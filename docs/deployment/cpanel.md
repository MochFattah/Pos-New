# Deploy di cPanel (hosting bersama)

Status verifikasi: langkah di bawah ditulis dari kode dan konfigurasi proyek. Belum dicoba di cPanel/Apache nyata dan belum dijalankan pada MySQL nyata. Laporkan hasil nyata bila ada yang berbeda.

## 1. Prasyarat

| Kebutuhan | Keterangan |
|---|---|
| PHP 8.3 atau lebih baru | Pilih di MultiPHP Manager untuk domain ini. |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`. Aktifkan di Select PHP Version / MultiPHP INI Editor. Penginstal memeriksa keempatnya. |
| MySQL 8.0+ | InnoDB, utf8mb4. |
| Apache dengan `mod_rewrite` | Wajib. Tanpa itu `.htaccess` root menolak semua permintaan (gagal tertutup). |
| Node.js | Tidak diperlukan di server. CSS hasil build sudah di-commit. |
| Composer | Tidak diperlukan. Tidak ada dependensi runtime. |

## 2. Unggah berkas

1. Unggah seluruh isi proyek ke folder di luar `public_html` bila memungkinkan, misalnya `/home/AKUN/pos-retail/`. Tanpa folder `node_modules` dan `.git`.
2. Pilihan A (dianjurkan): ubah document root domain ke `/home/AKUN/pos-retail/public` (cPanel > Domains > Manage > Document Root). Kode, `.env`, dan `storage/` tidak terjangkau web sama sekali.
3. Pilihan B (bila document root tidak bisa diubah): letakkan seluruh proyek di dalam document root dan pakai `.htaccess` di akar proyek. Berkas itu memblokir `.env`, `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `storage/`, `tests/`, `scripts/`, `resources/`, `docs/`, `vendor/`, `node_modules/`, `*.md`, `composer.*`, `package*.json`, lalu meneruskan sisanya ke `public/`. Ini cadangan: hanya untuk domain di akar (bukan subfolder), dan perlindungannya bergantung pada `.htaccess` terbaca. Setelah selesai, buka `https://domain-anda/.env` dan `https://domain-anda/storage/logs/app.log`; keduanya harus menghasilkan 403 atau 404. Bila terbuka, jangan pakai pilihan ini.

## 3. Berkas `.env`

Penginstal tidak membuat `.env`. Salin `.env.example` menjadi `.env` di akar proyek (File Manager atau SSH), lalu isi:

| Variabel | Isi |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (hanya `true` yang mengaktifkan debug) |
| `APP_URL` | URL publik dengan `https://`, tanpa garis miring akhir. Dipakai untuk tautan email. |
| `APP_TIMEZONE` | Bawaan `Asia/Jakarta` |
| `SESSION_SECURE` | `true` (cookie Secure dan HSTS). Wajib `true` bila TLS diakhiri di proxy. Hosting harus memakai HTTPS. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Dari langkah 4. Di cPanel nama database dan pengguna biasanya berawalan nama akun. |
| `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_TIMEOUT`, `MAIL_EHLO_HOSTNAME`, `MAIL_ALLOW_LOG`, `MAIL_LOG_PATH` (hanya driver log) | Lihat bagian 8. |
| `INSTALL_TOKEN` | Isi string acak panjang (sekitar 32 karakter) hanya selama pemasangan; kosong = penginstal mati. |
| `TRUSTED_PROXIES` | Kosong (bawaan) bila situs diakses langsung. Bila di belakang CDN/proxy (mis. Cloudflare), isi IP/CIDR proxy dipisah koma agar IP klien dibaca dari `X-Forwarded-For`; tanpa ini semua pengguna tampak berbagi satu IP dan pembatas laju bisa mengunci semua orang. Lihat `docs/deployment/nginx.md`. |
| `SUPPORT_EMAIL` | Opsional; ditampilkan di halaman bantuan. |

Pembatas laju (opsional, sudah punya bawaan di `config/security.php`): `LOGIN_IP_MAX_ATTEMPTS`, `PLATFORM_LOGIN_MAX_ATTEMPTS`, `PLATFORM_LOGIN_WINDOW_SECONDS`, `PLATFORM_LOGIN_IP_MAX_ATTEMPTS`, `RESET_REQUEST_MAX_ATTEMPTS`, `RESET_REQUEST_WINDOW_SECONDS`, `REGISTRATION_MAX_ATTEMPTS`, `REGISTRATION_WINDOW_SECONDS`, `INVITATION_RESEND_MAX_ATTEMPTS`, `INVITATION_RESEND_WINDOW_SECONDS`, `INVITE_MAX_ATTEMPTS` (undangan baru per tenant per jendela, bawaan 20), `INVITE_WINDOW_SECONDS` (bawaan 3600), `INVITE_MAX_PENDING` (maksimum undangan belum diterima per tenant, bawaan 50), `INVITE_STALE_SECONDS` (umur minimum baris undangan basi sebelum emailnya boleh diklaim ulang, bawaan 86400). Percobaan login tenant dibatasi 5 per 15 menit per (email, IP) dan tidak dapat diubah lewat `.env`.

Atur izin `.env` ke `640` (atau `600` bila PHP berjalan sebagai pemilik akun). Jangan pernah meng-commit `.env`.

Catatan: nilai dengan spasi atau tanda `#` sebaiknya diberi tanda kutip. `Env` tidak mendukung komentar di akhir baris.

## 4. Database

1. cPanel > MySQL Databases: buat database dan pengguna, lalu tambahkan pengguna ke database.
2. Hak akses minimal untuk pemasangan dan upgrade: `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES`. Migrasi membuat tabel dan foreign key. Pemberian "ALL PRIVILEGES" pada database itu saja (bukan akun lain) juga wajar di hosting bersama.
3. Isi `DB_*` di `.env`.

## 5. Izin folder

| Path | Izin | Catatan |
|---|---|---|
| Folder proyek | `755` | Pemilik akun cPanel. |
| `.env` | `640` atau `600` | Tidak boleh dapat dibaca web. |
| `app/`, `config/`, `database/`, `routes/`, `resources/`, `bootstrap/` | `755` (berkas `644`) | Hanya dibaca. |
| `storage/` dan subfoldernya (`logs/`, `sessions/`, `cache/`, `uploads/`) | `750` atau `755`; berkas `640`/`644` | Harus dapat ditulis oleh PHP. Dibuat otomatis bila belum ada. Jangan `777`. |
| `storage/installed.lock` | `640` | Dibuat penginstal. Jangan dihapus kecuali ingin memasang ulang (lihat pemecahan masalah). |
| `public/` | `755` | Satu-satunya yang boleh dilayani web (pilihan A). |

## 6. Menjalankan penginstal

1. Pastikan `.env` terisi dan `INSTALL_TOKEN` tidak kosong.
2. Buka `https://domain-anda/install`. Halaman memeriksa PHP, ekstensi, `storage/`, `.env`, dan berkas migrasi, dengan pesan Indonesia untuk yang gagal.
3. Masukkan token (dikirim lewat formulir, bukan URL). Lima token salah dalam 15 menit dari satu IP mengunci sementara; ada juga batas agregat.
4. Penginstal menjalankan migrasi, mengisi data referensi (permission dan pengaturan demo), lalu menulis `storage/installed.lock`. Setelah itu `/install` dan semua `/install*` memberi 404 yang sama dengan halaman yang tidak ada.
5. Kosongkan `INSTALL_TOKEN` di `.env` (dianjurkan).

Bila gagal, halaman hanya menampilkan pesan umum; rincian teknis (tanpa kata sandi) ada di `storage/logs/install.log`. Migrasi MySQL tidak transaksional (DDL ter-commit otomatis), jadi kegagalan di tengah dapat meninggalkan tabel sebagian. Pada percobaan ulang, jalankan dengan database kosong (hapus tabel yang tertinggal) atau perbaiki sesuai log.

## 7. Super Admin pertama

Penginstal tidak membuat Super Admin. Buat lewat terminal:

```
php scripts/create-platform-admin.php "Nama Admin" email@domain.com
```

Kata sandi diminta lewat prompt (tidak lewat argumen) dan dikonfirmasi dua kali. Skrip hanya jalan di CLI.

Keterbatasan: bila hosting tidak punya SSH, gunakan Terminal di cPanel bila tersedia, atau minta penyedia hosting menjalankan perintah itu. Aplikasi belum punya cara lain membuat Super Admin, dan belum ada alur ganti kata sandi Super Admin. Cron yang menyalurkan kata sandi lewat pipe memang mungkin secara teknis, tetapi menaruh kata sandi di crontab, belum diuji, dan tidak dianjurkan. Keputusan cara membuat Super Admin di hosting tanpa SSH menunggu pemilik proyek.

## 8. SMTP dan uji email

Driver `log` menulis email ke `storage/logs/mail.log`: tidak ada email terkirim dan **tautan token (reset password, verifikasi, undangan) tersimpan apa adanya di berkas itu**; siapa pun yang dapat membaca `storage/` (backup, docroot salah konfigurasi, akun hosting lain) dapat mengambil alih akun. Karena itu di `APP_ENV=production` aplikasi menolak driver `log` (halaman daftar/lupa password akan error 500 dengan pesan jelas di `app.log`) kecuali Anda sengaja mengatur `MAIL_ALLOW_LOG=true`. Untuk mengirim sungguhan:

```
MAIL_DRIVER=smtp
MAIL_HOST=mail.domain-anda.com
MAIL_PORT=465            # 465 dengan tls, 587 dengan starttls
MAIL_ENCRYPTION=tls      # none | starttls | tls
MAIL_USERNAME=no-reply@domain-anda.com
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@domain-anda.com
```

Gunakan akun email cPanel pada domain yang sama dan pastikan SPF/DKIM aktif di Email Deliverability agar tidak masuk spam. Uji: buka `/lupa-password`, masukkan email pengguna yang ada, lalu periksa kotak masuk. Atau daftar akun baru di `/daftar` dan cek email verifikasi. Bila tidak sampai, lihat `storage/logs/app.log`. Klien SMTP ini belum diuji terhadap server SMTP/TLS nyata.

## 9. Cron

**Sesi.** Berkas sesi disimpan di `storage/sessions` (bukan folder sesi bawaan PHP, jadi cron pembersih bawaan distro tidak menyentuhnya). Aplikasi membersihkannya sendiri: setiap sesi baru dibuka, ada peluang 1% (`gc_probability=1`, `gc_divisor=100`) menghapus berkas yang melewati `gc_maxlifetime` (batas idle + 1 jam). Sesi hanya dibuat bila permintaan membawa cookie sesi yang valid atau mulai menyimpan data (formulir/login), sehingga bot dan halaman 404/publik tidak menghabiskan inode. Pantau jumlah berkas di `storage/sessions` pada kuota inode hosting Anda.

Belum ada pekerjaan terjadwal yang diperlukan pada fondasi ini. Cron hanya relevan untuk menjalankan `php scripts/migrate.php` saat upgrade di hosting tanpa SSH (lihat bagian 10).

## 10. Backup dan upgrade

**Backup sebelum setiap migrasi, tanpa pengecualian.** MySQL meng-commit DDL otomatis, sehingga migrasi yang gagal di tengah tidak bisa di-rollback.

1. Backup database (cPanel > Backup, atau phpMyAdmin > Export, atau `mysqldump`) dan salin `.env` serta `storage/uploads/`. Simpan di luar server. Pastikan berkas backup terbuka dan terbaca.
2. Aktifkan mode pemeliharaan sebisanya (mis. jeda akses lewat cPanel) bila ada pengguna aktif.
3. Unggah versi baru menimpa berkas aplikasi. Jangan menimpa `.env` dan `storage/`.
4. Jalankan migrasi baru: `php scripts/migrate.php` (aman diulang; hanya menjalankan berkas yang belum tercatat di `schema_migrations`). Penginstal sudah terkunci dan tidak dipakai untuk upgrade. Tanpa SSH: buat cron satu kali (`php /home/AKUN/pos-retail/scripts/migrate.php`) pada menit berikutnya, lihat hasilnya lewat email cron, lalu hapus cron itu.
5. Buka aplikasi, uji login dan satu alur utama. Bila gagal, pulihkan database dan berkas dari backup.

### Bila migrasi gagal di tengah jalan

MySQL meng-commit DDL secara implisit, jadi kegagalan pada pernyataan ke-N meninggalkan tabel 1..N-1. Berkas migrasi memakai `CREATE TABLE IF NOT EXISTS`, sehingga **mengulang aman**: perbaiki penyebabnya (hak akses, kuota, engine), lalu jalankan ulang `php scripts/migrate.php` atau kirim ulang formulir penginstal. Tabel yang sudah ada dilewati dan nama berkas baru dicatat setelah semua pernyataan sukses. Pembersihan manual hanya perlu bila Anda ingin mulai dari nol: di phpMyAdmin pilih semua tabel pada database, pilih "Drop" (matikan "Enable foreign key checks" lebih dulu), lalu ulangi migrasi.

## 11. Pemecahan masalah

| Gejala | Penyebab umum |
|---|---|
| Halaman putih atau 500 | Lihat `storage/logs/app.log` (memuat ID permintaan yang tampil di halaman). Pastikan PHP 8.3+ dan `.env` ada. |
| `/install` 404 | `INSTALL_TOKEN` kosong atau kurang dari 16 karakter, atau `storage/installed.lock` sudah ada. Untuk memasang ulang pada database kosong: kosongkan database, hapus lock, isi token. |
| Semua halaman 403 atau 500 setelah memakai `.htaccess` root | `mod_rewrite` mati, atau `AllowOverride` tidak mengizinkan. Pindah ke pilihan A. |
| Tidak bisa login, selalu kembali ke halaman masuk | `SESSION_SECURE=true` tetapi situs diakses lewat HTTP; pakai HTTPS. Pastikan `storage/sessions` dapat ditulis. |
| "Sesi formulir kedaluwarsa" (419) | Cookie diblokir atau sesi habis; muat ulang halaman. |
| Unggah logo gagal | Batas logo 1 MB. Pastikan `upload_max_filesize` dan `post_max_size` di php.ini minimal 2M dan 3M (bawaan PHP 2M dan 8M sudah cukup); bila lebih kecil, naikkan lewat MultiPHP INI Editor. Pelanggaran `post_max_size` saat ini menampilkan 419, bukan pesan khusus. |
| Email tidak terkirim | `MAIL_DRIVER=log` di production (ditolak kecuali `MAIL_ALLOW_LOG=true`), atau kredensial/port/enkripsi salah. Lihat `app.log`. |
| Pra-pemeriksaan ekstensi gagal | Aktifkan di Select PHP Version, lalu muat ulang `/install`. |
