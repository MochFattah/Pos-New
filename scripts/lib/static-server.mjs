// Server statis kecil untuk HTML hasil scripts/dump-pages.php + aset dari public/assets (CSS/JS/font apa adanya).
// Bukan aplikasi nyata: GET dilayani dari manifest; POST yang tak ada di manifest dialihkan 303 kembali ke halaman asal.
import { createServer } from 'node:http';
import { readFileSync, existsSync, statSync } from 'node:fs';
import { join, normalize, extname } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const MIME = { '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png', '.txt': 'text/plain; charset=utf-8' };

/** Menjalankan scripts/dump-pages.php (perlu `php`). */
export function dumpPages(outDir) {
    execFileSync('php', [join(root, 'scripts/dump-pages.php'), outDir], { stdio: ['ignore', 'pipe', 'inherit'] });
}

/**
 * @param {{outDir: string, postDelayMs?: number}} options
 * @returns {Promise<{url: string, log: {method: string, path: string, status: number}[], close: () => Promise<void>}>}
 */
export function startServer({ outDir, postDelayMs = 0 }) {
    const manifest = JSON.parse(readFileSync(join(outDir, 'manifest.json'), 'utf8'));
    const log = [];
    const headers = (type) => ({
        'Content-Type': type,
        'Content-Security-Policy': manifest.csp,
        'X-Content-Type-Options': 'nosniff',
        'Cache-Control': 'no-store',
    });
    const html = (res, entry, status = entry.status) => {
        res.writeHead(status, headers('text/html; charset=utf-8'));
        res.end(readFileSync(join(outDir, entry.file)));
    };

    const server = createServer((req, res) => {
        const url = new URL(req.url, 'http://x');
        const done = (status) => log.push({ method: req.method, path: url.pathname + url.search, status });
        const respond = () => {
            if (req.method === 'GET' && url.pathname.startsWith('/assets/')) {
                const file = normalize(join(root, 'public', url.pathname));
                if (!file.startsWith(join(root, 'public/assets')) || !existsSync(file) || !statSync(file).isFile()) {
                    res.writeHead(404);
                    res.end('tidak ada');
                    return done(404);
                }
                res.writeHead(200, { 'Content-Type': MIME[extname(file)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
                res.end(readFileSync(file));
                return done(200);
            }
            if (req.method === 'GET') {
                const entry = manifest.pages[`GET ${url.pathname}${url.search}`] ?? manifest.pages[`GET ${url.pathname}`] ?? manifest.pages[`NAME ${url.pathname}`];
                if (entry) {
                    html(res, entry);
                    return done(entry.status);
                }
                html(res, manifest.pages['NAME /__page/error-404'], 404);
                return done(404);
            }
            if (req.method === 'POST') {
                req.resume();
                const entry = manifest.pages[`POST ${url.pathname}`];
                if (entry) {
                    html(res, entry);
                    return done(entry.status);
                }
                const ref = req.headers.referer ? new URL(req.headers.referer) : null;
                res.writeHead(303, { Location: ref ? ref.pathname + (ref.search || '?flash=1') : '/beranda' });
                res.end();
                return done(303);
            }
            res.writeHead(405);
            res.end();
            done(405);
        };
        if (req.method === 'POST' && postDelayMs > 0) setTimeout(respond, postDelayMs);
        else respond();
    });

    return new Promise((resolve) => {
        server.listen(0, '127.0.0.1', () => {
            const { port } = server.address();
            resolve({ url: `http://127.0.0.1:${port}`, log, close: () => new Promise((r) => server.close(() => r())) });
        });
    });
}
