# 0001. Inti tanpa dependensi runtime

Status: diterima untuk sub-proyek 1 (fondasi). Tanggal: 2026-11-08.

## Konteks

Target deploy utama adalah cPanel hosting bersama: sering tanpa SSH, tanpa Composer, dengan versi PHP dan ekstensi yang dibatasi. `.cursorrules` menetapkan PHP Native tanpa framework backend penuh, tetapi menyebut Composer, PHPUnit, PHPStan, dan PHP-CS-Fixer sebagai alat standar. Lingkungan pengembangan tugas fondasi ini tidak punya MySQL, jaringan untuk memasang paket, ataupun Composer.

## Keputusan

- Inti (router, request/response, validator, session, CSRF, RBAC, migrator, SMTP, mailer) ditulis sendiri dan hanya memakai PHP standar. `composer.json` tidak punya `require` selain versi PHP; autoload PSR-4 dipenuhi `bootstrap/autoload.php` sehingga server tidak perlu menjalankan `composer install`.
- Ekstensi yang dipakai dibatasi: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`.
- Pengujian memakai runner sendiri (`tests/run.php`) dengan SQLite in-memory sebagai double repository dan Chromium/Playwright untuk tes JS. `phpunit.xml`, `phpstan.neon`, dan `.php-cs-fixer.php` disediakan untuk dijalankan pemilik proyek, tetapi belum pernah dijalankan.
- Tailwind hanya alat build. CSS hasil build di-commit, server tidak memerlukan Node.

## Konsekuensi

- Pro: unggah dan jalan di hosting bersama; permukaan serangan rantai pasok kecil; perilaku keamanan mudah diaudit.
- Kontra: kode infrastruktur (SMTP, validator, migrator) menjadi tanggung jawab sendiri dan belum teruji di server nyata; migrator tidak sepintar alat matang (DDL MySQL tidak transaksional, tanpa kunci konkurensi antar-proses selain penginstal).
- Library yang dibutuhkan fitur berikutnya (mis. PhpSpreadsheet/Dompdf untuk ekspor, Midtrans) dapat ditambahkan lewat Composer dengan folder `vendor/` ikut diunggah; itu perubahan yang perlu persetujuan sesuai `.cursorrules` bagian 2.2.
- Bila dependensi ditambahkan, evaluasi ulang keputusan ini dan pembaruan `docs/deployment/`.
