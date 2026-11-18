// Toast: wilayah aria-live (layout app: [data-toast-region]). Otomatis hilang >= 5 detik, berhenti saat hover/fokus,
// ada tombol tutup. Pesan flash sukses dari server ([data-flash="notice"]) dipindah menjadi toast; galat tetap inline.
const DISMISS_MS = 6000;
const TOAST_CLASS = { success: 'toast-success', warning: 'toast-warning', danger: 'toast-danger', info: '' };

let region = null;

/** Menampilkan toast. @param {'success'|'warning'|'danger'|'info'} variant */
export function showToast(message, variant = 'info') {
    if (!region) return null;
    const toast = document.createElement('div');
    toast.classList.add('toast');
    if (TOAST_CLASS[variant]) toast.classList.add(TOAST_CLASS[variant]);
    const text = document.createElement('p');
    text.textContent = message;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-tertiary';
    button.textContent = 'Tutup';
    button.setAttribute('aria-label', 'Tutup pemberitahuan');
    toast.append(text, button);

    let timer = 0;
    const remove = () => {
        window.clearTimeout(timer);
        toast.remove();
    };
    const start = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(remove, DISMISS_MS);
    };
    const pause = () => window.clearTimeout(timer);
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', start);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', start);
    button.addEventListener('click', remove);
    region.append(toast);
    start();
    return toast;
}

export function initToast() {
    region = document.querySelector('[data-toast-region]');
    if (!region) return;
    for (const flash of document.querySelectorAll('[data-flash="notice"]')) {
        const message = flash.textContent.trim();
        if (message === '') continue;
        flash.hidden = true;
        showToast(message, 'success');
    }
}
