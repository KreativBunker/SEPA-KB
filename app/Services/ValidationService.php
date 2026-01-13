<?php
declare(strict_types=1);

namespace App\Services;

final class ValidationService
{
    public function validateIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (!preg_match('/^[A-Z0-9]{15,34}$/', $iban)) {
            return false;
        }
        // Move first 4 chars to end
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $converted = '';
        foreach (str_split($rearranged) as $ch) {
            if (ctype_alpha($ch)) {
                $converted .= (string)(ord($ch) - 55);
            } else {
                $converted .= $ch;
            }
        }
        // mod 97
        $checksum = 0;
        foreach (str_split($converted, 7) as $part) {
            $checksum = (int)(($checksum . $part) % 97);
        }
        return $checksum === 1;
    }

    public function sanitizeSepaText(string $text): string
    {
        $text = trim($text);

        $map = [
            'Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        ];
        $text = strtr($text, $map);

        // Allowed SEPA chars (common subset): A-Z a-z 0-9 / - ? : ( ) . , ' + space
        $text = preg_replace("/[^A-Za-z0-9 \/\-\?:\(\)\.,'\+]/u", '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim((string)$text);
    }

public function text(string $text, int $maxLen = 70): string
{
    $text = $this->sanitizeSepaText($text);
    return $this->limit($text, $maxLen);
}

public function remittance(string $text, int $maxLen = 140): string
{
    $text = $this->sanitizeSepaText($text);
    return $this->limit($text, $maxLen);
}

public function iban(string $iban): string
{
    $iban = strtoupper(preg_replace('/\s+/', '', $iban));
    if ($iban === '' || !$this->validateIban($iban)) {
        throw new \RuntimeException('Ungültige IBAN: ' . $iban);
    }
    return $iban;
}

public function bic(string $bic): string
{
    $bic = strtoupper(preg_replace('/\s+/', '', $bic));
    if ($bic === '') {
        throw new \RuntimeException('BIC ist leer');
    }
    if (!preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $bic)) {
        throw new \RuntimeException('Ungültige BIC: ' . $bic);
    }
    return $bic;
}

public function creditorId(string $id): string
{
    $id = strtoupper(preg_replace('/\s+/', '', trim($id)));
    $id = preg_replace('/[^A-Z0-9]/', '', $id);
    if ($id === '' || strlen($id) > 35) {
        throw new \RuntimeException('Ungültige Gläubiger ID');
    }
    return $id;
}

public function mandateId(string $ref): string
{
    $ref = $this->sanitizeSepaText($ref);
    $ref = $this->limit($ref, 35);
    if ($ref === '') {
        throw new \RuntimeException('Mandatsreferenz fehlt');
    }
    return $ref;
}

public function endToEndId(string $id): string
{
    $id = $this->sanitizeSepaText($id);
    $id = $this->limit($id, 35);
    if ($id === '') {
        return 'NOTPROVIDED';
    }
    if (!$this->validateEndToEndId($id)) {
        throw new \RuntimeException('Ungültige EndToEndId: ' . $id);
    }
    return $id;
}

private function limit(string $text, int $maxLen): string
{
    $text = trim($text);
    if ($maxLen <= 0) {
        return '';
    }
    if (function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $text = mb_substr($text, 0, $maxLen, 'UTF-8');
        }
    } else {
        if (strlen($text) > $maxLen) {
            $text = substr($text, 0, $maxLen);
        }
    }
    return trim($text);
}

    public function validateEndToEndId(string $id): bool
    {
        $id = trim($id);
        if ($id === '' || strlen($id) > 35) {
            return false;
        }
        return (bool)preg_match("/^[A-Za-z0-9\+\?\:\(\)\.,'\-\/ ]+$/", $id);
    }

    public function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
