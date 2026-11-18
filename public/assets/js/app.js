// Titik masuk modul ES. Tema dimuat terpisah (theme.js, sinkron di <head>) agar tidak berkedip.
import { initDrawer } from './drawer.js';
import { initDialog } from './dialog.js';
import { initToast } from './toast.js';
import { initFormSubmit } from './form-submit.js';

function init() {
    initDialog();
    initFormSubmit();
    initDrawer();
    initToast();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
