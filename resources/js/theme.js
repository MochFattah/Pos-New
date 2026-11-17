// Tema: ikuti sistem (CSS prefers-color-scheme) kecuali pengguna memilih manual; pilihan disimpan di localStorage.
// Dimuat sinkron di <head> agar data-theme terpasang sebelum render pertama (tanpa FOUC). Tanpa dependensi, tanpa global.
const KEY = 'pos-theme';

function stored() {
    try {
        const v = localStorage.getItem(KEY);
        return v === 'light' || v === 'dark' ? v : null;
    } catch {
        return null;
    }
}

function systemTheme() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function apply(theme) {
    document.documentElement.setAttribute('data-theme', theme);
}

const initial = stored();
if (initial) apply(initial);

document.addEventListener('DOMContentLoaded', () => {
    for (const button of document.querySelectorAll('[data-theme-toggle]')) {
        const sync = () => {
            const dark = (document.documentElement.getAttribute('data-theme') || systemTheme()) === 'dark';
            button.setAttribute('aria-pressed', String(dark));
        };
        button.hidden = false;
        sync();
        button.addEventListener('click', () => {
            const next = (document.documentElement.getAttribute('data-theme') || systemTheme()) === 'dark' ? 'light' : 'dark';
            apply(next);
            try { localStorage.setItem(KEY, next); } catch { /* penyimpanan diblokir: tema berlaku untuk sesi ini saja */ }
            for (const other of document.querySelectorAll('[data-theme-toggle]')) {
                other.setAttribute('aria-pressed', String(next === 'dark'));
            }
        });
    }
});
