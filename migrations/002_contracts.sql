-- Migration: Contract templates and contracts for online signing
-- This migration is applied dynamically via ensureTable() in the repositories.
-- It is kept here for documentation and manual setup.

CREATE TABLE IF NOT EXISTS contract_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    include_sepa TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token VARCHAR(80) NOT NULL,
    template_id BIGINT UNSIGNED NULL,
    status ENUM('draft','open','signed','revoked') NOT NULL DEFAULT 'draft',
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    include_sepa TINYINT(1) NOT NULL DEFAULT 0,
    sevdesk_contact_id BIGINT UNSIGNED NULL,
    contact_name VARCHAR(190) NOT NULL DEFAULT '',
    contact_email VARCHAR(190) NOT NULL DEFAULT '',
    signer_name VARCHAR(190) NULL,
    signer_street VARCHAR(190) NULL,
    signer_zip VARCHAR(20) NULL,
    signer_city VARCHAR(120) NULL,
    signer_country VARCHAR(2) NULL DEFAULT 'DE',
    debtor_iban VARCHAR(34) NULL,
    debtor_bic VARCHAR(11) NULL,
    mandate_reference VARCHAR(35) NULL,
    payment_type ENUM('OOFF','RCUR') NULL,
    signature_path VARCHAR(255) NULL,
    pdf_path VARCHAR(255) NULL,
    sepa_pdf_path VARCHAR(255) NULL,
    signed_place VARCHAR(120) NULL,
    signed_date DATE NULL,
    signed_at DATETIME NULL,
    signed_ip VARCHAR(45) NULL,
    signed_user_agent VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contracts_token (token),
    KEY ix_contracts_status (status),
    KEY ix_contracts_template (template_id),
    KEY ix_contracts_contact (sevdesk_contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_template_fields (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(64) NOT NULL,
    label VARCHAR(190) NOT NULL,
    field_type ENUM('text','textarea','number','date','email') NOT NULL DEFAULT 'text',
    fill_by ENUM('admin','customer') NOT NULL DEFAULT 'admin',
    required TINYINT(1) NOT NULL DEFAULT 0,
    default_value TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tpl_field (template_id, field_key),
    KEY ix_tpl_field_template (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_field_values (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(64) NOT NULL,
    label VARCHAR(190) NOT NULL,
    field_type ENUM('text','textarea','number','date','email') NOT NULL DEFAULT 'text',
    fill_by ENUM('admin','customer') NOT NULL DEFAULT 'admin',
    required TINYINT(1) NOT NULL DEFAULT 0,
    value TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contract_field (contract_id, field_key),
    KEY ix_contract_field_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
