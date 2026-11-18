// Pembantu fokus dan gerak bersama modul UI. Tanpa dependensi, tanpa global.
const FOCUSABLE = 'a[href], button, input, select, textarea, summary, [tabindex]:not([tabindex="-1"])';

function visible(el) {
    return !el.hidden && !el.closest('[hidden], [inert]') && (el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0);
}

/** Elemen yang bisa difokuskan keyboard di dalam $root, sesuai urutan dokumen. */
export function focusableIn(root) {
    return [...root.querySelectorAll(FOCUSABLE)].filter((el) => !el.disabled && el.getAttribute('aria-hidden') !== 'true' && visible(el));
}

export function prefersReducedMotion() {
    return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Fokus ke $el bila masih ada di dokumen (pemicu bisa hilang setelah render ulang). */
export function restoreFocus(el) {
    if (el instanceof HTMLElement && el.isConnected) el.focus();
}
