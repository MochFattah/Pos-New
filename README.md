# POS Retail

Aplikasi POS Retail multi-tenant (SaaS) berbasis PHP native. Repositori ini berisi sub-proyek 1 (fondasi): registrasi bisnis dan verifikasi email, login, tenant/cabang/pengguna/role dengan RBAC, pengaturan bisnis dan logo, demo 24 jam, area Super Admin dasar, audit log, penginstal, dan sistem desain UI. Modul bisnis (produk, stok, kasir, dan seterusnya) belum ada.

Sumber kebenaran: `PRD.md` (kebutuhan), `DESAIN.md` (visual), `.cursorrules` (teknis), `docs/superpowers/specs/2026-11-08-foundation-design.md` (spesifikasi fondasi).

## Persyaratan

- PHP 8.3+ dengan ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`
- MySQL 8.0+
- Apache (`mod_rewrite`) atau Nginx; lihat `docs/deployment/`
- Node.js 22 hanya untuk build CSS, tes JS, dan tangkapan layar (tidak diperlukan di server). `npm install` memasang `playwright` (devDependency); jalankan `npx playwright install chromium` sekali. Tanpa itu tes JS dilewati; setel `POS_REQUIRE_JS=1` (mis. di CI) agar dilewati dianggap GAGAL
- Tanpa Composer dan tanpa dependensi runtime (`docs/decisions/0001-zero-dependency-core.md`)

## Pengembangan lokal

```
cp .env.example .env        # isi DB_*; untuk lokal set APP_URL=http://localhost:8000, SESSION_SECURE=auto
php scripts/migrate.php     # menerapkan database/migrations ke MySQL
php -S localhost:8000 -t public
php scripts/create-platform-admin.php "Nama Admin" email@domain.com
```

`MAIL_DRIVER=log` (hanya untuk pengembangan; di `APP_ENV=production` ditolak kecuali `MAIL_ALLOW_LOG=true`) menulis email ke `storage/logs/mail.log`, jadi tautan verifikasi dapat diambil dari sana.

## Pengujian

```
php tests/run.php            # seluruh tes (runner sendiri; SQLite in-memory sebagai double DB)
php tests/run.php Install    # filter menurut nama
node scripts/build-css.mjs   # bangun public/assets/css/app.css (di-commit)
node scripts/check-contrast.mjs
node scripts/test-js.mjs
node scripts/screenshots.mjs
```

Tes JS memakai Chromium lewat Playwright dan dilewati bila tidak tersedia. `phpunit.xml`, `phpstan.neon`, dan `.php-cs-fixer.php` disediakan, tetapi belum pernah dijalankan.

## Struktur

| Path | Isi |
|---|---|
| `app/Core` | Router, request/response, view, validator, session, CSRF, RBAC, migrator, mailer |
| `app/Modules` | Auth, Tenants, Branches, Users, Roles, Platform, AuditLogs, Install |
| `bootstrap/` | Autoload dan perakitan layanan (`app.php`) |
| `config/` | Konfigurasi dari `.env` |
| `database/` | Migrasi MySQL dan pemuat seeder; lihat `docs/database/README.md` |
| `public/` | Document root: `index.php`, aset, CSS hasil build |
| `resources/views` | Templat server-side |
| `routes/` | Definisi rute (web, platform, install) |
| `scripts/` | Migrasi, pembuat Super Admin, build CSS, kontras, tes JS, tangkapan layar |
| `docs/` | Deploy, keputusan arsitektur, desain sistem, spesifikasi dan rencana |

## Deployment

- cPanel: `docs/deployment/cpanel.md`
- Nginx: `docs/deployment/nginx.md`

Ringkas: arahkan document root ke `public/`, buat `.env`, buat database, isi `INSTALL_TOKEN`, buka `/install`, buat Super Admin lewat CLI, lalu kosongkan `INSTALL_TOKEN`. Backup database sebelum setiap migrasi.

## Status verifikasi

Diverifikasi di lingkungan pengembangan: tes otomatis, sintaks PHP, build CSS, kontras warna, tes JS di Chromium. Belum diverifikasi: DDL pada MySQL nyata, SMTP/TLS nyata, PHPUnit/PHPStan/PHP-CS-Fixer, peramban dan perangkat nyata, penginstal di Apache/cPanel nyata.
