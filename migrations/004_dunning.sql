-- Migration: Mahnwesen-Automatik (automatische Zahlungserinnerungen und Mahnungen)
-- This migration is applied dynamically via ensureTable() in
-- DunningActionRepository, DunningExclusionRepository and DunningRunRepository.
-- The additional settings columns (dunning_*) are applied dynamically via
-- ensureColumns() in SettingsRepository.
-- It is kept here for documentation and manual setup.

CREATE TABLE IF NOT EXISTS dunning_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sevdesk_invoice_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(80) NOT NULL DEFAULT '',
    sevdesk_contact_id BIGINT UNSIGNED NULL,
    contact_name VARCHAR(190) NOT NULL DEFAULT '',
    stage TINYINT UNSIGNED NOT NULL, -- 1=Zahlungserinnerung, 2=1. Mahnung, 3=2. Mahnung
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    due_date DATE NULL, -- Faelligkeit der Ursprungsrechnung
    recipient_email VARCHAR(190) NULL,
    status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    sevdesk_dunning_id BIGINT UNSIGNED NULL, -- ID des in sevdesk erzeugten Mahnbelegs
    error_text TEXT NULL,
    is_test TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by BIGINT UNSIGNED NULL,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dunning_invoice_stage (sevdesk_invoice_id, stage),
    KEY ix_dunning_status (status),
    KEY ix_dunning_contact (sevdesk_contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dunning_exclusions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope ENUM('invoice','contact') NOT NULL,
    sevdesk_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(190) NOT NULL DEFAULT '',
    note VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dunning_excl (scope, sevdesk_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dunning_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trigger_type ENUM('cron','web','manual') NOT NULL DEFAULT 'cron',
    mode ENUM('review','auto') NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    candidates INT UNSIGNED NOT NULL DEFAULT 0,
    queued INT UNSIGNED NOT NULL DEFAULT 0,
    sent INT UNSIGNED NOT NULL DEFAULT 0,
    skipped INT UNSIGNED NOT NULL DEFAULT 0,
    errors INT UNSIGNED NOT NULL DEFAULT 0,
    log_text MEDIUMTEXT NULL,
    PRIMARY KEY (id),
    KEY ix_dunning_runs_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
