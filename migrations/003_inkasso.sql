-- Migration: Mahnwesen / Inkasso-Übergaben
-- This migration is applied dynamically via ensureTable() in InkassoHandoverRepository.
-- The additional settings columns (smtp_*, inkasso_email) are applied dynamically
-- via ensureColumns() in SettingsRepository.
-- It is kept here for documentation and manual setup.

CREATE TABLE IF NOT EXISTS inkasso_handovers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sevdesk_invoice_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(80) NOT NULL DEFAULT '',
    sevdesk_contact_id BIGINT UNSIGNED NULL,
    contact_name VARCHAR(190) NOT NULL DEFAULT '',
    amount_original DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    dunning_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
    due_date DATE NULL,
    recipient_email VARCHAR(190) NOT NULL,
    attachments_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY ix_inkasso_invoice (sevdesk_invoice_id),
    KEY ix_inkasso_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
