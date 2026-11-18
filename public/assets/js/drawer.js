// Drawer navigasi mobile. Tanpa JS, sidebar tetap daftar tautan biasa yang terbuka lewat :target.
// Dengan JS: tombol buka/tutup ber-aria-expanded, perangkap fokus, Esc, klik scrim, latar inert, fokus kembali ke pemicu.
import { focusableIn, restoreFocus } from './focusable.js';

const MOBILE = '(max-width: 1023.98px)';

/** Dengan JS, tautan fallback diganti <button> sungguhan (Spasi dan Enter bekerja; tanpa role buatan). Atribut dan anak dipindahkan apa adanya. */
function toButton(link) {
    const button = document.createElement('button');
    button.type = 'button';
    for (const { name, value } of link.attributes) {
        if (name !== 'href') button.setAttribute(name, value);
    }
    button.append(...link.childNodes);
    link.replaceWith(button);
    return button;
}

export function initDrawer() {
    const drawer = document.querySelector('[data-drawer]');
    const link = document.querySelector('[data-drawer-open]');
    if (!drawer || !link) return;
    const opener = link instanceof HTMLAnchorElement ? toButton(link) : link;
    const scrim = document.querySelector('[data-drawer-scrim]');
    const closers = [...document.querySelectorAll('[data-drawer-close]')].map((el) => (el instanceof HTMLAnchorElement ? toButton(el) : el));
    const background = [...document.querySelectorAll('.topbar, .app-shell__main')];
    const mq = window.matchMedia(MOBILE);
    let isOpen = false;

    // Muat ulang dengan #navigasi (fallback tanpa JS) akan membuka :target; hapus agar JS yang memegang kendali.
    if (location.hash === '#' + drawer.id) history.replaceState(null, '', location.pathname + location.search);

    opener.setAttribute('aria-expanded', 'false');

    function sync() {
        // Mobile & tertutup: sidebar di luar layar tidak boleh bisa difokuskan. Desktop: sidebar selalu tampil.
        drawer.inert = mq.matches && !isOpen;
        for (const el of background) el.inert = mq.matches && isOpen;
        drawer.dataset.open = String(mq.matches && isOpen);
        if (scrim) scrim.hidden = !(mq.matches && isOpen);
        opener.setAttribute('aria-expanded', String(mq.matches && isOpen));
    }

    function open() {
        if (!mq.matches || isOpen) return;
        isOpen = true;
        sync();
        (focusableIn(drawer)[0] ?? drawer).focus();
    }

    function close({ restore = true } = {}) {
        if (!isOpen) return;
        isOpen = false;
        sync();
        if (restore) restoreFocus(opener);
    }

    opener.addEventListener('click', (event) => {
        if (!mq.matches) return;
        event.preventDefault();
        isOpen ? close() : open();
    });
    for (const closer of closers) {
        closer.addEventListener('click', (event) => {
            if (!mq.matches) return;
            event.preventDefault();
            close();
        });
    }
    scrim?.addEventListener('click', () => close());
    // Tautan menu memuat halaman baru; tidak perlu mengembalikan fokus.
    drawer.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('a.nav-item')) close({ restore: false });
    });

    document.addEventListener('keydown', (event) => {
        if (!isOpen) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        const items = focusableIn(drawer);
        if (items.length === 0) {
            event.preventDefault();
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        const active = document.activeElement;
        if (event.shiftKey && (active === first || !drawer.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !drawer.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    });

    mq.addEventListener('change', () => {
        if (!mq.matches) isOpen = false;
        sync();
    });
    sync();
}
