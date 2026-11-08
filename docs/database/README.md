# Database

**Peringatan: DDL MySQL (`database/migrations/0001_foundation_schema.sql`) BELUM dijalankan pada server MySQL nyata di lingkungan ini.** Hanya diverifikasi secara statis/struktural (pemindai SQL pada tes). Jalankan pada MySQL 8 nyata sebelum dipakai.

## Tabel (17)

platform_users, tenants, tenant_profiles, tenant_settings, branches, users, roles, permissions, role_permissions, user_roles, user_branch_access, demo_settings, tenant_demos, email_verifications, password_resets, login_attempts, audit_logs. (`schema_migrations` dibuat oleh `Migrator` sendiri.)

Konvensi: InnoDB, utf8mb4_unicode_ci, id `BIGINT UNSIGNED AUTO_INCREMENT`, waktu UTC `DATETIME`, FK bernama eksplisit dan setiap kolom FK berindeks. Semua tabel memuat `tenant_id` kecuali `platform_users, tenants, permissions, demo_settings, email_verifications, password_resets, login_attempts` (`audit_logs.tenant_id` boleh NULL untuk peristiwa platform). Kolom status `tenants.status`, `users.status`, `tenant_demos.source`, `audit_logs.actor_type` memakai ENUM.

## Double uji SQLite

`tests/Support/schema.sqlite.sql` adalah terjemahan manual untuk tes repository (`Tests\Support\Db::fresh()` memuatnya, mengaktifkan `PRAGMA foreign_keys`, lalu menjalankan seeder referensi). Penjaga drift: `tests/Unit/Database/SchemaTest.php` mem-parse kedua berkas dan menuntut himpunan tabel, nama kolom per tabel, dan jumlah FK identik. Setiap perubahan migrasi MySQL harus diikuti perubahan berkas SQLite. Perilaku khusus MySQL (ENUM, tipe, collation) tidak teruji oleh double ini.

## Seeder referensi

`App\Core\Database\Seeders\ReferenceDataSeeder::run(Connection)` (idempoten) mengisi 9 permission fondasi dan satu baris `demo_settings`. `database/seeders/reference_data.php` adalah pemuat tipis untuk installer.
