#!/usr/bin/env node
// Hitung rasio kontras WCAG 2.x dari token di resources/css/tokens.css (terang dan gelap).
// Pasangan yang gagal tetapi tercatat di scripts/contrast-known-failures.json dilaporkan
// sebagai "KNOWN" dan tidak menggagalkan exit code, selama rasio sekarang tidak lebih rendah
// dari ratioAtListing. Kegagalan baru atau yang memburuk (WORSENED) menghasilkan exit 1.
// Entri usang (pasangan sudah lulus) dicetak sebagai PERINGATAN.
// Pakai: node scripts/check-contrast.mjs [--json] [--known=<berkas allowlist>]
import { readFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const asJson = process.argv.includes('--json');

const css = readFileSync(join(root, 'resources/css/tokens.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');

function block(selector) {
    const at = css.indexOf(selector + ' {');
    if (at < 0) throw new Error('Blok tidak ditemukan di tokens.css: ' + selector);
    const open = css.indexOf('{', at);
    const close = css.indexOf('}', open);
    const vars = {};
    for (const m of css.slice(open + 1, close).matchAll(/((?:--[\w-]+)|color-scheme)\s*:\s*([^;]+);/g)) vars[m[1]] = m[2].trim();
    return vars;
}

const light = block(':root');
const darkAttr = block(':root[data-theme="dark"]');
const darkMedia = block(':root:not([data-theme="light"])');

const same = (a, b) => JSON.stringify(Object.entries(a).sort()) === JSON.stringify(Object.entries(b).sort());
if (!same(darkAttr, darkMedia)) {
    console.error('GAGAL: blok tema gelap [data-theme="dark"] dan prefers-color-scheme tidak identik (semua properti, termasuk --shadow-overlay, --scrim, color-scheme).');
    process.exit(1);
}
const themes = { light, dark: { ...light, ...darkAttr } };

function resolve(vars, name, depth = 0) {
    let value = vars[name];
    if (value === undefined) throw new Error('Token tidak ada: ' + name);
    const ref = value.match(/^var\((--[\w-]+)\)$/);
    if (ref && depth < 5) return resolve(vars, ref[1], depth + 1);
    return value;
}
const hexToRgb = (h) => {
    if (!/^#[0-9a-f]{6}$/i.test(h)) throw new Error('Bukan hex 6 digit: ' + h);
    return [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
};
const lin = (c) => { const s = c / 255; return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4; };
const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
const ratio = (a, b) => { const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x); return (hi + 0.05) / (lo + 0.05); };
// color-mix(in srgb, A, B p%) seperti yang dipakai .btn-danger:hover
const mix = (a, b, p) => a.map((v, i) => Math.round(v * (1 - p) + b[i] * p));

const pairs = [];
function add(id, group, min, fg, bg, note) { pairs.push({ id, group, min, fg, bg, note }); }

const texts = ['text-primary', 'text-secondary', 'text-muted'];
const bgs = ['background', 'surface', 'surface-muted'];
for (const t of texts) for (const b of bgs) add(`${t} on ${b}`, 'teks', 4.5, t, b);
add('text-secondary on primary-subtle', 'teks', 4.5, 'text-secondary', 'primary-subtle');
add('text-primary on primary-subtle', 'teks', 4.5, 'text-primary', 'primary-subtle');
add('on-primary on primary', 'tombol primary', 4.5, 'on-primary', 'primary');
add('on-primary on primary-hover', 'tombol primary', 4.5, 'on-primary', 'primary-hover');
add('on-danger on danger', 'tombol danger', 4.5, 'on-danger', 'danger');
add('on-danger on danger (hover, campuran 18% text-primary)', 'tombol danger', 4.5, 'on-danger', { mix: ['danger', 'text-primary', 0.18] });
for (const b of ['background', 'surface', 'primary-subtle']) add(`primary (tautan/nav aktif) on ${b}`, 'tautan', 4.5, 'primary', b);
add('primary (tautan/nav aktif) on surface-muted', 'tautan', 4.5, 'primary', 'surface-muted');
for (const s of ['success', 'warning', 'danger', 'info']) {
    add(`${s} on ${s}-subtle`, 'status', 4.5, s, `${s}-subtle`);
    for (const b of bgs) add(`${s} on ${b}`, 'status', 4.5, s, b);
    for (const t of ['text-primary', 'text-secondary']) add(`${t} on ${s}-subtle`, 'status', 4.5, t, `${s}-subtle`);
}
// border = pemisah dekoratif (kartu, tabel), tidak diuji; border-strong = batas kontrol (WCAG 1.4.11).
for (const b of ['background', 'surface', 'surface-muted']) add(`border-strong on ${b}`, 'non-teks', 3, 'border-strong', b, 'WCAG 1.4.11 untuk border kontrol');
for (const b of ['background', 'surface', 'surface-muted', 'primary-subtle']) add(`focus-ring on ${b}`, 'non-teks', 3, 'focus-ring', b);

const knownArg = process.argv.find((a) => a.startsWith('--known='));
const knownPath = knownArg ? knownArg.slice(8) : join(root, 'scripts/contrast-known-failures.json');
const known = existsSync(knownPath) ? JSON.parse(readFileSync(knownPath, 'utf8')).failures : [];
const isKnown = (theme, id) => known.find((k) => k.theme === theme && k.id === id);

const rows = [];
for (const [theme, vars] of Object.entries(themes)) {
    const col = (n) => hexToRgb(resolve(vars, `--color-${n}`));
    const val = (spec) => (typeof spec === 'string' ? col(spec) : mix(col(spec.mix[0]), col(spec.mix[1]), spec.mix[2]));
    for (const p of pairs) {
        const r = ratio(val(p.fg), val(p.bg));
        const pass = r >= p.min;
        const r2 = Math.round(r * 100) / 100;
        const k = pass ? null : isKnown(theme, p.id);
        let status = 'PASS';
        if (!pass) status = !k ? 'FAIL' : (typeof k.ratioAtListing !== 'number' || r2 < k.ratioAtListing ? 'WORSENED' : 'KNOWN');
        rows.push({ theme, id: p.id, group: p.group, min: p.min, ratio: r2, status });
    }
}
const stale = known.filter((k) => !rows.some((r) => r.theme === k.theme && r.id === k.id && (r.status === 'KNOWN' || r.status === 'WORSENED')));

if (asJson) {
    console.log(JSON.stringify({ rows, stale }, null, 2));
} else {
    console.log('Laporan kontras WCAG (nilai dari resources/css/tokens.css)\n');
    console.log('tema   status  rasio  min  pasangan');
    for (const r of rows) {
        console.log(`${r.theme.padEnd(6)} ${r.status.padEnd(7)} ${r.ratio.toFixed(2).padStart(5)}  ${String(r.min).padStart(3)}  ${r.id}`);
    }
    const count = (s) => rows.filter((r) => r.status === s).length;
    const knownRows = rows.filter((r) => r.status === 'KNOWN');
    if (knownRows.length) {
        console.log('\n!!! KEGAGALAN TERCATAT (menunggu keputusan pemilik proyek) !!!');
        for (const r of knownRows) console.log(`  [${r.theme}] ${r.id}: ${r.ratio.toFixed(2)} < ${r.min} (tercatat ${isKnown(r.theme, r.id).ratioAtListing})  -- ${isKnown(r.theme, r.id).reason}`);
    }
    for (const r of rows.filter((x) => x.status === 'WORSENED')) console.log(`GAGAL: kegagalan tercatat MEMBURUK: [${r.theme}] ${r.id}: ${r.ratio.toFixed(2)} < ratioAtListing ${isKnown(r.theme, r.id).ratioAtListing}`);
    for (const k of stale) console.log(`PERINGATAN: entri allowlist USANG (pasangan sudah lulus atau tidak ada lagi), hapus dari contrast-known-failures.json: [${k.theme}] ${k.id}`);
    console.log(`\nTotal ${rows.length}: ${count('PASS')} lulus, ${count('KNOWN')} gagal tercatat, ${count('WORSENED')} memburuk, ${count('FAIL')} gagal BARU`);
}
process.exit(rows.some((r) => r.status === 'FAIL' || r.status === 'WORSENED') ? 1 : 0);
