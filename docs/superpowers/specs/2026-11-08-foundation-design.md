# Spesifikasi Desain — Sub-proyek 1: Fondasi

**Tanggal:** 2026-11-08
**Status:** Menunggu tinjauan pemilik proyek
**Sumber kebenaran:** `PRD.md` (v1.3), `DESAIN.md`, `.cursorrules`
**Cakupan:** Sub-proyek 1 dari 8 (lihat bagian 2). Modul lain tidak disentuh.

---

## 1. Tujuan dan kriteria keberhasilan

Membangun dasar teknis dan UI yang akan dipakai seluruh modul POS Retail berikutnya, dapat dijalankan di shared hosting cPanel, dan terisolasi per tenant.

Fondasi dianggap selesai jika:

1. Pengunjung dapat mendaftar, memverifikasi email, lalu login sebagai Owner Tenant.
2. Setiap tenant terisolasi: pengguna tenant A tidak dapat membaca atau mengubah data tenant B (dibuktikan tes).
3. RBAC berfungsi di server: permission granular, role per tenant, dan akses cabang.
4. Owner dapat mengelola cabang, pengguna, role/izin, dan pengaturan bisnis.
5. Super Admin dapat login di jalur platform terpisah.
6. Akses operasional diblokir ketika demo berakhir, dan data tetap tersimpan.
7. Seluruh UI memakai token `DESAIN.md` (terang dan gelap), responsif, dan memenuhi target aksesibilitas yang bisa diperiksa otomatis.
8. Aplikasi dapat dipasang di cPanel tanpa SSH penuh.

## 2. Dekomposisi proyek

| # | Sub-proyek | Status |
|---|---|---|
| **1** | **Fondasi** (dokumen ini) | Desain |
| 2 | Katalog dan inventori | Belum |
| 3 | POS dan shift kasir (termasuk absensi) | Belum |
| 4 | Offline PWA dan scanner barcode | Belum |
| 5 | Pembelian, pelanggan, transfer, retur | Belum |
| 6 | Akuntansi dan keuangan | Belum |
| 7 | Laporan dan dashboard | Belum |
| 8 | Super Admin, langganan, Midtrans, tiket bantuan | Belum |

Setiap sub-proyek memiliki spesifikasi, rencana, dan verifikasi sendiri.

## 3. Keputusan dan sumbernya

### 3.1 Keputusan pemilik proyek (sesi ini)

| Keputusan | Pilihan |
|---|---|
| Sub-proyek pertama | Fondasi saja |
| Identitas visual | Baseline `DESAIN.md`: palet usulan dan font Inter, nama sementara "POS Retail", tanpa logo |
| Hosting | Shared hosting / cPanel |
| Email | SMTP, dengan driver log untuk development |
| Pendekatan inti | A: inti PHP tanpa dependensi |
| Jenis bisnis saat registrasi | Teks bebas |
| Verifikasi email | **Wajib** (lihat deviasi 3.3) |
| Halaman awal semua role | Beranda ringkas |
| Asumsi teknis 1–4 pada bagian 3.2 | Disetujui |

### 3.2 Asumsi teknis (bukan keputusan bisnis PRD; dapat diubah di `config/security.php` atau `config/app.php`)

1. Email pengguna **unik secara global**; login cukup dengan email.
2. Batas login: 5 percobaan gagal per 15 menit per kombinasi email dan IP. Token reset password berlaku 60 menit. Token verifikasi email berlaku 24 jam.
3. Timeout idle sesi web: 8 jam. Tidak berlaku untuk sesi POS offline (OQ-16 tetap terbuka).
4. Durasi demo awal 1 hari, disimpan sebagai data referensi `demo_settings`.
5. Default pengaturan tenant: zona waktu `Asia/Jakarta`, mata uang `IDR`, format angka Indonesia. Owner dapat mengubahnya.
6. Super Admin pertama dibuat lewat skrip CLI `scripts/create-platform-admin.php`, bukan lewat form publik.

### 3.3 Deviasi dari PRD v1.3 yang perlu dicatat

PRD 6.1 menyebut "sistem memvalidasi email" tanpa mewajibkan tautan verifikasi, dan langkah 7 mengaktifkan demo langsung setelah registrasi. Pemilik proyek memilih **verifikasi email wajib**. Konsekuensinya:

- Akun baru berstatus `pending_verification` dan tidak dapat login sebelum tautan diklik.
- **Demo dimulai saat email terverifikasi**, bukan saat form registrasi dikirim, agar jam demo 1 hari tidak habis sebelum pengguna sempat masuk. Ini asumsi saya yang mengikuti keputusan verifikasi dan perlu dikonfirmasi.
- Tindak lanjut: catat sebagai revisi PRD (v1.4) sebelum sub-proyek ini dianggap selesai (PRD bagian "Penutup" mewajibkan perubahan alur dicatat).

## 4. Arsitektur

### 4.1 Pola

Modular monolith PHP Native 8.3, MySQL 8, tanpa framework backend. Alur request:

`Request → Router → Middleware → Controller → Validator/Policy → Service → Repository → MySQL`

- Controller tipis. Aturan bisnis hanya di Service. Query hanya di Repository (PDO prepared statements).
- View tidak berisi SQL maupun aturan bisnis. Output di-escape sesuai konteks.
- Integrasi eksternal (SMTP) berada di adapter di balik interface (`MailerInterface`).

### 4.2 Inti tanpa dependensi

Alasan: `composer install` tidak dapat dijalankan di lingkungan pengembangan ini, dan cPanel tanpa SSH tidak cocok dengan langkah build PHP. Konsekuensi:

- Autoloader PSR-4 kecil (`bootstrap/autoload.php`) agar aplikasi berjalan tanpa `vendor/`. `composer.json` tetap disediakan untuk dev tools dan library di masa depan.
- Klien SMTP ditulis sendiri di balik `MailerInterface` (STARTTLS dan AUTH LOGIN). **Belum dapat diuji terhadap server SMTP sungguhan di sini**; PHPMailer dapat dipasang kemudian tanpa mengubah modul lain.

### 4.3 Struktur folder (mengikuti `.cursorrules` bagian 3)

```text
pos-retail/
├── app/
│   ├── Core/            Application, Router, Request, Response, Database, Exceptions,
│   │                    Middleware, Validation, Authorization, Mail, Support
│   ├── Modules/
│   │   ├── Auth/  Tenants/  Branches/  Users/  Roles/  AuditLogs/
│   │   └── Platform/    (login dan kerangka Super Admin)
│   └── Shared/          Contracts, Enums, DTO
├── bootstrap/           app.php, autoload.php
├── config/              app.php, database.php, security.php, mail.php
├── database/            migrations/, seeders/, schema/
├── public/              index.php, .htaccess, assets/, manifest.webmanifest
├── resources/           views/, css/ (tokens.css, app.css), js/ (ES Modules)
├── routes/              web.php, platform.php
├── storage/             logs/, sessions/, uploads/, cache/  (di luar jangkauan web)
├── tests/               Unit/, Integration/, Feature/, Support/
├── scripts/             migrate.php, create-platform-admin.php, build-css.mjs
├── docs/                architecture/, database/, deployment/, decisions/
└── .env.example  composer.json  package.json
```

Folder modul lain (Products, POS, dst.) **tidak** dibuat sampai sub-proyeknya dikerjakan.

### 4.4 Deployment cPanel

- Document root idealnya diarahkan ke `public/`. Jika tidak dapat diubah, disediakan `.htaccess` di root yang meneruskan permintaan ke `public/` dan memblokir akses ke `app/`, `storage/`, `.env`, dan `vendor/`. Petunjuk keduanya ada di `docs/deployment/`.
- Migrasi: `scripts/migrate.php` (CLI/cron) dan **halaman installer satu kali** yang dilindungi token dari `.env` dan file lock. Setelah instalasi selesai, installer dinonaktifkan permanen.
- CSS hasil build di-commit (`public/assets/css/app.css`), jadi server tidak memerlukan Node.
- Font Inter disimpan lokal (tanpa CDN). Jika file font tidak tersedia saat build, fallback ke `system-ui` dan hal ini dilaporkan.

## 5. Database (MySQL 8, InnoDB, utf8mb4)

Konvensi: nama `snake_case`, uang `DECIMAL`, waktu UTC (konversi ke zona waktu tenant saat tampil), indeks pada setiap foreign key dan kolom filter. Semua tabel tenant memuat `tenant_id`.

| Tabel | Kolom utama |
|---|---|
| `platform_users` | id, name, email (unik), password_hash, status, last_login_at, timestamps |
| `tenants` | id, uuid, name, status (`pending_verification`/`active`/`suspended`), timestamps |
| `tenant_profiles` | tenant_id (PK, FK), business_type (teks bebas), address, phone, logo_path |
| `tenant_settings` | tenant_id (PK, FK), timezone, currency_code, number_format |
| `branches` | id, tenant_id, code, name, address, phone, is_main, status, timestamps; unik (tenant_id, code) |
| `users` | id, tenant_id, name, email (unik global), phone, password_hash, status, email_verified_at, is_owner, last_login_at, timestamps |
| `roles` | id, tenant_id, slug, name, is_system; unik (tenant_id, slug) |
| `permissions` | id, code (unik), module, description |
| `role_permissions` | role_id, permission_id |
| `user_roles` | user_id, role_id |
| `user_branch_access` | user_id, branch_id, tenant_id |
| `demo_settings` | id, is_enabled, duration_hours (default 24) |
| `tenant_demos` | id, tenant_id, starts_at, ends_at, source (`registration`/`grant`/`extension`), granted_by_platform_user_id (nullable), reason, created_at |
| `email_verifications` | id, user_id, token_hash, expires_at, used_at, created_at |
| `password_resets` | id, user_id, token_hash, expires_at, used_at, ip, created_at |
| `login_attempts` | id, identifier_hash, ip, succeeded, attempted_at |
| `audit_logs` | id, tenant_id (nullable), actor_type, actor_id, action, entity_type, entity_id, metadata (JSON), ip, request_id, created_at |

Token disimpan hanya dalam bentuk hash. Tabel domain lain ditambah migrasi sub-proyek berikutnya.

## 6. RBAC dan isolasi tenant

- **Role per tenant.** Saat registrasi dibuat 8 role tenant dari PRD 4.3 (Owner Tenant, Manager, Supervisor, Kasir, Staf Gudang, Staf Pembelian, Staf Keuangan, Akuntan). Super Admin berada di jalur platform terpisah (`platform_users`).
- **Owner** otomatis memiliki seluruh permission tenant dan tidak dapat dihapus atau diturunkan. Role lain dibuat **kosong**; Owner mengatur izinnya. Pemetaan default tidak dikarang karena PRD tidak menetapkannya.
- **Katalog permission fondasi:** `branches.view`, `branches.manage`, `users.view`, `users.manage`, `roles.view`, `roles.manage`, `tenant_settings.view`, `tenant_settings.manage`, `audit_logs.view`. Setiap sub-proyek menambah permission miliknya lewat migrasi.
- **Pemeriksaan** dilakukan di server oleh `PolicyService` (role, permission, tenant, akses cabang). Menu yang disembunyikan hanyalah kenyamanan UI.
- **Tenant context** ditentukan server dari sesi. Nilai `tenant_id` dari browser tidak pernah dipercaya. Repository tenant wajib menyertakan scope tenant; akses lintas tenant ditolak dan dicatat.

## 7. Autentikasi dan sesi

- **Registrasi:** data sesuai PRD 6.1 (nama Owner, email, telepon/WhatsApp, password, nama bisnis, jenis bisnis, alamat). Satu transaksi database membuat tenant, profil, pengaturan, cabang utama, user Owner, 8 role, dan mengirim email verifikasi. Status awal `pending_verification`.
- **Verifikasi email:** tautan sekali pakai (24 jam). Setelah berhasil: status user/tenant menjadi `active`, `tenant_demos` dibuat (durasi dari `demo_settings`), dan pengguna diarahkan ke Login. Pengiriman ulang dibatasi laju.
- **Login:** `password_hash`/`password_verify`, session ID diregenerasi setelah login, cookie `HttpOnly`, `Secure`, `SameSite=Lax`, session disimpan di `storage/sessions`. Pesan error tidak membocorkan apakah email terdaftar. Rate limit memakai `login_attempts`.
- **Reset password:** token acak sekali pakai (60 menit), respons selalu netral, semua sesi aktif pengguna dicabut setelah password diganti.
- **CSRF:** token pada semua request pengubah state. **Header keamanan** (CSP, X-Frame-Options, Referrer-Policy, dsb.) di-set oleh middleware.
- **Gerbang akses:** middleware memblokir akses operasional ketika tidak ada jendela demo/langganan yang berlaku, menampilkan halaman "demo berakhir" dengan kanal dukungan (email/tiket). Data tidak dihapus. Pemulihan lewat pembayaran atau persetujuan Super Admin dibangun di sub-proyek 8.
- **Audit log:** login, registrasi, verifikasi, reset password, perubahan role/izin, pengguna, cabang, dan pengaturan. Password dan token tidak pernah dicatat.

## 8. UI dan sistem desain

### 8.1 Halaman

| Area | Halaman |
|---|---|
| Publik | Landing sederhana (tanpa harga), Login, Daftar, Verifikasi email, Lupa password, Reset password |
| Tenant | Kerangka aplikasi, Beranda/Dashboard awal, Cabang, Karyawan dan Pengguna, Role dan izin, Pengaturan bisnis (profil, logo, zona waktu, mata uang, format angka) |
| Platform | `/platform/login`, kerangka Dashboard Platform (isi di sub-proyek 8) |
| Sistem | 403, 404, 500, demo/langganan berakhir |

Aturan keputusan: Dashboard hanya berisi sambutan, status demo, dan empty state (metrik dashboard belum ditetapkan, OQ-07). Menu hanya menampilkan modul yang sudah dibangun dan lolos permission. Landing tanpa harga dan tanpa section pemasaran tambahan (OQ-06 terbuka). Semua role non-Owner mendarat di beranda ringkas (OQ-05 baseline).

### 8.2 Token dan komponen

- `resources/css/tokens.css` memuat seluruh token `DESAIN.md` (terang dan gelap), dipetakan ke tema Tailwind 4. Tema mengikuti sistem dan dapat diganti manual, disimpan di browser (baseline OQ-04).
- CSS dikompilasi dengan Tailwind 4.x melalui `scripts/build-css.mjs` dan `package.json` (CLI standar untuk pengembangan lokal pemilik proyek).
- Komponen reusable beserta state lengkap (default, hover, focus-visible, active, disabled, loading, error, empty): tombol, input/form, badge status (teks + ikon), tabel, dialog (`<dialog>` native), toast, skeleton, empty state, navigasi.
- Radius, spacing, dan skala tipografi mengikuti `DESAIN.md` bagian 3–4.

### 8.3 Responsif dan aksesibilitas

- Sidebar pada desktop, drawer pada layar kecil. Padding konten 24/20/16 px. Breakpoint bawaan Tailwind (640/768/1024/1280) didokumentasikan.
- Skip link, focus ring jelas di kedua tema, target sentuh minimal 44 px, label terhubung programatis, error terhubung ke field (`aria-describedby`), `aria-live` untuk toast/status, `prefers-reduced-motion` dihormati, status tidak hanya berbasis warna.
- Target kontras WCAG 2.2 AA diperiksa dengan skrip untuk setiap pasangan token. Penetapan WCAG 2.2 AA sebagai kriteria penerimaan formal (OQ-20) tetap menunggu persetujuan; hasil pemeriksaan dilaporkan sebagai data.
- JavaScript: ES Modules per fitur, tanpa inline JS, tanpa variabel global.

## 9. Strategi verifikasi

| Kebutuhan | Cara | Dapat dijalankan di lingkungan ini |
|---|---|---|
| Sintaks PHP | `php -l` seluruh file | Ya |
| Logika inti (router, validator, CSRF, RBAC, tenant scope, rate limit, token) | Skrip uji mandiri tanpa dependensi, SQLite in-memory sebagai double untuk repository | Ya |
| SQL MySQL 8 sungguhan | Tidak ada server MySQL | **Tidak.** Skema ditulis cermat dan ditandai "belum dijalankan di MySQL"; pemilik proyek menjalankan `migrate.php` dan melaporkan hasilnya |
| CSS Tailwind | Kompilasi melalui API Node lokal | Ya |
| Tampilan dan responsif | Tangkapan layar Chromium (desktop, tablet, ponsel; terang dan gelap) | Ya |
| Kontras warna | Skrip hitung rasio kontras | Ya |
| SMTP nyata | Tidak ada server SMTP | **Tidak.** Driver log diuji; klien SMTP belum diuji end-to-end |
| PHPUnit, PHPStan, PHP-CS-Fixer | Tidak dapat dipasang | **Tidak.** Konfigurasi disediakan; menunggu dijalankan oleh pemilik proyek |

Setiap laporan tahap akan memisahkan secara jelas hal yang sudah diverifikasi dan yang belum.

## 10. Open Questions

**Diputuskan atau diberi baseline sementara untuk fondasi:** OQ-01 (nama/logo sementara), OQ-02 dan OQ-03 (baseline palet dan Inter), OQ-04 (tema mengikuti sistem + toggle), OQ-05 (beranda ringkas), OQ-06 (tanpa harga publik), OQ-07 (tanpa metrik dashboard), OQ-19 (hanya Bahasa Indonesia).

**Tidak relevan untuk fondasi dan tetap terbuka (tidak diasumsikan):** OQ-08, 09, 10, 11, 12, 13, 14, 15, 16, 17, 18, 20, serta detail kuota/harga paket, COA, costing, retensi data, SLA, backup, dan target pemulihan.

## 11. Di luar cakupan sub-proyek 1

Produk, inventori, POS, shift, offline/PWA service worker, scanner, pembelian, pelanggan, transfer, retur, kas, akuntansi, laporan, langganan, Midtrans, tiket bantuan, pengumuman, dan monitoring. Tabel `subscriptions` dan `billing_*` tidak dibuat; gerbang akses fondasi hanya membaca `tenant_demos`.
