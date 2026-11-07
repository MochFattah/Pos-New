# Fondasi POS Retail — Rencana Implementasi

> **Untuk pekerja agentik:** WAJIB gunakan superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini task demi task. Langkah memakai sintaks checkbox (`- [ ]`).

**Tujuan:** Membangun dasar teknis dan UI POS Retail (inti PHP, auth, tenant, RBAC, cabang, pengguna, pengaturan, sistem desain) yang berjalan di cPanel.

**Arsitektur:** Modular monolith PHP Native 8.3 tanpa dependensi runtime. Alur: Router → Middleware → Controller → Validator/Policy → Service → Repository (PDO) → MySQL. View PHP dirender server, JS ES Modules, CSS Tailwind 4 yang dikompilasi dan di-commit.

**Tech Stack:** PHP 8.3, MySQL 8 (InnoDB), Tailwind CSS 4.3, JavaScript ES Modules, Node 22 (hanya untuk build CSS dan tes tampilan).

**Spec:** `docs/superpowers/specs/2026-11-08-foundation-design.md` (dibaca bersama `PRD.md`, `DESAIN.md`, `.cursorrules`).

**Aturan commit:** Langkah "Checkpoint" tiap task menjalankan seluruh tes. Commit git hanya dibuat jika pemilik proyek memintanya.

## Global Constraints

- PHP `declare(strict_types=1);` di setiap file; namespace root `App\`, PSR-4 ke `app/`; satu class utama per file; nama file = nama class.
- Tanpa framework backend dan tanpa dependensi Composer runtime; autoloader sendiri di `bootstrap/autoload.php`.
- Tanpa CDN; Inter dan semua aset dari `public/assets/` lokal. Tanpa inline JavaScript. JS: ES Modules, file `kebab-case.js`.
- Semua SQL memakai PDO prepared statements; dilarang menyambung input ke SQL.
- Setiap query tabel tenant wajib memuat `tenant_id`; `tenant_id` hanya berasal dari sesi server, tidak dari request.
- Tabel: `snake_case`, InnoDB, utf8mb4, uang `DECIMAL`, waktu UTC.
- Token (verifikasi email, reset password) disimpan sebagai hash; password via `password_hash`/`password_verify`; session ID diregenerasi setelah login; cookie `HttpOnly`, `Secure`, `SameSite=Lax`; CSRF pada semua request pengubah state.
- Batas default: login 5 gagal/15 menit per (email, IP); token reset 60 menit; token verifikasi 24 jam; idle sesi 8 jam; demo default 24 jam (`demo_settings.duration_hours`); zona waktu `Asia/Jakarta`, mata uang `IDR`.
- Bahasa antarmuka Bahasa Indonesia, sentence case. Midtrans, harga paket, metrik dashboard, kebijakan approval/retur/offline tidak dibuat atau dikarang.
- Token warna, spacing, radius, tipografi persis dari `DESAIN.md` bagian 2–4; WCAG 2.2 AA dicek dengan skrip; target sentuh ≥ 44 px.
- Folder modul di luar fondasi (Products, POS, dst.) tidak dibuat.
- Jangan mengklaim "teruji" untuk MySQL nyata, SMTP nyata, PHPUnit, atau PHPStan; laporkan sebagai belum diverifikasi.

## Review Focus

1. Email registrasi yang sama dengan huruf besar/kecil berbeda atau spasi di tepi → harus ditolak sebagai duplikat (Task 7).
2. Tautan verifikasi/reset yang dibuka dua kali, kedaluwarsa, atau token acak → pesan netral, tidak ada efek ganda (Task 7, 8).
3. Pengguna tenant A memanggil URL dengan ID milik tenant B (cabang, pengguna, role) → 404, bukan 403 yang membocorkan keberadaan data (Task 10, 11).
4. Owner menurunkan/menghapus dirinya sendiri atau role Owner → ditolak; tenant tidak pernah tanpa Owner (Task 11).
5. Unggah logo berekstensi gambar tetapi isinya skrip, atau nama file berbahaya → ditolak berdasarkan MIME dan nama diganti server (Task 12).

---

## Peta file (tanggung jawab)

| Path | Tanggung jawab |
|---|---|
| `bootstrap/autoload.php`, `bootstrap/app.php` | Autoloader PSR-4, perakitan aplikasi |
| `app/Core/{Config,Env}.php` | Konfigurasi dan `.env` |
| `app/Core/{Request,Response,Router,Application}.php` | HTTP dan routing |
| `app/Core/Middleware/*` | Session, CSRF, header keamanan, auth, permission, gerbang demo |
| `app/Core/Database/{Connection,Migrator}.php` | PDO dan migrasi |
| `app/Core/Validation/Validator.php` | Validasi input server |
| `app/Core/Security/{Session,Csrf,TokenService,RateLimiter,PasswordHasher}.php` | Primitif keamanan |
| `app/Core/Mail/{MailerInterface,LogMailer,SmtpMailer}.php` | Pengiriman email |
| `app/Core/Authorization/{AuthUser,PolicyService}.php` | RBAC dan konteks pengguna |
| `app/Modules/{Auth,Tenants,Branches,Users,Roles,AuditLogs,Platform}/` | Controller, Service, Repository, Validator per domain |
| `resources/{views,css,js}` | Tampilan, token/CSS, modul JS |
| `database/migrations/*.sql`, `database/seeders/` | Skema MySQL dan data referensi |
| `tests/` | Runner mandiri, tes Unit/Integration/Feature |
| `scripts/` | `migrate.php`, `create-platform-admin.php`, `build-css.mjs`, `check-contrast.mjs`, `screenshots.mjs` |

---

### Task 1: Kerangka proyek, konfigurasi, dan test runner

**Files:**
- Create: `bootstrap/autoload.php`, `app/Core/Env.php`, `app/Core/Config.php`, `config/{app,database,security,mail}.php`, `.env.example`, `.gitignore`, `composer.json`, `package.json`, `tests/run.php`, `tests/Support/TestCase.php`
- Test: `tests/Unit/Core/ConfigTest.php`, `tests/Unit/Core/AutoloadTest.php`

**Interfaces:**
- Produces: `App\Core\Env::load(string $path): void`, `Env::get(string $key, ?string $default = null): ?string`; `Config::load(string $basePath): void`, `Config::get(string $key, mixed $default = null): mixed` (notasi titik, mis. `security.login.max_attempts`); `Tests\Support\TestCase` dengan `assertSame`, `assertTrue`, `assertFalse`, `assertThrows(string $class, callable $fn)`, `assertContains`; `php tests/run.php [filter]` menemukan `tests/**/*Test.php`, menjalankan metode `test*`, keluar kode 1 jika ada yang gagal.

- [ ] **Step 1: Tulis tes gagal** — `ConfigTest::testReadsNestedKeyWithDefault` (`Config::get('security.login.max_attempts') === 5`, `Config::get('x.y', 'd') === 'd'`), `ConfigTest::testEnvOverridesValue` (`APP_TIMEZONE` di `.env` mengganti `app.timezone`), `AutoloadTest::testResolvesAppClassByPsr4`.
- [ ] **Step 2: Jalankan `php tests/run.php`** → Expected: FAIL (class tidak ada).
- [ ] **Step 3: Implementasikan** semua file di atas. `config/security.php` memuat nilai Global Constraints (login 5/900 detik, reset 3600, verifikasi 86400, idle 28800). `.gitignore` mengecualikan `.env`, `storage/*` (kecuali `.gitkeep`), `node_modules/`.
- [ ] **Step 4: Jalankan `php tests/run.php`** → Expected: semua PASS, exit 0.
- [ ] **Step 5: Checkpoint** — `find . -name '*.php' -not -path './node_modules/*' | xargs -n1 php -l` tanpa error.

### Task 2: HTTP inti — Request, Response, Router, Application

**Files:**
- Create: `app/Core/{Request,Response,Router,Application}.php`, `app/Core/Middleware/MiddlewareInterface.php`, `app/Core/Exceptions/{NotFoundException,ForbiddenException,ValidationException,HttpException}.php`, `public/index.php`, `public/.htaccess`
- Test: `tests/Unit/Core/RouterTest.php`, `tests/Unit/Core/ResponseTest.php`

**Interfaces:**
- Produces: `Request::fromGlobals(): self`, `Request::create(string $method, string $uri, array $data = [], array $headers = []): self` (untuk tes), `method()`, `path()`, `input(string $k, mixed $d = null)`, `all(): array`, `header(string): ?string`, `ip(): string`, `file(string): ?array`, `attribute(string $k, mixed $d = null)`, `withAttribute(string $k, mixed $v): static`; `Response::html(string, int = 200)`, `::json(array, int = 200)`, `::redirect(string, int = 302)`, `withHeader(string, string): static`, `status(): int`, `body(): string`, `header(string): ?string`, `send(): void`; `MiddlewareInterface::handle(Request $r, callable $next): Response`; `Router::get/post(string $pattern, callable|array $handler, array $middleware = []): void`, `Router::dispatch(Request): Response` (pola `/branches/{id}` → `$request->attribute('id')`; 404 → `NotFoundException`; metode salah → 405); `Application::handle(Request): Response` menangkap exception menjadi halaman error ramah tanpa stack trace.

- [ ] **Step 1: Tulis tes gagal** — `testMatchesParameterizedRoute`, `testUnknownPathThrowsNotFound`, `testWrongMethodReturns405`, `testMiddlewareRunsInDeclaredOrder`, `ResponseTest::testRedirectSetsLocationHeader`, `testApplicationHidesExceptionDetail` (body tidak memuat nama class exception/pesan SQL).
- [ ] **Step 2: Jalankan** `php tests/run.php Router` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**. `public/.htaccess` mengarahkan semua ke `index.php`, menolak dotfile, dan menonaktifkan listing direktori.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — `php -S 127.0.0.1:8080 -t public` lalu `curl -s -o /dev/null -w '%{http_code}' localhost:8080/tidak-ada` → `404`.

### Task 3: Database — Connection dan Migrator

**Files:**
- Create: `app/Core/Database/{Connection,Migrator}.php`, `scripts/migrate.php`, `tests/Support/Db.php`
- Test: `tests/Integration/Database/ConnectionTest.php`, `tests/Integration/Database/MigratorTest.php`

**Interfaces:**
- Produces: `Connection::__construct(\PDO $pdo)`, `::fromConfig(): self` (MySQL dari `config/database.php`), `::sqliteMemory(): self` (khusus tes), `query(string $sql, array $params = []): array`, `fetchOne(string $sql, array $params = []): ?array`, `execute(string $sql, array $params = []): int`, `lastInsertId(): int`, `transaction(callable $fn): mixed` (rollback saat exception); `Migrator::__construct(Connection $db, string $dir)`, `migrate(): array<string>` (nama berkas yang baru dijalankan, tabel `schema_migrations`, idempoten, berurutan menurut nama berkas). PDO diset `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`.

- [ ] **Step 1: Tulis tes gagal** — `testTransactionRollsBackOnException`, `testExecuteBindsParametersNotInterpolates` (nilai `' OR 1=1 --` tersimpan apa adanya), `testMigratorAppliesOnceInOrder` (dua kali `migrate()`, kedua kosong).
- [ ] **Step 2: Jalankan** `php tests/run.php Database` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**. `scripts/migrate.php` memuat konfigurasi, menjalankan `Migrator` pada `database/migrations`, mencetak daftar yang diterapkan.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — seluruh suite hijau.

### Task 4: Skema MySQL, seeder referensi, dan penjaga drift

**Files:**
- Create: `database/migrations/0001_foundation_schema.sql`, `database/seeders/reference_data.php`, `tests/Support/schema.sqlite.sql`
- Test: `tests/Unit/Database/SchemaTest.php`

**Interfaces:**
- Produces: 17 tabel sesuai spec bagian 5 (`platform_users` … `audit_logs`) dalam dialek MySQL 8; seeder mengisi `permissions` (9 kode: `branches.view|manage`, `users.view|manage`, `roles.view|manage`, `tenant_settings.view|manage`, `audit_logs.view`) dan satu baris `demo_settings` (`is_enabled=1`, `duration_hours=24`); `Db::fresh(): Connection` memuat `schema.sqlite.sql` + seeder untuk tes lain.

- [ ] **Step 1: Tulis tes gagal** — `testEveryTenantTableHasTenantId` (semua tabel kecuali `platform_users`, `permissions`, `demo_settings`, `schema_migrations`, `email_verifications`, `password_resets`, `login_attempts` memuat kolom `tenant_id`), `testEveryForeignKeyColumnIsIndexed`, `testSqliteSchemaColumnsMatchMysqlMigration` (nama tabel dan kolom identik), `testSeederIsIdempotent`.
- [ ] **Step 2: Jalankan** `php tests/run.php SchemaTest` → Expected: FAIL.
- [ ] **Step 3: Tulis DDL MySQL** dengan constraint spec (unik `users.email`, unik `(tenant_id, code)` cabang, unik `(tenant_id, slug)` role), lalu `schema.sqlite.sql` hasil terjemahan manual. Tes membaca nama tabel/kolom dengan pemindai regex sederhana.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — catat di `docs/database/README.md`: "DDL MySQL belum dijalankan pada server MySQL nyata".

### Task 5: Primitif keamanan — Session, CSRF, token, hasher, rate limiter

**Files:**
- Create: `app/Core/Security/{Session,Csrf,TokenService,PasswordHasher,RateLimiter}.php`, `app/Core/Middleware/{SessionMiddleware,CsrfMiddleware,SecurityHeadersMiddleware}.php`
- Test: `tests/Unit/Security/*Test.php`

**Interfaces:**
- Produces: `Session::start(): void`, `get/set/forget`, `flash(string $k, mixed $v)`, `pullFlash(string $k)`, `regenerate(): void`, `destroy(): void`, `isIdleExpired(int $maxIdleSeconds): bool`; `Csrf::token(Session $s): string`, `Csrf::isValid(Session $s, ?string $t): bool` (hash_equals); `TokenService::generate(): array{plain: string, hash: string}`, `TokenService::hash(string $plain): string` (sha256); `PasswordHasher::hash(string): string`, `verify(string, string): bool`; `RateLimiter::__construct(Connection $db, int $maxAttempts, int $windowSeconds)`, `tooManyAttempts(string $email, string $ip): bool`, `record(string $email, string $ip, bool $succeeded): void` (identifier disimpan sebagai hash email huruf kecil); `CsrfMiddleware` menolak POST tanpa token valid dengan 419; `SecurityHeadersMiddleware` menambah CSP (`default-src 'self'`), `X-Frame-Options: DENY`, `Referrer-Policy`, `X-Content-Type-Options`.

- [ ] **Step 1: Tulis tes gagal** — `testTokenHashDiffersFromPlainAndIsDeterministic`, `testRateLimiterBlocksAfterFiveFailuresWithinWindow`, `testRateLimiterIgnoresOldAttemptsAndOtherIp`, `testSuccessfulLoginDoesNotCountTowardLimit`, `testCsrfRejectsMissingAndWrongToken`, `testPostWithoutCsrfReturns419`, `testHeadersContainCsp`, `testPasswordVerifyRejectsWrongPassword`.
- [ ] **Step 2: Jalankan** `php tests/run.php Security` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**. Session: `session.save_path` ke `storage/sessions`, cookie flag sesuai Global Constraints, `use_strict_mode=1`.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — suite hijau.

### Task 6: Validator dan Mailer

**Files:**
- Create: `app/Core/Validation/{Validator,ValidationResult}.php`, `app/Core/Mail/{MailerInterface,LogMailer,SmtpMailer}.php`
- Test: `tests/Unit/Validation/ValidatorTest.php`, `tests/Unit/Mail/MailerTest.php`

**Interfaces:**
- Produces: `Validator::make(array $data, array $rules): ValidationResult`; aturan `required|email|min:N|max:N|confirmed|phone|in:a,b`; `ValidationResult::fails(): bool`, `errors(): array<string,string>` (satu pesan Bahasa Indonesia per field, mis. "Nomor telepon wajib diisi."), `validated(): array`; `MailerInterface::send(string $to, string $subject, string $textBody): void`; `LogMailer` menulis ke `storage/logs/mail.log`; `SmtpMailer::__construct(string $host, int $port, string $user, string $pass, string $from, string $encryption)` melakukan EHLO, STARTTLS, AUTH LOGIN, MAIL FROM, RCPT TO, DATA, QUIT.

- [ ] **Step 1: Tulis tes gagal** — `testRequiredMessageIsIndonesianAndFieldSpecific`, `testEmailRuleTrimsAndRejectsMalformed`, `testMinPasswordLengthIs8` (aturan `min:8`), `testConfirmedRequiresMatch`, `MailerTest::testLogMailerWritesRecipientAndBody`, `testSmtpMailerSpeaksExpectedCommandSequence` (server palsu lewat `stream_socket_pair`, urutan perintah diverifikasi, tanpa TLS).
- [ ] **Step 2: Jalankan** `php tests/run.php Validat` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**. Header email di-encode (RFC 2047) dan karakter CR/LF di input dibuang untuk mencegah header injection.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — tambahkan `testSmtpMailerRejectsCrlfInSubject`.

### Task 7: Registrasi tenant dan verifikasi email

**Files:**
- Create: `app/Modules/Tenants/{Services/RegisterTenantService,Repositories/TenantRepository,Repositories/UserRepository,Repositories/RoleRepository}.php`, `app/Modules/Auth/{Services/VerifyEmailService,Repositories/EmailVerificationRepository,Controllers/RegisterController}.php`, `app/Modules/Tenants/DemoService.php`
- Test: `tests/Feature/Auth/RegistrationTest.php`

**Interfaces:**
- Consumes: `Connection::transaction`, `TokenService`, `PasswordHasher`, `MailerInterface`, `Validator`.
- Produces: `RegisterTenantService::register(array $input): array{tenantId: int, userId: int}` (satu transaksi: tenant `pending_verification`, profil, pengaturan default, cabang utama `is_main=1`, user Owner `is_owner=1`, 8 role dari PRD 4.3 dengan slug `owner|manager|supervisor|kasir|staf-gudang|staf-pembelian|staf-keuangan|akuntan`, Owner mendapat role `owner`, email verifikasi terkirim, audit `tenant.registered`); `VerifyEmailService::verify(string $plainToken): bool`, `VerifyEmailService::resend(string $email, string $ip): void` (respons netral untuk email tak dikenal/sudah terverifikasi; dibatasi `RateLimiter` yang sama; token lama dibatalkan); `DemoService::startFor(int $tenantId, string $source): void` (menulis `tenant_demos` dengan `ends_at = now + duration_hours`); `UserRepository::findByEmail(string $email): ?array` (email dinormalisasi `trim` + huruf kecil).

- [ ] **Step 1: Tulis tes gagal** — `testRegisterCreatesTenantBranchOwnerAndEightRoles`, `testOwnerRoleHasNoRowsInRolePermissionsButIsOwnerFlagged`, `testOtherRolesAreCreatedEmpty`, `testDuplicateEmailIgnoringCaseAndWhitespaceIsRejected`, `testNoDemoBeforeVerification`, `testVerifyActivatesUserTenantAndStartsDemoOnce`, `testVerifyTokenSecondUseReturnsFalse`, `testExpiredOrRandomTokenReturnsFalseWithoutSideEffects`, `testRegistrationRollsBackWhenMailerFails`, `testPlainTokenNeverStored`, `testResendIsNeutralRateLimitedAndInvalidatesOldToken`.
- [ ] **Step 2: Jalankan** `php tests/run.php Registration` → Expected: FAIL.
- [ ] **Step 3: Implementasikan** service dan repository; controller hanya memanggil `Validator`, service, dan merender view (view dibuat di Task 14).
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — suite hijau.

### Task 8: Login, logout, reset password, gerbang demo

**Files:**
- Create: `app/Modules/Auth/{Services/AuthService,Services/PasswordResetService,Controllers/LoginController,Controllers/PasswordResetController}.php`, `app/Modules/Auth/Repositories/PasswordResetRepository.php`, `app/Core/Middleware/{AuthMiddleware,AccessGateMiddleware}.php`
- Test: `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/Auth/AccessGateTest.php`

**Interfaces:**
- Consumes: Task 5 dan 7.
- Produces: `AuthService::attempt(string $email, string $password, string $ip): AuthResult` (`AuthResult::{ok(AuthUser)|failed|locked|unverified}`; pesan ke pengguna sama untuk email tak dikenal dan password salah); `AuthService::logout(): void`; `PasswordResetService::request(string $email): void` (selalu diam-diam sukses), `reset(string $plainToken, string $newPassword): bool` (mencabut semua sesi pengguna); `AuthMiddleware` mengisi `attribute('user')` (`AuthUser`) dan menolak idle > 8 jam; `AccessGateMiddleware` mengarahkan ke halaman "demo berakhir" bila tidak ada baris `tenant_demos` dengan `starts_at <= now < ends_at`, mengecualikan rute logout dan halaman dukungan.

- [ ] **Step 1: Tulis tes gagal** — `testWrongPasswordAndUnknownEmailGiveIdenticalResult`, `testSessionIdRegeneratedOnLogin`, `testSixthFailureWithinWindowIsLocked`, `testUnverifiedUserCannotLogin`, `testResetRequestForUnknownEmailLooksSuccessful`, `testResetTokenIsSingleUseAndExpires60Minutes`, `testResetRevokesExistingSessions`, `testExpiredDemoBlocksOperationalRoutesButKeepsData`, `testGateAllowsLogoutAndSupportPage`, `testAuditLogRecordsLoginWithoutPassword`.
- [ ] **Step 2: Jalankan** `php tests/run.php Auth` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — suite hijau.

### Task 9: Audit log

**Files:**
- Create: `app/Modules/AuditLogs/{Services/AuditLogger,Repositories/AuditLogRepository,Controllers/AuditLogController}.php`
- Test: `tests/Unit/AuditLogs/AuditLoggerTest.php`

**Interfaces:**
- Produces: `AuditLogger::record(string $action, ?int $tenantId, string $actorType, ?int $actorId, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void` (mengisi IP dan `request_id`; kunci `password`, `token`, `secret` di `metadata` dibuang rekursif); `AuditLogRepository::paginate(int $tenantId, int $page, int $perPage): array`. Daftar audit tenant hanya untuk permission `audit_logs.view`.

- [ ] **Step 1: Tulis tes gagal** — `testSensitiveMetadataKeysAreStripped`, `testListIsScopedToTenant`, `testPaginationLimitsRows`.
- [ ] **Step 2: Jalankan** `php tests/run.php AuditLog` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**; ganti pemanggilan audit sementara di Task 7–8 agar memakai `AuditLogger`.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — suite hijau.

### Task 10: Otorisasi (PolicyService) dan Cabang

**Files:**
- Create: `app/Core/Authorization/{AuthUser,PolicyService}.php`, `app/Core/Middleware/PermissionMiddleware.php`, `app/Modules/Branches/{Controllers/BranchController,Services/BranchService,Repositories/BranchRepository,Validators/BranchValidator}.php`
- Test: `tests/Unit/Authorization/PolicyTest.php`, `tests/Feature/Branches/BranchTest.php`, `tests/Feature/TenantIsolationTest.php`

**Interfaces:**
- Produces: `AuthUser(int $id, int $tenantId, bool $isOwner, string $name, string $email)`; `PolicyService::can(AuthUser $u, string $permission, ?int $branchId = null): bool` (Owner → true untuk seluruh permission tenant; selain itu harus punya permission lewat role, dan bila `$branchId` diberikan harus ada di `user_branch_access`); `PolicyService::authorize(...)` melempar `ForbiddenException`; `PermissionMiddleware::requires(string $permission): MiddlewareInterface`; `BranchService::create|update|deactivate(AuthUser $actor, ...)`; `BranchRepository::find(int $tenantId, int $id): ?array`. Akses ID di luar tenant → `NotFoundException`.

- [ ] **Step 1: Tulis tes gagal** — `testOwnerCanEverythingInOwnTenant`, `testRoleWithoutPermissionIsDenied`, `testBranchRestrictedUserDeniedOtherBranch`, `testTenantAUserGets404ForTenantBBranchId`, `testTenantAUserCannotUpdateOrDeactivateTenantBBranch`, `testBranchCodeUniquePerTenantButReusableAcrossTenants`, `testMainBranchCannotBeDeactivated`, `testBranchChangesAreAudited`, `testNoRepositoryQueryOmitsTenantId` (pemindai statis atas `app/Modules/*/Repositories/*.php`: setiap SQL yang menyentuh tabel bertanda tenant memuat `tenant_id`).
- [ ] **Step 2: Jalankan** `php tests/run.php Policy` lalu `Branch` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**. Pemindai statis memakai daftar tabel tenant dari `schema.sqlite.sql`.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — suite hijau.

### Task 11: Pengguna dan Role/izin

**Files:**
- Create: `app/Modules/Users/{Controllers/UserController,Services/UserService,Validators/UserValidator}.php`, `app/Modules/Roles/{Controllers/RoleController,Services/RoleService,Repositories/PermissionRepository}.php`
- Test: `tests/Feature/Users/UserTest.php`, `tests/Feature/Roles/RoleTest.php`

**Interfaces:**
- Consumes: `PolicyService`, `UserRepository`, `RoleRepository`, `AuditLogger`.
- Produces: `UserService::invite|update|setStatus|assignRoles|assignBranches(AuthUser $actor, ...)`; `RoleService::syncPermissions(AuthUser $actor, int $roleId, array $permissionCodes): void`, `createCustom(AuthUser $actor, string $name): int`. Pengguna baru dibuat berstatus `pending_verification` dengan email penetapan password (mekanisme token reset). Hanya permission yang dimiliki aktor yang boleh diberikan (tidak melampaui kewenangan).

- [ ] **Step 1: Tulis tes gagal** — `testOwnerFlagCannotBeRemovedOrDeactivated`, `testOwnerRolePermissionsCannotBeEdited`, `testTenantAlwaysKeepsOneActiveOwner`, `testActorCannotGrantPermissionTheyLack`, `testUserFromOtherTenantReturns404`, `testRoleFromOtherTenantReturns404`, `testPermissionChangesAreAudited`, `testInviteEmailUniqueGloballyCaseInsensitive`.
- [ ] **Step 2: Jalankan** `php tests/run.php Users` lalu `Roles` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — suite hijau.

### Task 12: Pengaturan bisnis dan unggah logo

**Files:**
- Create: `app/Modules/Tenants/{Controllers/SettingsController,Services/TenantSettingsService,Services/LogoUploader,Validators/SettingsValidator}.php`
- Test: `tests/Feature/Tenants/SettingsTest.php`, `tests/Unit/Tenants/LogoUploaderTest.php`

**Interfaces:**
- Produces: `TenantSettingsService::update(AuthUser $actor, array $input): void` (profil, zona waktu valid IANA, mata uang 3 huruf, format angka); `LogoUploader::store(int $tenantId, array $uploadedFile): string` (validasi ukuran maksimal 1 MB, MIME nyata via `finfo` ∈ png/jpeg/webp, nama diganti `bin2hex(random_bytes(16))`, disimpan di `storage/uploads/logos/`); logo disajikan lewat rute terkontrol berpermission tenant, bukan URL langsung.

- [ ] **Step 1: Tulis tes gagal** — `testRejectsPhpFileRenamedToPng`, `testRejectsOversizeFile`, `testStoredNameIgnoresUserFilename` (nama `../../x.png` tidak berpengaruh), `testInvalidTimezoneRejected`, `testTenantCannotReadOtherTenantLogo`, `testSettingsChangeIsAudited`.
- [ ] **Step 2: Jalankan** `php tests/run.php Settings` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — suite hijau.

### Task 13: Jalur Super Admin

**Files:**
- Create: `app/Modules/Platform/{Controllers/PlatformAuthController,Controllers/PlatformDashboardController,Services/PlatformAuthService}.php`, `app/Core/Middleware/PlatformAuthMiddleware.php`, `scripts/create-platform-admin.php`, `routes/platform.php`
- Test: `tests/Feature/Platform/PlatformAuthTest.php`

**Interfaces:**
- Produces: `PlatformAuthService::attempt(string $email, string $password, string $ip): bool` (rate limit sama, memakai `platform_users`); sesi platform terpisah dari sesi tenant (kunci sesi `platform_user`); `create-platform-admin.php <nama> <email>` meminta password lewat prompt CLI tanpa echo, menolak berjalan dari web.

- [ ] **Step 1: Tulis tes gagal** — `testTenantSessionCannotAccessPlatformRoutes`, `testPlatformSessionCannotAccessTenantRoutes`, `testPlatformLoginIsRateLimited`, `testPlatformLoginIsAudited`.
- [ ] **Step 2: Jalankan** `php tests/run.php Platform` → Expected: FAIL.
- [ ] **Step 3: Implementasikan**.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS.
- [ ] **Step 5: Checkpoint** — `php scripts/create-platform-admin.php` dari web SAPI ditolak (tes `testScriptRefusesNonCli`).

### Task 14: Sistem desain — token, CSS, skrip kontras

**Files:**
- Create: `resources/css/{tokens.css,app.css}`, `resources/css/components/*.css`, `scripts/build-css.mjs`, `scripts/check-contrast.mjs`, `public/assets/fonts/` (Inter bila tersedia lokal), `docs/architecture/design-system.md`
- Test: `scripts/check-contrast.mjs` sebagai tes (exit 1 bila gagal)

**Interfaces:**
- Produces: variabel CSS untuk seluruh token `DESAIN.md` bagian 2 (terang dan gelap, `:root` dan `[data-theme="dark"]` plus `prefers-color-scheme`), skala spacing `space-1..12`, radius `sm/md/lg/xl/full`, tipografi bagian 3.2; kelas komponen: `btn btn-primary|secondary|tertiary|danger`, `field`, `field-error`, `badge badge-success|warning|danger|info|neutral`, `table-data`, `dialog-panel`, `toast`, `skeleton`, `empty-state`, `nav-item`, `skip-link`; `build-css.mjs` mengompilasi via API `tailwindcss` lokal ke `public/assets/css/app.css`.

- [ ] **Step 1: Tulis `check-contrast.mjs`** yang menghitung rasio WCAG untuk pasangan: teks (`text-primary|secondary|muted`) × (`background|surface|surface-muted`), teks tombol primary di atas `primary`, tiap `status` di atas `status-subtle`, dan `border`/focus ring (≥ 3:1), pada kedua tema; memakai nilai dari `tokens.css`.
- [ ] **Step 2: Jalankan** `node scripts/check-contrast.mjs` → Expected: FAIL (token belum ada).
- [ ] **Step 3: Tulis token dan CSS komponen** persis dari `DESAIN.md`. Pasangan yang gagal kontras **tidak diubah diam-diam**: dilaporkan sebagai temuan, lalu menunggu keputusan pemilik proyek (`.cursorrules` 8.2 mewajibkan pencatatan).
- [ ] **Step 4: Jalankan** `node scripts/build-css.mjs` lalu `node scripts/check-contrast.mjs` → Expected: CSS terbentuk; laporan rasio dicetak lengkap.
- [ ] **Step 5: Checkpoint** — simpan keluaran kontras di `docs/architecture/design-system.md`.

### Task 15: Layout, halaman, dan modul JS

**Files:**
- Create: `resources/views/layouts/{public,app,platform}.php`, `resources/views/components/*.php`, `resources/views/{landing,auth,tenant,platform,errors}/*.php`, `resources/js/{app.js,theme.js,drawer.js,dialog.js,toast.js,form-submit.js}`, `app/Core/View.php`
- Test: `tests/Feature/Views/ViewTest.php`, `scripts/screenshots.mjs`

**Interfaces:**
- Consumes: komponen CSS Task 14; controller Task 7–13.
- Produces: `View::render(string $template, array $data = [], string $layout = 'app'): string` (semua `$data` di-escape default lewat `e()`; HTML mentah hanya lewat `raw()` eksplisit); halaman sesuai spec 8.1 dengan empty/loading/error state; navigasi hanya memuat modul fondasi dan disaring `PolicyService`; `form-submit.js` mencegah submit ganda dan menampilkan status loading; `theme.js` membaca sistem lalu `localStorage` (dibungkus try/catch).

- [ ] **Step 1: Tulis tes gagal** — `testViewEscapesUserInputByDefault` (`<script>` di nama bisnis tampil sebagai teks), `testNavHidesItemsWithoutPermission`, `testEveryInputHasAssociatedLabel` (pemindai atas view form), `testErrorMessageLinkedViaAriaDescribedby`, `testNoInlineScriptOrInlineEventHandlers`, `testPagesDeclareLangIdAndSkipLink`.
- [ ] **Step 2: Jalankan** `php tests/run.php Views` → Expected: FAIL.
- [ ] **Step 3: Implementasikan** layout dan halaman. Dashboard awal: sambutan, status demo (sisa waktu), empty state; tanpa angka/grafik. Landing tanpa harga.
- [ ] **Step 4: Jalankan** `php tests/run.php` → Expected: PASS; lalu `node scripts/screenshots.mjs` menjalankan server dev dan mengambil tangkapan Chromium untuk Login, Daftar, Beranda, Cabang, Pengguna, Role, Pengaturan, Demo berakhir pada lebar 375/768/1280, terang dan gelap, ke `storage/cache/screens/`; periksa tidak ada scroll horizontal pada 375 px dan tidak ada error konsol.
- [ ] **Step 5: Checkpoint** — tinjau tangkapan layar satu per satu; catat temuan visual dan perbaiki.

### Task 16: Installer, dokumentasi deploy, dan verifikasi akhir

**Files:**
- Create: `app/Modules/Install/{InstallController,InstallService}.php`, `.htaccess` (root, fallback), `docs/deployment/cpanel.md`, `docs/deployment/nginx.md`, `docs/decisions/0001-zero-dependency-core.md`, `README.md`, `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.php`
- Test: `tests/Feature/Install/InstallTest.php`

**Interfaces:**
- Produces: `InstallService::isInstalled(): bool` (file lock `storage/installed.lock`); halaman `/install` hanya aktif bila belum terpasang dan token `INSTALL_TOKEN` di `.env` cocok; menjalankan `Migrator` lalu seeder referensi lalu menulis lock; setelah itu semua rute `/install*` mengembalikan 404.

- [ ] **Step 1: Tulis tes gagal** — `testInstallRequiresValidToken`, `testInstallRunsMigrationsAndWritesLock`, `testInstallRoutesReturn404AfterLock`, `testRootHtaccessBlocksEnvAppStorageVendor` (pemindai isi berkas).
- [ ] **Step 2: Jalankan** `php tests/run.php Install` → Expected: FAIL.
- [ ] **Step 3: Implementasikan** dan tulis dokumentasi: langkah cPanel (document root ke `public/`, fallback `.htaccess`, `.env`, cron, SMTP), Nginx, daftar permission folder, prosedur backup sebelum migrasi.
- [ ] **Step 4: Verifikasi akhir** — jalankan berurutan: `php tests/run.php` (hijau semua), `find . -name '*.php' -not -path './node_modules/*' | xargs -n1 php -l`, `node scripts/build-css.mjs`, `node scripts/check-contrast.mjs`, `node scripts/screenshots.mjs`, dan pemindai rahasia (`grep -rniE "server_key|password\s*=\s*['\"]" app config resources public` tanpa temuan).
- [ ] **Step 5: Laporan akhir** sesuai `.cursorrules` bagian 14: ringkasan, file, migrasi/konfigurasi, tes dan hasilnya, serta daftar eksplisit yang **belum diverifikasi** (DDL pada MySQL nyata, SMTP nyata, PHPUnit/PHPStan/PHP-CS-Fixer, perangkat dan browser nyata) dan keputusan yang menunggu (revisi PRD v1.4, konfirmasi waktu mulai demo, hasil kontras yang gagal bila ada).
