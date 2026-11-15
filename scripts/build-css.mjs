#!/usr/bin/env node
// Kompilasi resources/css/app.css dengan API Node tailwindcss lokal (tanpa CLI, tanpa jaringan).
// Kandidat kelas dipindai dari resources/views/**/*.php dan resources/js/**/*.js.
// Lokasi paket: require.resolve (cwd/proyek), TAILWIND_MODULES, NODE_PATH, root global npm, /opt/npm-tools.
// Pakai: node scripts/build-css.mjs [--no-minify] [--out=<berkas>]
// Batas: minifier berbasis regex tidak memahami string; spasi ganda atau karakter { } ; , > di dalam
// string/content CSS ikut dirapatkan. Hindari pola itu di literal string (atau ganti dengan minifier sungguhan).
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync, readdirSync, statSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join, resolve, delimiter } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const minify = !process.argv.includes('--no-minify');

function findTailwind() {
    const tried = [];
    const tryDir = (dir, label) => {
        const pkg = join(dir, 'tailwindcss');
        tried.push(`${label}: ${pkg}`);
        return existsSync(join(pkg, 'package.json')) && existsSync(join(pkg, 'dist/lib.mjs')) ? pkg : null;
    };
    // 1) resolusi Node dari cwd dan proyek (node_modules bertingkat, NODE_PATH)
    for (const base of [process.cwd(), root]) {
        try {
            const pj = createRequire(join(base, 'noop.js')).resolve('tailwindcss/package.json');
            tried.push(`require.resolve dari ${base}: ${pj}`);
            return dirname(pj);
        } catch { tried.push(`require.resolve dari ${base}: tidak ada`); }
    }
    // 2) TAILWIND_MODULES, NODE_PATH, lalu root global npm
    const dirs = [['TAILWIND_MODULES', process.env.TAILWIND_MODULES], ...String(process.env.NODE_PATH || '').split(delimiter).filter(Boolean).map((d) => ['NODE_PATH', d])];
    try { dirs.push(['npm root -g', execFileSync('npm', ['root', '-g'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim()]); } catch { tried.push('npm root -g: gagal dijalankan'); }
    dirs.push(['/opt/npm-tools', '/opt/npm-tools/node_modules']);
    for (const [label, d] of dirs) { if (d) { const f = tryDir(d, label); if (f) return f; } }
    console.error('tailwindcss (v4, dengan dist/lib.mjs) tidak ditemukan. Percobaan:\n  ' + tried.join('\n  ') +
        '\nPasang tailwindcss secara lokal/global, atau set TAILWIND_MODULES=<folder node_modules>.');
    process.exit(1);
}
const pkgDir = findTailwind();
const { compile } = await import(pathToFileURL(join(pkgDir, 'dist/lib.mjs')).href);

function walk(dir, exts, out = []) {
    if (!existsSync(dir)) return out;
    for (const name of readdirSync(dir)) {
        const p = join(dir, name);
        if (statSync(p).isDirectory()) walk(p, exts, out);
        else if (exts.some((e) => name.endsWith(e))) out.push(p);
    }
    return out;
}

const candidates = new Set();
for (const file of [...walk(join(root, 'resources/views'), ['.php']), ...walk(join(root, 'resources/js'), ['.js'])]) {
    for (const token of readFileSync(file, 'utf8').split(/[\s"'`<>=(){};,]+/)) {
        if (token && token.length < 100 && /^[!a-zA-Z0-9\-_:/.\[\]%#@&*]+$/.test(token)) candidates.add(token);
    }
}

async function loadStylesheet(id, base) {
    if (id === 'tailwindcss') return { path: join(pkgDir, 'index.css'), base: pkgDir, content: readFileSync(join(pkgDir, 'index.css'), 'utf8') };
    if (id.startsWith('tailwindcss/')) {
        const path = join(pkgDir, id.slice('tailwindcss/'.length) + (id.endsWith('.css') ? '' : '.css'));
        return { path, base: dirname(path), content: readFileSync(path, 'utf8') };
    }
    if (id.startsWith('.')) {
        const path = resolve(base, id);
        return { path, base: dirname(path), content: readFileSync(path, 'utf8') };
    }
    throw new Error('Stylesheet tidak diizinkan (hanya lokal): ' + id);
}

const entry = join(root, 'resources/css/app.css');
const compiler = await compile(readFileSync(entry, 'utf8'), { base: dirname(entry), from: entry, loadStylesheet });
let out = compiler.build([...candidates]);

if (minify) {
    out = out
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/\s+/g, ' ')
        .replace(/\s*([{};,>])\s*/g, '$1')
        .replace(/;}/g, '}')
        .trim() + '\n';
}

// Hanya url(...) dan @import yang dihitung; URL di komentar (mis. banner lisensi Tailwind) diabaikan.
const noComments = out.replace(/\/\*[\s\S]*?\*\//g, '');
if (/url\(\s*['"]?\s*(?:https?:)?\/\//i.test(noComments) || /@import\s+(?:url\(\s*)?['"]?\s*(?:https?:)?\/\//i.test(noComments)) {
    console.error('Keluaran memuat url()/@import eksternal; build ditolak.');
    process.exit(1);
}

const outArg = process.argv.find((a) => a.startsWith('--out='));
const target = outArg ? resolve(outArg.slice(6)) : join(root, 'public/assets/css/app.css');
mkdirSync(dirname(target), { recursive: true });
writeFileSync(target, out);
console.log(`OK ${target} (${out.length} byte, ${candidates.size} kandidat dipindai)`);
