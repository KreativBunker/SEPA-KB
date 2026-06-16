-- Ratenzahlung per SEPA-Lastschrift
-- Hinweis: Die Tabellen/Spalten werden zur Laufzeit zusätzlich über
-- ensureTable()/ensureColumns() in den Repositories angelegt (wie beim Mahnwesen).
-- Diese Datei dient als Dokumentation und für manuelles Setup.

CREATE TABLE IF NOT EXISTS installment_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source ENUM('invoice','manual') NOT NULL DEFAULT 'invoice',
  sevdesk_invoice_id BIGINT UNSIGNED NULL,
  invoice_number VARCHAR(60) NOT NULL DEFAULT '',
  sevdesk_contact_id BIGINT UNSIGNED NULL,
  mandate_id BIGINT UNSIGNED NULL,
  debtor_name VARCHAR(190) NOT NULL DEFAULT '',
  debtor_iban VARCHAR(34) NOT NULL DEFAULT '',
  mandate_reference VARCHAR(35) NOT NULL DEFAULT '',
  mandate_date DATE NULL,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  rate_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  interval_months SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  first_collection_date DATE NOT NULL,
  remittance_template VARCHAR(140) NOT NULL DEFAULT 'Rechnung {invoice_number} Rate {rate_no}/{rate_count}',
  scheme ENUM('CORE','B2B') NOT NULL DEFAULT 'CORE',
  status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_installment_plan_invoice (sevdesk_invoice_id),
  KEY ix_installment_plan_contact (sevdesk_contact_id),
  KEY ix_installment_plan_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installment_rates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id BIGINT UNSIGNED NOT NULL,
  rate_no SMALLINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  due_date DATE NOT NULL,
  sequence_type ENUM('FRST','RCUR','OOFF','FNAL') NOT NULL DEFAULT 'RCUR',
  status ENUM('planned','queued','collected','failed','cancelled') NOT NULL DEFAULT 'planned',
  export_run_id BIGINT UNSIGNED NULL,
  export_item_id BIGINT UNSIGNED NULL,
  collected_at DATETIME NULL,
  error_text TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rate_plan_no (plan_id, rate_no),
  KEY ix_rate_status_due (status, due_date),
  CONSTRAINT fk_rate_plan FOREIGN KEY (plan_id) REFERENCES installment_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Export-Läufe um Typ und Sequenztyp erweitern (Raten-Läufe vs. normale Rechnungs-Läufe)
ALTER TABLE export_runs
  ADD COLUMN run_type ENUM('invoices','installments') NOT NULL DEFAULT 'invoices',
  ADD COLUMN sequence_type ENUM('FRST','RCUR','OOFF','FNAL') NULL;

-- Einstellungen für Ratenzahlungs-Defaults
ALTER TABLE settings
  ADD COLUMN installment_seq_mode VARCHAR(20) NOT NULL DEFAULT 'rcur_only',
  ADD COLUMN installment_default_rates SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  ADD COLUMN installment_remittance_template VARCHAR(140) NOT NULL DEFAULT 'Rechnung {invoice_number} Rate {rate_no}/{rate_count}';
