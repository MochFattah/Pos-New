#!/usr/bin/env node
// Tangkapan layar offline: HTML tiap jenis halaman dirender PHP (scripts/dump-pages.php, SQLite memori lewat harness tes,
// TANPA MySQL) lalu dibuka Playwright lewat server statis dengan CSS/JS/font terbangun apa adanya.
// BATAS: ini BUKAN uji end-to-end berbasis MySQL/server nyata; data adalah fixture, tautan/formulir dilayani seadanya.
// Keluaran: storage/cache/screens/<halaman>-<lebar>-<terang|gelap>.png (di-gitignore). Keluar 1 bila ada pelanggaran.
// Pakai: node scripts/screenshots.mjs
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { startServer, dumpPages } from './lib/static-server.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
let chromium;
try { ({ chromium } = createRequire(import.meta.url)('playwright')); } catch { /* lihat di bawah */ }
if (!chromium) { console.log('DILEWATI: paket playwright tidak tersedia'); process.exit(2); }

const outDir = join(root, 'storage/cache/screens');
mkdirSync(outDir, { recursive: true });
dumpPages(outDir);
const server = await startServer({ outDir });

const PAGES = [
    ['landing', '/'], ['login', '/masuk'], ['daftar', '/daftar'], ['beranda', '/beranda'], ['cabang', '/cabang'],
    ['pengguna', '/pengguna'], ['role', '/role'], ['pengaturan', '/pengaturan'],
    ['demo-berakhir', '/demo-berakhir', true], ['platform-masuk', '/__page/platform-masuk'],
];
const WIDTHS = [375, 768, 1280];
const SCHEMES = [['terang', 'light'], ['gelap', 'dark']];

const browser = await chromium.launch();
const violations = [];
let shots = 0;
for (const [scheme, colorScheme] of SCHEMES) {
    for (const width of WIDTHS) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme, reducedMotion: 'reduce' });
        for (const [name, path, allowHttp] of PAGES) {
            const page = await context.newPage();
            const tag = `${name}@${width}/${scheme}`;
            page.on('console', (m) => { if (['error', 'warning'].includes(m.type()) && !(allowHttp && /Failed to load resource/.test(m.text()))) violations.push(`${tag}: konsol ${m.type()}: ${m.text()}`); });
            page.on('pageerror', (e) => violations.push(`${tag}: pageerror ${e.message}`));
            page.on('requestfailed', (r) => violations.push(`${tag}: permintaan gagal ${r.url()}`));
            page.on('response', (r) => { if (r.status() >= 400 && r.url().includes('/assets/')) violations.push(`${tag}: ${r.status()} ${r.url()}`); });
            await page.goto(server.url + path);
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => document.fonts.ready);
            const dims = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
            if (width === 375 && dims.sw > dims.cw) violations.push(`${tag}: scroll horizontal (scrollWidth ${dims.sw} > clientWidth ${dims.cw})`);
            await page.screenshot({ path: join(outDir, `${name}-${width}-${scheme}.png`), fullPage: true });
            shots++;
            await page.close();
        }
        await context.close();
    }
}
await browser.close();
await server.close();
console.log(`${shots} tangkapan layar di ${outDir}`);
if (violations.length) {
    console.log('PELANGGARAN:\n  ' + [...new Set(violations)].join('\n  '));
    process.exit(1);
}
console.log('OK: tanpa scroll horizontal di 375, tanpa galat konsol/permintaan gagal (halaman dari fixture, bukan MySQL nyata)');
