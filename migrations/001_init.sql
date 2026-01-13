CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','viewer') NOT NULL DEFAULT 'staff',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED NOT NULL,
  creditor_name VARCHAR(140) NOT NULL,
  creditor_id VARCHAR(35) NOT NULL,
  creditor_iban VARCHAR(34) NOT NULL,
  creditor_bic VARCHAR(11) NULL,
  initiating_party_name VARCHAR(140) NULL,
  default_scheme ENUM('CORE','B2B') NOT NULL DEFAULT 'CORE',
  default_pain_version ENUM('pain.008.001.02','pain.008.001.08') NOT NULL DEFAULT 'pain.008.001.08',
  default_days_until_collection SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  batch_booking TINYINT(1) NOT NULL DEFAULT 1,
  charge_bearer ENUM('SLEV') NOT NULL DEFAULT 'SLEV',
  sanitize_text TINYINT(1) NOT NULL DEFAULT 1,
  include_bic TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sevdesk_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(120) NOT NULL DEFAULT 'default',
  api_token_encrypted TEXT NOT NULL,
  header_mode ENUM('Authorization','X-Authorization') NOT NULL DEFAULT 'Authorization',
  base_url VARCHAR(190) NOT NULL DEFAULT 'https://my.sevdesk.de/api/v1',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sevdesk_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts_cache (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sevdesk_contact_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  street VARCHAR(190) NULL,
  zip VARCHAR(20) NULL,
  city VARCHAR(120) NULL,
  country VARCHAR(2) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contacts_cache_contact (sevdesk_contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mandates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sevdesk_contact_id BIGINT UNSIGNED NOT NULL,
  debtor_name VARCHAR(190) NOT NULL,
  debtor_iban VARCHAR(34) NOT NULL,
  debtor_bic VARCHAR(11) NULL,
  mandate_reference VARCHAR(35) NOT NULL,
  mandate_date DATE NOT NULL,
  scheme ENUM('CORE','B2B') NOT NULL DEFAULT 'CORE',
  sequence_mode ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  status ENUM('active','paused','revoked') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  attachment_path VARCHAR(255) NULL,
  last_sequence_type ENUM('FRST','RCUR','OOFF','FNAL') NULL,
  last_export_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mandates_contact (sevdesk_contact_id),
  UNIQUE KEY uq_mandates_reference (mandate_reference),
  KEY ix_mandates_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS export_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  collection_date DATE NOT NULL,
  pain_version ENUM('pain.008.001.02') NOT NULL,
  batch_booking TINYINT(1) NOT NULL DEFAULT 1,
  scheme_default ENUM('CORE','B2B') NULL,
  endtoend_strategy ENUM('invoice_number','generated') NOT NULL DEFAULT 'invoice_number',
  remittance_template VARCHAR(140) NOT NULL DEFAULT 'Rechnung {invoice_number}',
  status ENUM('draft','validated','exported','final') NOT NULL DEFAULT 'draft',
  total_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_sum DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  file_path VARCHAR(255) NULL,
  file_hash CHAR(64) NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  validated_at DATETIME NULL,
  exported_at DATETIME NULL,
  finalized_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_export_runs_status (status),
  CONSTRAINT fk_export_runs_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS export_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  export_run_id BIGINT UNSIGNED NOT NULL,
  sevdesk_invoice_id BIGINT UNSIGNED NOT NULL,
  invoice_number VARCHAR(60) NOT NULL,
  sevdesk_contact_id BIGINT UNSIGNED NOT NULL,
  debtor_name VARCHAR(190) NOT NULL,
  debtor_iban VARCHAR(34) NOT NULL,
  mandate_reference VARCHAR(35) NOT NULL,
  mandate_date DATE NOT NULL,
  sequence_type ENUM('FRST','RCUR','OOFF','FNAL') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  endtoend_id VARCHAR(35) NOT NULL,
  remittance VARCHAR(140) NOT NULL,
  status ENUM('pending','ok','error','blocked_duplicate') NOT NULL DEFAULT 'pending',
  error_text TEXT NULL,
  exported_once TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_export_items_run_invoice (export_run_id, sevdesk_invoice_id),
  KEY ix_export_items_run_status (export_run_id, status),
  KEY ix_export_items_invoice (sevdesk_invoice_id),
  CONSTRAINT fk_export_items_run FOREIGN KEY (export_run_id) REFERENCES export_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_export_markers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sevdesk_invoice_id BIGINT UNSIGNED NOT NULL,
  first_export_run_id BIGINT UNSIGNED NOT NULL,
  first_exported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoice_marker_invoice (sevdesk_invoice_id),
  CONSTRAINT fk_invoice_marker_run FOREIGN KEY (first_export_run_id) REFERENCES export_runs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(60) NOT NULL,
  object_type VARCHAR(60) NOT NULL,
  object_id BIGINT UNSIGNED NULL,
  meta_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_user_time (user_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
