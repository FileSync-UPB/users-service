CREATE DATABASE IF NOT EXISTS upbcientifica_user_service
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE upbcientifica_user_service;

CREATE TABLE IF NOT EXISTS roles (
  role_code VARCHAR(64) NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  permission_code VARCHAR(64) NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (permission_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_code VARCHAR(64) NOT NULL,
  permission_code VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_code, permission_code),
  KEY idx_role_permissions_permission_code (permission_code),
  CONSTRAINT fk_role_permissions_role
    FOREIGN KEY (role_code) REFERENCES roles (role_code)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_role_permissions_permission
    FOREIGN KEY (permission_code) REFERENCES permissions (permission_code)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  role_code VARCHAR(64) NOT NULL,
  given_name VARCHAR(128) NOT NULL,
  sn VARCHAR(128) NOT NULL,
  mail VARCHAR(255) NOT NULL,
  uid VARCHAR(64) NOT NULL,
  home_directory VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  ldap_dn VARCHAR(512) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_profiles_username (username),
  UNIQUE KEY uq_user_profiles_uid (uid),
  KEY idx_user_profiles_role_code (role_code),
  KEY idx_user_profiles_active (active),
  CONSTRAINT fk_user_profiles_role
    FOREIGN KEY (role_code) REFERENCES roles (role_code)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_quotas (
  username VARCHAR(64) NOT NULL,
  quota_mb INT UNSIGNED NOT NULL,
  used_mb INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (username),
  CONSTRAINT fk_user_quotas_profile
    FOREIGN KEY (username) REFERENCES user_profiles (username)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_user_quotas_quota CHECK (quota_mb >= used_mb)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_services (
  username VARCHAR(64) NOT NULL,
  service_code VARCHAR(64) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (username, service_code),
  KEY idx_user_services_enabled (enabled),
  CONSTRAINT fk_user_services_profile
    FOREIGN KEY (username) REFERENCES user_profiles (username)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  role_code VARCHAR(64) NOT NULL,
  permissions_json JSON NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  client_ip VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auth_tokens_token_hash (token_hash),
  KEY idx_auth_tokens_username (username),
  KEY idx_auth_tokens_expires_at (expires_at),
  KEY idx_auth_tokens_active (active),
  CONSTRAINT fk_auth_tokens_profile
    FOREIGN KEY (username) REFERENCES user_profiles (username)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_auth_tokens_role
    FOREIGN KEY (role_code) REFERENCES roles (role_code)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  operation VARCHAR(128) NOT NULL,
  client_ip VARCHAR(45) NOT NULL,
  result VARCHAR(32) NOT NULL,
  occurred_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_logs_username (username),
  KEY idx_audit_logs_operation (operation),
  KEY idx_audit_logs_occurred_at (occurred_at),
  CONSTRAINT fk_audit_logs_profile
    FOREIGN KEY (username) REFERENCES user_profiles (username)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_metadata (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  meta_key VARCHAR(128) NOT NULL,
  meta_value TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_metadata_username_key (username, meta_key),
  CONSTRAINT fk_user_metadata_profile
    FOREIGN KEY (username) REFERENCES user_profiles (username)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_code, description) VALUES
  ('ADMINISTRADOR', 'Rol administrador con control total'),
  ('INVESTIGADOR', 'Rol para investigadores'),
  ('EMPLEADO', 'Rol para personal interno'),
  ('INVITADO', 'Rol con acceso limitado')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO permissions (permission_code, description) VALUES
  ('USER_CREATE', 'Crear usuarios'),
  ('USER_DELETE', 'Eliminar usuarios'),
  ('QUOTA_MANAGE', 'Gestionar cuotas'),
  ('SERVICE_MANAGE', 'Gestionar servicios'),
  ('ROLE_MANAGE', 'Gestionar roles'),
  ('USER_READ', 'Leer usuarios'),
  ('USER_LIST', 'Listar usuarios'),
  ('HPC_USE', 'Uso de HPC'),
  ('FILE_SHARE', 'Compartir archivos'),
  ('CONTENT_MANAGE', 'Gestionar contenidos'),
  ('FILE_MANAGE', 'Gestionar archivos'),
  ('FILE_SYNC', 'Sincronizar archivos'),
  ('LIMITED_READ', 'Lectura limitada')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_permissions (role_code, permission_code) VALUES
  ('ADMINISTRADOR', 'USER_CREATE'),
  ('ADMINISTRADOR', 'USER_DELETE'),
  ('ADMINISTRADOR', 'QUOTA_MANAGE'),
  ('ADMINISTRADOR', 'SERVICE_MANAGE'),
  ('ADMINISTRADOR', 'ROLE_MANAGE'),
  ('ADMINISTRADOR', 'USER_READ'),
  ('ADMINISTRADOR', 'USER_LIST'),
  ('INVESTIGADOR', 'HPC_USE'),
  ('INVESTIGADOR', 'FILE_SHARE'),
  ('INVESTIGADOR', 'CONTENT_MANAGE'),
  ('EMPLEADO', 'FILE_MANAGE'),
  ('EMPLEADO', 'FILE_SYNC'),
  ('INVITADO', 'LIMITED_READ')
ON DUPLICATE KEY UPDATE permission_code = VALUES(permission_code);
