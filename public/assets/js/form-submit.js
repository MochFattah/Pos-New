// Mencegah kirim ganda: tombol kirim diberi aria-busy (teks dipertahankan; CSS menampilkan spinner) dan kirim kedua
// diblokir. Tombol tidak di-disable agar nama/nilainya tetap ikut terkirim. Pulih saat halaman dipulihkan dari bfcache.
// Halaman galat validasi: fokus ke ringkasan galat; tautan di ringkasan memfokuskan field-nya.
import { focusableIn, prefersReducedMotion } from './focusable.js';

const busyForms = new Set();

function release() {
    for (const form of busyForms) {
        form.removeAttribute('aria-busy');
        for (const button of form.querySelectorAll('[aria-busy="true"]')) {
            button.removeAttribute('aria-busy');
            button.removeAttribute('aria-disabled');
        }
    }
    busyForms.clear();
}

function initDoubleSubmit() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        // Kirim ke jendela/bingkai lain (target form atau formtarget tombol selain _self) tidak menavigasi halaman ini: jangan kunci.
        const target = (submitter?.getAttribute('formtarget') ?? form.getAttribute('target') ?? '').toLowerCase();
        if ((target !== '' && target !== '_self') || form.hasAttribute('data-no-busy')) return;
        if (busyForms.has(form)) {
            event.preventDefault();
            return;
        }
        busyForms.add(form);
        form.setAttribute('aria-busy', 'true');
        const button = submitter ?? form.querySelector('button[type="submit"], input[type="submit"]');
        if (button) {
            button.setAttribute('aria-busy', 'true');
            button.setAttribute('aria-disabled', 'true');
        }
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted || busyForms.size > 0) release();
    });
}

function focusField(id) {
    const target = document.getElementById(id);
    if (!target) return false;
    const control = target.matches('input, select, textarea, button') ? target : focusableIn(target)[0];
    if (!control) return false;
    control.scrollIntoView({ block: 'center', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
    control.focus({ preventScroll: true });
    return true;
}

function initErrorSummary() {
    const summary = document.querySelector('[data-error-summary]');
    if (!summary) return;
    summary.focus();
    summary.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;
        if (!link) return;
        let id = link.getAttribute('href').slice(1);
        try { id = decodeURIComponent(id); } catch { /* href tak valid: pakai apa adanya */ }
        if (focusField(id)) event.preventDefault();
    });
}

export function initFormSubmit() {
    initDoubleSubmit();
    initErrorSummary();
}
