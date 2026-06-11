<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Logger;

/**
 * Erzeugt den passenden Mailer (eigener SMTP oder Microsoft 365 / Graph)
 * anhand der gespeicherten Einstellungen.
 */
final class MailerFactory
{
    /**
     * @return SmtpMailer|GraphMailer
     */
    public static function fromSettings(array $settings): object
    {
        if (self::provider($settings) === 'm365') {
            return new GraphMailer([
                'tenant_id' => (string)($settings['m365_tenant_id'] ?? ''),
                'client_id' => (string)($settings['m365_client_id'] ?? ''),
                'client_secret' => self::decrypt($settings['m365_client_secret_encrypted'] ?? null),
                'from_email' => (string)($settings['smtp_from_email'] ?? ''),
                'from_name' => (string)($settings['smtp_from_name'] ?? ''),
                'test_mode' => !empty($settings['smtp_test_mode']),
            ]);
        }

        return new SmtpMailer([
            'host' => (string)($settings['smtp_host'] ?? ''),
            'port' => (int)($settings['smtp_port'] ?? 587),
            'encryption' => (string)($settings['smtp_encryption'] ?? 'tls'),
            'user' => (string)($settings['smtp_user'] ?? ''),
            'pass' => self::decrypt($settings['smtp_pass_encrypted'] ?? null),
            'from_email' => (string)($settings['smtp_from_email'] ?? ''),
            'from_name' => (string)($settings['smtp_from_name'] ?? ''),
            'test_mode' => !empty($settings['smtp_test_mode']),
        ]);
    }

    public static function provider(array $settings): string
    {
        return (($settings['mail_provider'] ?? 'smtp') === 'm365') ? 'm365' : 'smtp';
    }

    /**
     * Prüft, ob der Versand mit den aktuellen Einstellungen möglich ist.
     */
    public static function isConfigured(array $settings): bool
    {
        if (empty($settings['smtp_from_email'])) {
            return false;
        }
        if (!empty($settings['smtp_test_mode'])) {
            return true;
        }
        if (self::provider($settings) === 'm365') {
            return !empty($settings['m365_tenant_id'])
                && !empty($settings['m365_client_id'])
                && !empty($settings['m365_client_secret_encrypted']);
        }
        return !empty($settings['smtp_host']);
    }

    private static function decrypt(?string $encrypted): string
    {
        if ($encrypted === null || $encrypted === '') {
            return '';
        }
        try {
            return (new CryptoService())->decrypt($encrypted);
        } catch (\Throwable $e) {
            Logger::error('Mail-Zugangsdaten konnten nicht entschlüsselt werden', $e);
            return '';
        }
    }
}
