// Konfirmasi tindakan berisiko dengan <dialog> bawaan.
// Markup (components/confirm-form): form[data-confirm-dialog="<id dialog>"] tetap form POST biasa tanpa JS.
// Dengan JS, kirim form dicegat, dialog dibuka modal (fokus awal: Batal), Esc/Batal/klik latar menutup,
// fokus kembali ke pemicu. Pemicu umum lain: [data-dialog-target="<id>"] membuka dialog tanpa form.
import { restoreFocus } from './focusable.js';

function supported(dialog) {
    return dialog instanceof HTMLDialogElement && typeof dialog.showModal === 'function';
}

function openDialog(dialog, opener) {
    dialog.returnValue = '';
    dialog.showModal();
    dialog.addEventListener('close', () => restoreFocus(opener), { once: true });
}

export function initDialog() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirmDialog) return;
        // Pengiriman dari tombol konfirmasi di dalam dialog: biarkan lanjut.
        if (event.submitter instanceof HTMLElement && event.submitter.hasAttribute('data-dialog-confirm')) return;
        const dialog = document.getElementById(form.dataset.confirmDialog);
        if (!supported(dialog)) return; // tanpa dukungan: form terkirim langsung
        event.preventDefault();
        const trigger = event.submitter instanceof HTMLElement ? event.submitter : form.querySelector('button[type="submit"]');
        openDialog(dialog, trigger);
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const target = event.target.closest('[data-dialog-target]');
        if (target) {
            const dialog = document.getElementById(target.getAttribute('data-dialog-target'));
            if (supported(dialog)) {
                event.preventDefault();
                openDialog(dialog, target);
            }
            return;
        }
        const closer = event.target.closest('[data-dialog-close]');
        if (closer) {
            closer.closest('dialog')?.close();
            return;
        }
        // Klik pada latar (backdrop) = di luar kotak dialog.
        const dialog = event.target;
        if (dialog instanceof HTMLDialogElement && dialog.open) {
            const r = dialog.getBoundingClientRect();
            const outside = event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom;
            if (outside) dialog.close();
        }
    });
}
