# Database

**Peringatan: DDL MySQL (`database/migrations/0001_foundation_schema.sql`) BELUM dijalankan pada server MySQL nyata di lingkungan ini.** Hanya diverifikasi secara statis/struktural (pemindai SQL pada tes). Jalankan pada MySQL 8 nyata sebelum dipakai.

## Tabel (17)

platform_users, tenants, tenant_profiles, tenant_settings, branches, users, roles, permissions, role_permissions, user_roles, user_branch_access, demo_settings, tenant_demos, email_verifications, password_resets, login_attempts, audit_logs. (`schema_migrations` dibuat oleh `Migrator` sendiri.)

Konvensi: InnoDB, utf8mb4_unicode_ci, id `BIGINT UNSIGNED AUTO_INCREMENT`, waktu UTC `DATETIME`, FK bernama eksplisit dan setiap kolom FK berindeks. Semua tabel memuat `tenant_id` kecuali `platform_users, tenants, permissions, demo_settings, email_verifications, password_resets, login_attempts` (`audit_logs.tenant_id` boleh NULL untuk peristiwa platform). Kolom status `tenants.status`, `users.status`, `tenant_demos.source`, `audit_logs.actor_type` memakai ENUM.

## Isolasi tenant pada skema

`users`, `roles`, dan `branches` punya `UNIQUE (tenant_id, id)`. Tabel penghubung memakai FK komposit sehingga tautan lintas tenant ditolak di tingkat database: `user_roles` `(tenant_id, user_id)` -> `users(tenant_id, id)` dan `(tenant_id, role_id)` -> `roles(tenant_id, id)`; `user_branch_access` `(tenant_id, user_id)` -> `users` dan `(tenant_id, branch_id)` -> `branches`; `role_permissions` `(tenant_id, role_id)` -> `roles` plus `permission_id` -> `permissions(id)`. Setiap FK punya indeks yang diawali kolom FK-nya, berurutan.

## Double uji SQLite

`tests/Support/schema.sqlite.sql` adalah terjemahan manual untuk tes repository (`Tests\Support\Db::fresh()` memuatnya, mengaktifkan `PRAGMA foreign_keys`, lalu menjalankan seeder referensi).

Penjaga drift (`SqlSchemaParser::drift`, dipakai `SchemaTest`) membandingkan MySQL vs SQLite untuk: himpunan tabel; nama kolom; flag NOT NULL per kolom (kolom PK dianggap NOT NULL); primary key; himpunan UNIQUE tingkat tabel (SQLite wajib memakai `CONSTRAINT ... UNIQUE (...)`); setiap FK (kolom, tabel dan kolom rujukan, aksi ON DELETE; NO ACTION disamakan dengan RESTRICT); dan daftar nilai ENUM (MySQL) vs `CHECK (kolom IN (...))` (SQLite). Tes negatif memutasi salinan teks SQLite (hapus unique, arahkan ulang FK, ubah kolom FK, ubah ON DELETE, balik NOT NULL, ubah daftar enum) dan menuntut tiap mutasi terdeteksi.

Yang TIDAK dibandingkan: tipe kolom, nilai DEFAULT, nama constraint/indeks, indeks non-unik di SQLite (SQLite tidak membuatnya), collation. Perilaku khusus MySQL tidak teruji oleh double ini. Perubahan migrasi MySQL harus diikuti perubahan berkas SQLite.

## Seeder referensi

`App\Core\Database\Seeders\ReferenceDataSeeder::run(Connection)` (idempoten) mengisi 9 permission fondasi dan satu baris `demo_settings`. `database/seeders/reference_data.php` adalah pemuat tipis untuk installer.
