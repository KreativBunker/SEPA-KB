<?php
declare(strict_types=1);

namespace App\Support;

final class ContractPlaceholders
{
    /**
     * Apply both built-in and custom placeholders to an HTML body.
     * Values are HTML-escaped before substitution.
     *
     * @param string $body          HTML body containing {{key}} placeholders.
     * @param array  $contract      Contract row (for signer fields and date fallbacks).
     * @param array  $settings      Settings row (creditor info).
     * @param array  $customValues  Associative array field_key => raw value (custom fields).
     */
    public static function apply(string $body, array $contract, array $settings, array $customValues = []): string
    {
        $placeholders = self::buildMap($contract, $settings, $customValues);
        foreach ($placeholders as $key => $value) {
            if ((string)$value === '') {
                continue;
            }
            $body = str_replace($key, htmlspecialchars((string)$value), $body);
        }
        return $body;
    }

    /**
     * Build the placeholder => raw value map (NOT escaped).
     */
    public static function buildMap(array $contract, array $settings, array $customValues = []): array
    {
        $map = [
            '{{mandant_name}}' => (string)($contract['signer_name'] ?? ''),
            '{{mandant_strasse}}' => (string)($contract['signer_street'] ?? ''),
            '{{mandant_plz}}' => (string)($contract['signer_zip'] ?? ''),
            '{{mandant_ort}}' => (string)($contract['signer_city'] ?? ''),
            '{{mandant_land}}' => (string)($contract['signer_country'] ?? 'DE'),
            '{{firma}}' => (string)($settings['creditor_name'] ?? ''),
            '{{firma_strasse}}' => (string)($settings['creditor_street'] ?? ''),
            '{{firma_plz}}' => (string)($settings['creditor_zip'] ?? ''),
            '{{firma_ort}}' => (string)($settings['creditor_city'] ?? ''),
            '{{firma_land}}' => (string)($settings['creditor_country'] ?? ''),
            '{{firma_iban}}' => (string)($settings['creditor_iban'] ?? ''),
            '{{firma_bic}}' => (string)($settings['creditor_bic'] ?? ''),
            '{{glaeubiger_id}}' => (string)($settings['creditor_id'] ?? ''),
            '{{datum}}' => date('d.m.Y'),
        ];

        foreach ($customValues as $key => $value) {
            $key = (string)$key;
            if ($key === '') {
                continue;
            }
            $map['{{' . $key . '}}'] = (string)$value;
        }

        return $map;
    }
}
