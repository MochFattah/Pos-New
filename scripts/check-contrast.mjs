#!/usr/bin/env node
// Hitung rasio kontras WCAG 2.x dari token di resources/css/tokens.css (terang dan gelap).
// Pasangan yang gagal tetapi tercatat di scripts/contrast-known-failures.json dilaporkan
// sebagai "known" dan tidak menggagalkan exit code; kegagalan baru menghasilkan exit 1.
// Pakai: node scripts/check-contrast.mjs [--json]
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
    for (const m of css.slice(open + 1, close).matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)) vars[m[1]] = m[2].trim();
    return vars;
}

const light = block(':root');
const darkAttr = block(':root[data-theme="dark"]');
const darkMedia = block(':root:not([data-theme="light"])');

const colorKeys = (v) => Object.keys(v).filter((k) => k.startsWith('--color-')).sort();
if (JSON.stringify(colorKeys(darkAttr)) !== JSON.stringify(colorKeys(darkMedia))
    || colorKeys(darkAttr).some((k) => darkAttr[k] !== darkMedia[k])) {
    console.error('GAGAL: blok tema gelap [data-theme="dark"] dan prefers-color-scheme tidak identik.');
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
for (const s of ['success', 'warning', 'danger', 'info']) {
    add(`${s} on ${s}-subtle`, 'status', 4.5, s, `${s}-subtle`);
    add(`${s} on surface`, 'status', 4.5, s, 'surface');
}
for (const b of ['background', 'surface', 'surface-muted']) add(`border on ${b}`, 'non-teks', 3, 'border', b, 'WCAG 1.4.11 untuk border input');
for (const b of ['background', 'surface', 'surface-muted', 'primary-subtle']) add(`focus-ring on ${b}`, 'non-teks', 3, 'focus-ring', b);

const knownPath = join(root, 'scripts/contrast-known-failures.json');
const known = existsSync(knownPath) ? JSON.parse(readFileSync(knownPath, 'utf8')).failures : [];
const isKnown = (theme, id) => known.find((k) => k.theme === theme && k.id === id);

const rows = [];
for (const [theme, vars] of Object.entries(themes)) {
    const col = (n) => hexToRgb(resolve(vars, `--color-${n}`));
    const val = (spec) => (typeof spec === 'string' ? col(spec) : mix(col(spec.mix[0]), col(spec.mix[1]), spec.mix[2]));
    for (const p of pairs) {
        const r = ratio(val(p.fg), val(p.bg));
        const pass = r >= p.min;
        const k = pass ? null : isKnown(theme, p.id);
        rows.push({ theme, id: p.id, group: p.group, min: p.min, ratio: Math.round(r * 100) / 100, status: pass ? 'PASS' : (k ? 'KNOWN' : 'FAIL') });
    }
}
const stale = known.filter((k) => !rows.some((r) => r.theme === k.theme && r.id === k.id && r.status === 'KNOWN'));

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
        for (const r of knownRows) console.log(`  [${r.theme}] ${r.id}: ${r.ratio.toFixed(2)} < ${r.min}  -- ${isKnown(r.theme, r.id).reason}`);
    }
    for (const k of stale) console.log(`PERINGATAN: entri allowlist tidak lagi gagal atau tidak dikenal: [${k.theme}] ${k.id}`);
    console.log(`\nTotal ${rows.length}: ${count('PASS')} lulus, ${count('KNOWN')} gagal tercatat, ${count('FAIL')} gagal BARU`);
}
process.exit(rows.some((r) => r.status === 'FAIL') ? 1 : 0);
