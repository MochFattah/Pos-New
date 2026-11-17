#!/usr/bin/env node
// Menyalin resources/js/**/*.js ke public/assets/js (tanpa bundler, tanpa transformasi; modul ES apa adanya).
// Sumber kebenaran: resources/js. Hasil salinan dikomit; CssBuildTest/ViewTest mendeteksi salinan usang.
// Pakai: node scripts/build-js.mjs
import { readdirSync, readFileSync, writeFileSync, mkdirSync, statSync, existsSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const src = join(root, 'resources/js');
const dest = join(root, 'public/assets/js');

function walk(dir, base = '') {
    const out = [];
    for (const name of readdirSync(dir)) {
        const rel = base ? `${base}/${name}` : name;
        if (statSync(join(dir, name)).isDirectory()) out.push(...walk(join(dir, name), rel));
        else if (name.endsWith('.js')) out.push(rel);
    }
    return out;
}

const files = walk(src);
const wanted = new Set(files);
if (existsSync(dest)) {
    for (const rel of walk(dest)) if (!wanted.has(rel)) rmSync(join(dest, rel));
}
for (const rel of files) {
    mkdirSync(dirname(join(dest, rel)), { recursive: true });
    writeFileSync(join(dest, rel), readFileSync(join(src, rel)));
}
console.log(`OK ${files.length} berkas JS disalin ke public/assets/js`);
