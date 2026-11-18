#!/usr/bin/env node
// Tes perilaku modul JS di Chromium (Playwright) terhadap HTML hasil scripts/dump-pages.php. Offline, tanpa MySQL.
// Pakai: node scripts/test-js.mjs   (butuh php, node, paket playwright + Chromium; keluar 2 bila tak tersedia)
import { createRequire } from 'node:module';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { startServer, dumpPages } from './lib/static-server.mjs';

let chromium;
try {
    ({ chromium } = createRequire(import.meta.url)('playwright'));
} catch {
    try { ({ chromium } = createRequire(join(process.cwd(), 'x.js'))('playwright')); } catch { /* ditangani di bawah */ }
}
if (!chromium) {
    console.log('DILEWATI: paket playwright tidak tersedia');
    process.exit(2);
}

const outDir = mkdtempSync(join(tmpdir(), 'pos-js-'));
dumpPages(outDir);
const server = await startServer({ outDir, postDelayMs: 300 });
let browser;
try {
    browser = await chromium.launch();
} catch (e) {
    console.log('DILEWATI: Chromium tidak dapat dijalankan: ' + e.message.split('\n')[0]);
    await server.close();
    process.exit(2);
}

const MOBILE = { width: 375, height: 800 };
const DESKTOP = { width: 1280, height: 800 };
const results = [];

/** Satu konteks baru per tes; semua galat konsol/halaman/permintaan gagal membuat tes gagal. */
async function test(name, fn, { viewport = DESKTOP, tolerateHttpErrors = false, javaScriptEnabled = true, colorScheme = 'light', reducedMotion = 'no-preference', init } = {}) {
    const context = await browser.newContext({ viewport, javaScriptEnabled, colorScheme, reducedMotion });
    if (init) await context.addInitScript(init);
    const problems = [];
    const watch = (page) => {
        page.on('console', (m) => { if ((m.type() === 'error' || m.type() === 'warning') && !(tolerateHttpErrors && /Failed to load resource/.test(m.text()))) problems.push(`konsol ${m.type()}: ${m.text()}`); });
        page.on('pageerror', (e) => problems.push('pageerror: ' + e.message));
        page.on('requestfailed', (r) => problems.push(`permintaan gagal: ${r.url()} ${r.failure()?.errorText}`));
        page.on('response', (r) => { if (r.status() >= 400 && !tolerateHttpErrors && (r.url().includes('/assets/') || r.request().resourceType() !== 'document')) problems.push(`${r.status()}: ${r.url()}`); });
    };
    context.on('page', watch);
    const page = await context.newPage();
    server.log.length = 0;
    try {
        await fn(page, context);
        if (problems.length) throw new Error('galat tak terduga:\n  ' + problems.join('\n  '));
        results.push({ name, ok: true });
        console.log('PASS  ' + name);
    } catch (e) {
        results.push({ name, ok: false });
        console.log('FAIL  ' + name + '\n      ' + String(e.message).split('\n').join('\n      '));
    } finally {
        await context.close();
    }
}

function assert(cond, message) {
    if (!cond) throw new Error(message);
}
const go = (page, path) => page.goto(server.url + path);
const active = (page) => page.evaluate(() => { const a = document.activeElement; return a ? (a.id || a.getAttribute('data-drawer-close') !== null && 'drawer-close' || a.textContent.trim().slice(0, 40) || a.tagName) : null; });
const posts = (path) => server.log.filter((r) => r.method === 'POST' && r.path === path);

// --- Tanpa galat konsol di setiap halaman, dengan CSP ketat dari aplikasi ---
await test('semua halaman termuat tanpa galat konsol/permintaan gagal (desktop)', async (page) => {
    const paths = ['/', '/masuk', '/daftar', '/lupa-password', '/bantuan', '/beranda', '/cabang', '/cabang/baru', '/pengguna', '/pengguna/undang', '/role', '/role/baru', '/pengaturan', '/__page/error-404', '/__page/error-500', '/__page/platform-masuk', '/__page/platform-beranda'];
    for (const p of paths) {
        const res = await go(page, p);
        assert(res && res.status() === 200, `${p}: status ${res?.status()}`);
        await page.waitForLoadState('networkidle');
    }
});
await test('halaman status 403 (demo berakhir) dan 404 memuat JS tanpa galat skrip', async (page) => {
    for (const p of ['/demo-berakhir', '/tidak-ada']) {
        await go(page, p);
        await page.waitForLoadState('networkidle');
        assert(await page.locator('main#main-content').count() === 1, p + ': kerangka halaman');
    }
}, { tolerateHttpErrors: true });
await test('halaman termuat tanpa galat konsol di mobile', async (page) => {
    for (const p of ['/masuk', '/beranda', '/cabang', '/pengguna', '/pengaturan']) {
        await go(page, p);
        await page.waitForLoadState('networkidle');
    }
}, { viewport: MOBILE });

// --- Drawer ---
await test('drawer: buka/tutup, aria-expanded, fokus masuk, inert, Esc, fokus kembali', async (page) => {
    await go(page, '/beranda');
    const opener = page.locator('[data-drawer-open]');
    const drawer = page.locator('#navigasi');
    assert((await opener.getAttribute('aria-expanded')) === 'false', 'aria-expanded awal harus false');
    assert(await drawer.evaluate((el) => el.inert), 'drawer tertutup harus inert (tak bisa difokuskan)');
    await opener.click();
    assert((await opener.getAttribute('aria-expanded')) === 'true', 'aria-expanded harus true saat terbuka');
    assert((await drawer.getAttribute('data-open')) === 'true', 'data-open true');
    assert(await drawer.evaluate((el) => el.contains(document.activeElement)), 'fokus harus pindah ke dalam drawer');
    assert(await page.evaluate(() => document.querySelector('.topbar').inert && document.querySelector('#main-content').inert), 'latar (topbar, main) harus inert');
    await page.waitForTimeout(350);
    assert((await drawer.boundingBox()).x >= 0, 'drawer harus tampak di layar');
    await page.keyboard.press('Escape');
    assert((await opener.getAttribute('aria-expanded')) === 'false', 'Esc menutup drawer');
    assert((await active(page)) === 'Menu', 'fokus kembali ke tombol Menu, dapat: ' + (await active(page)));
    assert(await page.evaluate(() => !document.querySelector('#main-content').inert), 'latar tidak lagi inert');
}, { viewport: MOBILE });

await test('drawer: pembuka adalah <button> nyata dan Spasi/Enter membukanya', async (page) => {
    await go(page, '/beranda');
    const opener = page.locator('[data-drawer-open]');
    assert((await opener.evaluate((el) => el.tagName)) === 'BUTTON', 'pembuka harus <button> saat JS aktif');
    assert((await opener.getAttribute('role')) === null, 'tanpa role=button buatan');
    assert((await opener.getAttribute('aria-controls')) === 'navigasi', 'aria-controls terbawa');
    await opener.focus();
    await page.keyboard.press('Space');
    assert((await opener.getAttribute('aria-expanded')) === 'true', 'Spasi membuka drawer');
    await page.keyboard.press('Escape');
    assert((await opener.getAttribute('aria-expanded')) === 'false', 'Esc menutup');
    assert((await active(page)) === 'Menu', 'fokus kembali ke tombol Menu');
    await page.keyboard.press('Enter');
    assert((await opener.getAttribute('aria-expanded')) === 'true', 'Enter membuka drawer');
    assert(!page.url().includes('#'), 'URL tidak berubah hash');
}, { viewport: MOBILE });

await test('drawer: perangkap fokus (Tab dan Shift+Tab berputar di dalam drawer)', async (page) => {
    await go(page, '/beranda');
    await page.locator('[data-drawer-open]').click();
    const inside = () => page.evaluate(() => document.querySelector('#navigasi').contains(document.activeElement));
    const count = await page.evaluate(() => document.querySelectorAll('#navigasi a[href]').length);
    for (let i = 0; i < count + 3; i++) {
        await page.keyboard.press('Tab');
        assert(await inside(), `Tab ke-${i + 1} keluar dari drawer`);
    }
    for (let i = 0; i < count + 3; i++) {
        await page.keyboard.press('Shift+Tab');
        assert(await inside(), `Shift+Tab ke-${i + 1} keluar dari drawer`);
    }
}, { viewport: MOBILE });

await test('drawer: klik scrim dan tombol Tutup menu menutup, fokus kembali', async (page) => {
    await go(page, '/beranda');
    const opener = page.locator('[data-drawer-open]');
    await opener.click();
    await page.mouse.click(360, 400); // di luar drawer (lebar 18rem = 288 px), pada scrim
    assert((await opener.getAttribute('aria-expanded')) === 'false', 'klik scrim menutup');
    assert((await active(page)) === 'Menu', 'fokus kembali setelah scrim');
    await opener.click();
    await page.locator('[data-drawer-close]').click();
    assert((await opener.getAttribute('aria-expanded')) === 'false', 'tombol tutup menutup');
    assert((await active(page)) === 'Menu', 'fokus kembali setelah tutup');
    assert(!(await page.url()).includes('#'), 'URL tidak berubah hash');
}, { viewport: MOBILE });

await test('drawer: desktop menampilkan sidebar tanpa inert dan tanpa tombol Menu', async (page) => {
    await go(page, '/beranda');
    assert(await page.evaluate(() => !document.querySelector('#navigasi').inert), 'sidebar desktop tidak inert');
    assert(!(await page.locator('[data-drawer-open]').isVisible()), 'tombol Menu tersembunyi di desktop');
    assert(await page.locator('#navigasi a.nav-item').first().isVisible(), 'menu terlihat');
});

await test('layout: sidebar desktop menempel dan setinggi viewport saat halaman panjang digulir', async (page) => {
    await go(page, '/pengaturan');
    await page.evaluate(() => { const m = document.querySelector('#main-content'); const pad = document.createElement('div'); pad.style.height = '3000px'; m.append(pad); });
    const before = await page.locator('#navigasi').boundingBox();
    assert(Math.abs(before.height - DESKTOP.height) <= 1, 'tinggi sidebar harus = viewport, dapat ' + before.height);
    await page.evaluate(() => window.scrollTo(0, 1500));
    const after = await page.locator('#navigasi').boundingBox();
    assert(Math.abs(after.y) <= 1 && Math.abs(after.height - DESKTOP.height) <= 1, `sidebar harus menempel di atas setelah gulir, y=${after.y} h=${after.height}`);
});

await test('drawer: tanpa JS tetap bisa dibuka lewat :target', async (page) => {
    await go(page, '/beranda');
    await page.locator('[data-drawer-open]').click();
    await page.waitForTimeout(350);
    assert((await page.locator('#navigasi').boundingBox()).x >= 0, 'sidebar harus tampil saat :target');
    await page.locator('#navigasi a.nav-item').nth(1).click();
    assert(page.url().endsWith('/cabang'), 'tautan menu bekerja tanpa JS');
}, { viewport: MOBILE, javaScriptEnabled: false });

await test('drawer: gerak dikurangi (prefers-reduced-motion) menghapus transisi', async (page) => {
    await go(page, '/beranda');
    const d = await page.locator('#navigasi').evaluate((el) => parseFloat(getComputedStyle(el).transitionDuration));
    assert(d <= 0.001, 'transition-duration harus ~0, dapat ' + d);
}, { viewport: MOBILE, reducedMotion: 'reduce' });

// --- Target sentuh ---
await test('target sentuh: tautan mandiri (tabel role, halaman masuk) minimal 44px', async (page) => {
    for (const [path, sel] of [['/role', '.table-data tbody th a'], ['/masuk', 'main p.stack-sm > a'], ['/daftar', 'main .stack-sm > a, main p > a']]) {
        await go(page, path);
        const heights = await page.locator(sel).evaluateAll((els) => els.map((e) => e.getBoundingClientRect().height));
        assert(heights.length > 0, `${path}: tautan ${sel} tidak ditemukan`);
        assert(heights.every((h) => h >= 43.9), `${path}: tinggi tautan < 44px: ${heights.join(', ')}`);
    }
}, { viewport: MOBILE });
await test('target sentuh: .table-wrap dapat difokuskan dengan keyboard', async (page) => {
    await go(page, '/role');
    await page.keyboard.press('Tab');
    const wrap = page.locator('.table-wrap');
    await wrap.focus();
    assert(await wrap.evaluate((el) => el === document.activeElement), 'wilayah tabel dapat difokuskan');
}, { viewport: MOBILE });

// --- Dialog ---
const trigger = (page) => page.locator('form[data-confirm-dialog] button[type="submit"]').first();
await test('dialog: konfirmasi membuka modal, fokus masuk, Esc menutup dan fokus kembali, tanpa kirim', async (page) => {
    await go(page, '/cabang');
    const btn = trigger(page);
    const label = await btn.getAttribute('aria-label');
    await btn.click();
    const dialog = page.locator('dialog[open]');
    assert((await dialog.count()) === 1, 'dialog harus terbuka');
    assert(await dialog.evaluate((el) => el.contains(document.activeElement)), 'fokus harus di dalam dialog');
    assert((await active(page)) === 'Batal', 'fokus awal pada Batal (aksi aman)');
    assert((await dialog.getAttribute('aria-labelledby')) !== null, 'dialog bernama');
    await page.keyboard.press('Escape');
    assert((await page.locator('dialog[open]').count()) === 0, 'Esc menutup');
    assert((await btn.getAttribute('aria-label')) === label && await btn.evaluate((el) => el === document.activeElement), 'fokus kembali ke pemicu');
    assert(server.log.every((r) => r.method !== 'POST'), 'tidak boleh ada POST saat dibatalkan');
});
await test('dialog: tombol Batal dan klik latar menutup tanpa kirim', async (page) => {
    await go(page, '/cabang');
    await trigger(page).click();
    await page.locator('dialog[open] [data-dialog-close]').click();
    assert((await page.locator('dialog[open]').count()) === 0, 'Batal menutup');
    await trigger(page).click();
    await page.mouse.click(5, 5); // latar
    assert((await page.locator('dialog[open]').count()) === 0, 'klik latar menutup');
    assert(server.log.every((r) => r.method !== 'POST'), 'tidak ada POST');
});
await test('dialog: konfirmasi mengirim form POST sekali', async (page) => {
    await go(page, '/cabang');
    const action = await page.locator('form[data-confirm-dialog]').first().getAttribute('action');
    await trigger(page).click();
    await page.locator('dialog[open] [data-dialog-confirm]').click();
    await page.waitForURL(/\/cabang\?flash=1$/);
    assert(posts(action).length === 1, `harus tepat 1 POST ke ${action}, dapat ${posts(action).length}`);
});
await test('dialog: tanpa JS form konfirmasi tetap terkirim sebagai form biasa', async (page) => {
    await go(page, '/cabang');
    const action = await page.locator('form[data-confirm-dialog]').first().getAttribute('action');
    await trigger(page).click();
    await page.waitForURL(/\/cabang\?flash=1$/);
    assert(posts(action).length === 1, 'POST terkirim tanpa JS');
}, { javaScriptEnabled: false });

// --- Kirim ganda ---
await test('form: kirim ganda dicegah, aria-busy dipasang, teks dipertahankan, pulih saat pageshow', async (page) => {
    await go(page, '/masuk');
    await page.fill('#email', 'budi@example.com');
    await page.fill('#password', 'rahasia-banget');
    const btn = page.locator('form button[type="submit"]').first();
    const text = (await btn.textContent()).trim();
    // Tiga upaya kirim berturut-turut dalam satu tick (klik ganda + Enter); status dibaca di tick yang sama
    // karena respons server (POST /masuk) bisa tiba dan mengganti dokumen sebelum pembacaan terpisah.
    const state = await btn.evaluate((el) => {
        el.click();
        el.click();
        el.form.requestSubmit(el);
        return { busy: el.getAttribute('aria-busy'), disabled: el.getAttribute('aria-disabled'), text: el.textContent.trim() };
    });
    assert(state.busy === 'true', 'aria-busy saat mengirim');
    assert(state.disabled === 'true', 'aria-disabled saat mengirim');
    assert(state.text === text, 'teks tombol dipertahankan');
    await page.waitForLoadState('load');
    await page.waitForTimeout(600);
    assert(posts('/masuk').length === 1, `tepat 1 POST /masuk, dapat ${posts('/masuk').length}`);
    // Halaman hasil (galat) lalu kembali: tombol harus aktif lagi.
    await page.goBack();
    await page.waitForLoadState('load');
    const again = page.locator('form button[type="submit"]').first();
    assert((await again.getAttribute('aria-busy')) === null, 'aria-busy hilang setelah kembali');
    assert((await again.getAttribute('aria-disabled')) === null, 'aria-disabled hilang setelah kembali');
});
await test('form: submitter dengan formtarget bukan _self tidak mengunci form', async (page) => {
    await go(page, '/masuk');
    const busy = await page.evaluate(() => {
        const form = document.querySelector('main form');
        const btn = form.querySelector('button[type="submit"]');
        btn.setAttribute('formtarget', '_blank');
        document.addEventListener('submit', (e) => e.preventDefault(), { once: true });
        form.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: btn }));
        return [form.getAttribute('aria-busy'), btn.getAttribute('aria-busy')];
    });
    assert(busy[0] === null && busy[1] === null, 'formtarget=_blank tidak boleh memasang aria-busy: ' + busy.join(','));
});
await test('galat validasi: href ringkasan dengan % tak valid tidak melempar galat', async (page) => {
    await go(page, '/masuk');
    await page.evaluate(() => { document.querySelector('main form').noValidate = true; });
    await page.locator('main form button[type="submit"]').click();
    await page.waitForSelector('#error-summary');
    await page.evaluate(() => { document.querySelector('#error-summary a[href^="#"]').setAttribute('href', '#%E0%A4%A'); });
    await page.locator('#error-summary a[href^="#"]').first().click(); // pageerror akan menggagalkan tes
});
await test('form: pageshow dari bfcache melepas status sibuk', async (page) => {
    await go(page, '/masuk');
    await page.evaluate(() => document.querySelector('form').addEventListener('submit', (e) => e.preventDefault(), { once: false, capture: false }));
    // Pasang status sibuk lewat submit yang tidak dicegat form-submit: dispatch event sintetis lalu pageshow.
    await page.evaluate(() => {
        const form = document.querySelector('form');
        form.removeAttribute('data-x');
        form.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: form.querySelector('button[type="submit"]') }));
    });
    // Handler dokumen berjalan setelah handler form yang memanggil preventDefault, jadi status belum terpasang: pastikan netral.
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
    assert((await page.locator('form button[type="submit"]').first().getAttribute('aria-busy')) === null, 'pageshow tidak meninggalkan status sibuk');
});

// --- Ringkasan galat ---
await test('galat validasi: fokus ke ringkasan galat; tautan memfokuskan field', async (page) => {
    await go(page, '/masuk');
    await page.evaluate(() => { document.querySelector('form').noValidate = true; });
    await page.locator('form button[type="submit"]').first().click();
    await page.waitForURL(/\/masuk$/);
    await page.waitForSelector('#error-summary');
    assert((await active(page)) === 'error-summary', 'fokus harus di #error-summary, dapat ' + (await active(page)));
    assert((await page.locator('#error-summary').getAttribute('tabindex')) === '-1', 'tabindex -1');
    const link = page.locator('#error-summary a[href^="#"]').first();
    const target = (await link.getAttribute('href')).slice(1);
    await link.click();
    assert((await active(page)) === target, `tautan harus memfokuskan #${target}, dapat ${await active(page)}`);
    assert(!page.url().includes('#'), 'hash tidak berubah');
});
await test('galat validasi (daftar, mobile): ringkasan terfokus dan tautan bekerja', async (page) => {
    await go(page, '/daftar');
    await page.evaluate(() => { document.querySelector('main form').noValidate = true; });
    await page.locator('main form button[type="submit"]').click();
    await page.waitForSelector('#error-summary');
    assert((await active(page)) === 'error-summary', 'fokus di ringkasan');
    const targets = await page.locator('#error-summary a[href^="#"]').evaluateAll((as) => as.map((a) => a.getAttribute('href').slice(1)));
    assert(targets.length > 1, 'beberapa tautan galat');
    await page.locator('#error-summary a[href^="#"]').nth(1).click();
    assert((await active(page)) === targets[1], 'tautan kedua memfokuskan field-nya');
}, { viewport: MOBILE });

// --- Tema ---
await test('tema: toggle mengubah tema, aria-pressed, tersimpan setelah muat ulang', async (page) => {
    await go(page, '/beranda');
    const toggle = page.locator('[data-theme-toggle]');
    assert(await toggle.isVisible(), 'tombol tema tampil dengan JS');
    assert((await toggle.getAttribute('aria-pressed')) === 'false', 'awal: terang');
    await toggle.click();
    assert((await page.locator('html').getAttribute('data-theme')) === 'dark', 'data-theme dark');
    assert((await toggle.getAttribute('aria-pressed')) === 'true', 'aria-pressed true');
    assert((await page.evaluate(() => localStorage.getItem('pos-theme'))) === 'dark', 'tersimpan di localStorage');
    await page.reload();
    assert((await page.locator('html').getAttribute('data-theme')) === 'dark', 'tema bertahan setelah muat ulang');
    const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    assert(bg === 'rgb(16, 20, 28)', 'latar gelap diterapkan, dapat ' + bg);
    await go(page, '/cabang');
    assert((await page.locator('html').getAttribute('data-theme')) === 'dark', 'tema bertahan di halaman lain');
    await page.locator('[data-theme-toggle]').click();
    assert((await page.locator('html').getAttribute('data-theme')) === 'light', 'kembali ke terang');
});
await test('tema: mengikuti preferensi sistem gelap tanpa pilihan tersimpan', async (page) => {
    await go(page, '/beranda');
    const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    assert(bg === 'rgb(16, 20, 28)', 'sistem gelap harus menghasilkan latar gelap, dapat ' + bg);
    assert((await page.locator('[data-theme-toggle]').getAttribute('aria-pressed')) === 'true', 'aria-pressed sesuai sistem');
}, { colorScheme: 'dark' });
await test('tema: localStorage diblokir tidak merusak halaman atau toggle', async (page) => {
    await go(page, '/beranda');
    await page.locator('[data-theme-toggle]').click();
    assert((await page.locator('html').getAttribute('data-theme')) === 'dark', 'tema tetap berlaku untuk sesi ini');
}, { init: () => Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('diblokir', 'SecurityError'); } }) });
await test('tema: skrip tema tidak menambah global (window maupun leksikal)', async (page) => {
    await go(page, '/beranda');
    const extra = await page.evaluate(() => {
        const frame = document.createElement('iframe');
        document.body.appendChild(frame);
        const base = new Set(Object.getOwnPropertyNames(frame.contentWindow));
        frame.remove();
        const lexical = ['KEY', 'initial', 'stored', 'systemTheme', 'apply'].filter((n) => { try { (0, eval)(n); return true; } catch { return false; } });
        return { props: Object.getOwnPropertyNames(window).filter((k) => !base.has(k) && !/^(webkit|__)/.test(k)), lexical };
    });
    assert(extra.lexical.length === 0, 'global leksikal bocor: ' + extra.lexical.join(', '));
    assert(extra.props.length === 0, 'properti window baru: ' + extra.props.join(', '));
});
await test('tema: keadaan tekan tombol tema terlihat berbeda (bukan hanya aria-pressed)', async (page) => {
    await go(page, '/beranda');
    const toggle = page.locator('[data-theme-toggle]');
    const look = () => toggle.evaluate((el) => { const c = getComputedStyle(el); return [c.backgroundColor, c.borderTopColor, c.color, c.fontWeight].join('|'); });
    await toggle.evaluate((el) => el.setAttribute('aria-pressed', 'false'));
    await page.waitForTimeout(300); // lewati transisi warna
    const off = await look();
    await toggle.evaluate((el) => el.setAttribute('aria-pressed', 'true'));
    await page.waitForTimeout(300);
    const on = await look();
    assert(off !== on, 'tampilan tombol harus berbeda saat aria-pressed=true, sama: ' + on);
});
await test('tema: tanpa JS tombol tema tersembunyi dan halaman tetap terbaca', async (page) => {
    await go(page, '/beranda');
    assert(!(await page.locator('[data-theme-toggle]').isVisible()), 'tombol tema memerlukan JS');
}, { javaScriptEnabled: false });

// --- Toast ---
await test('toast: flash sukses menjadi toast aria-live, bertahan >= 5 detik, lalu hilang', async (page) => {
    await page.clock.install();
    await go(page, '/cabang?flash=1');
    const region = page.locator('[data-toast-region]');
    assert((await region.getAttribute('aria-live')) === 'polite', 'wilayah aria-live');
    assert((await region.locator('.toast').count()) === 1, 'satu toast');
    assert(/Cabang/.test(await region.locator('.toast').textContent()), 'isi pesan flash terbawa');
    assert(await page.locator('[data-flash="notice"]').evaluate((el) => el.hidden), 'pesan inline disembunyikan agar tidak ganda');
    await page.clock.fastForward(5000);
    assert((await region.locator('.toast').count()) === 1, 'masih tampil setelah 5 detik');
    await page.clock.fastForward(2000);
    assert((await region.locator('.toast').count()) === 0, 'hilang otomatis');
});
await test('toast: berhenti saat hover/fokus, tombol tutup bekerja', async (page) => {
    await page.clock.install();
    await go(page, '/cabang?flash=1');
    const toast = page.locator('.toast');
    await toast.hover();
    await page.clock.fastForward(20000);
    assert((await toast.count()) === 1, 'hover menahan toast');
    await page.mouse.move(5, 5);
    await page.clock.fastForward(7000);
    assert((await toast.count()) === 0, 'hilang setelah hover berakhir');
    await go(page, '/cabang?flash=1');
    await page.locator('.toast button').focus();
    await page.clock.fastForward(20000);
    assert((await page.locator('.toast').count()) === 1, 'fokus menahan toast');
    await page.locator('.toast button').click();
    assert((await page.locator('.toast').count()) === 0, 'tombol Tutup menghapus toast');
});
await test('toast: tanpa JS pesan flash tetap tampil inline', async (page) => {
    await go(page, '/cabang?flash=1');
    assert(await page.locator('[data-flash="notice"]').isVisible(), 'pesan inline terlihat tanpa JS');
}, { javaScriptEnabled: false });

await browser.close();
await server.close();
rmSync(outDir, { recursive: true, force: true });
const failed = results.filter((r) => !r.ok).length;
console.log(`\n${results.length} tes JS, ${results.length - failed} lulus, ${failed} gagal`);
process.exit(failed ? 1 : 0);
