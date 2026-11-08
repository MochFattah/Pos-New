-- Terjemahan manual database/migrations/0001_foundation_schema.sql untuk SQLite (double uji).
-- Dijaga selaras oleh SchemaTest::testSqliteSchemaColumnsMatchMysqlMigration.

CREATE TABLE platform_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'active',
  last_login_at TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  CONSTRAINT uq_platform_users_email UNIQUE (email)
);

CREATE TABLE tenants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  uuid TEXT NOT NULL,
  name TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending_verification' CHECK (status IN ('pending_verification','active','suspended')),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  CONSTRAINT uq_tenants_uuid UNIQUE (uuid)
);

CREATE TABLE tenant_profiles (
  tenant_id INTEGER NOT NULL PRIMARY KEY,
  business_type TEXT NULL,
  address TEXT NULL,
  phone TEXT NULL,
  logo_path TEXT NULL,
  CONSTRAINT fk_tenant_profiles_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
);

CREATE TABLE tenant_settings (
  tenant_id INTEGER NOT NULL PRIMARY KEY,
  timezone TEXT NOT NULL DEFAULT 'Asia/Jakarta',
  currency_code TEXT NOT NULL DEFAULT 'IDR',
  number_format TEXT NOT NULL DEFAULT 'id-ID',
  CONSTRAINT fk_tenant_settings_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
);

CREATE TABLE branches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tenant_id INTEGER NOT NULL,
  code TEXT NOT NULL,
  name TEXT NOT NULL,
  address TEXT NULL,
  phone TEXT NULL,
  is_main INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  CONSTRAINT uq_branches_tenant_code UNIQUE (tenant_id, code),
  CONSTRAINT uq_branches_tenant_id UNIQUE (tenant_id, id),
  CONSTRAINT fk_branches_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants (id)
);

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tenant_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  email TEXT NOT NULL,
  phone TEXT NULL,
  password_hash TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending_verification' CHECK (status IN ('pending_verification','active','inactive')),
  email_verified_at TEXT NULL,
  is_owner INTEGER NOT NULL DEFAULT 0,
  last_login_at TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  CONSTRAINT uq_users_email UNIQUE (email),
  CONSTRAINT uq_users_tenant_id UNIQUE (tenant_id, id),
  CONSTRAINT fk_users_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants (id)
);

CREATE TABLE roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tenant_id INTEGER NOT NULL,
  slug TEXT NOT NULL,
  name TEXT NOT NULL,
  is_system INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT uq_roles_tenant_slug UNIQUE (tenant_id, slug),
  CONSTRAINT uq_roles_tenant_id UNIQUE (tenant_id, id),
  CONSTRAINT fk_roles_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants (id)
);

CREATE TABLE permissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL,
  module TEXT NOT NULL,
  description TEXT NOT NULL,
  CONSTRAINT uq_permissions_code UNIQUE (code)
);

CREATE TABLE role_permissions (
  role_id INTEGER NOT NULL,
  permission_id INTEGER NOT NULL,
  tenant_id INTEGER NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (tenant_id, role_id) REFERENCES roles (tenant_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id)
);

CREATE TABLE user_roles (
  user_id INTEGER NOT NULL,
  role_id INTEGER NOT NULL,
  tenant_id INTEGER NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (tenant_id, role_id) REFERENCES roles (tenant_id, id)
);

CREATE TABLE user_branch_access (
  user_id INTEGER NOT NULL,
  branch_id INTEGER NOT NULL,
  tenant_id INTEGER NOT NULL,
  PRIMARY KEY (user_id, branch_id),
  CONSTRAINT fk_user_branch_access_user FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_user_branch_access_branch FOREIGN KEY (tenant_id, branch_id) REFERENCES branches (tenant_id, id)
);

CREATE TABLE demo_settings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  is_enabled INTEGER NOT NULL DEFAULT 1,
  duration_hours INTEGER NOT NULL DEFAULT 24
);

CREATE TABLE tenant_demos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tenant_id INTEGER NOT NULL,
  starts_at TEXT NOT NULL,
  ends_at TEXT NOT NULL,
  source TEXT NOT NULL CHECK (source IN ('registration','grant','extension')),
  granted_by_platform_user_id INTEGER NULL,
  reason TEXT NULL,
  created_at TEXT NOT NULL,
  CONSTRAINT fk_tenant_demos_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants (id),
  CONSTRAINT fk_tenant_demos_granted_by_platform_user_id FOREIGN KEY (granted_by_platform_user_id) REFERENCES platform_users (id)
);

CREATE TABLE email_verifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used_at TEXT NULL,
  created_at TEXT NOT NULL,
  CONSTRAINT uq_email_verifications_token UNIQUE (token_hash),
  CONSTRAINT fk_email_verifications_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE password_resets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used_at TEXT NULL,
  ip TEXT NULL,
  created_at TEXT NOT NULL,
  CONSTRAINT uq_password_resets_token UNIQUE (token_hash),
  CONSTRAINT fk_password_resets_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  identifier_hash TEXT NOT NULL,
  ip TEXT NOT NULL,
  succeeded INTEGER NOT NULL DEFAULT 0,
  attempted_at TEXT NOT NULL
);

CREATE TABLE audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tenant_id INTEGER NULL,
  actor_type TEXT NOT NULL CHECK (actor_type IN ('tenant_user','platform_user','system')),
  actor_id INTEGER NULL,
  action TEXT NOT NULL,
  entity_type TEXT NULL,
  entity_id INTEGER NULL,
  metadata TEXT NULL,
  ip TEXT NULL,
  request_id TEXT NULL,
  created_at TEXT NOT NULL,
  CONSTRAINT fk_audit_logs_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants (id)
);
