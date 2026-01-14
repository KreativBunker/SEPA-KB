<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Minimal PDF generator without external libs.
 * Uses standard Helvetica fonts and WinAnsiEncoding for umlauts.
 * Embeds JPEG signatures directly (no GD needed). PNG signatures are supported via GD if available.
 */
final class SimplePdf
{
    private static function winAnsi(string $s): string
    {
        if ($s === '') {
            return '';
        }

        // Prefer iconv, then mb_convert_encoding, then a small manual fallback for common DE umlauts.
        if (function_exists('iconv')) {
            $enc = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
            if ($enc !== false) {
                return $enc;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            try {
                return (string)mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            } catch (\Throwable $e) {
                // continue
            }
        }

        // Manual fallback for common characters (äöüÄÖÜß)
        $map = [
            'ä' => chr(0xE4),
            'ö' => chr(0xF6),
            'ü' => chr(0xFC),
            'Ä' => chr(0xC4),
            'Ö' => chr(0xD6),
            'Ü' => chr(0xDC),
            'ß' => chr(0xDF),
        ];
        return strtr($s, $map);
    }

    private static function esc(string $s): string
    {
        $s = self::winAnsi($s);
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('(', '\\(', $s);
        $s = str_replace(')', '\\)', $s);
        return $s;
    }

    private static function text(float $x, float $y, string $font, int $size, string $text): string
    {
        $t = self::esc($text);
        return "0 0 0 rg 0 0 0 RG BT /{$font} {$size} Tf {$x} {$y} Td ({$t}) Tj ET\n";
    }

    private static function multiText(float $x, float $y, float $w, string $font, int $size, int $lineH, string $text): string
    {
        $maxChars = max(10, (int)floor($w / ($size * 0.52)));
        $out = '';
        $yy = $y;

        $paras = preg_split("/\R/", $text) ?: [];
        foreach ($paras as $para) {
            $para = trim((string)$para);
            if ($para === '') {
                $yy -= $lineH;
                continue;
            }

            $words = preg_split('/\s+/', $para) ?: [];
            $line = '';
            foreach ($words as $word) {
                $test = $line === '' ? $word : ($line . ' ' . $word);
                $len = function_exists('mb_strlen') ? (int)mb_strlen($test) : (int)strlen($test);
                if ($len <= $maxChars) {
                    $line = $test;
                } else {
                    $out .= self::text($x, $yy, $font, $size, $line);
                    $yy -= $lineH;
                    $line = $word;
                }
            }
            if ($line !== '') {
                $out .= self::text($x, $yy, $font, $size, $line);
                $yy -= $lineH;
            }
            $yy -= (int)($lineH / 2);
        }

        return $out;
    }

    private static function rect(float $x, float $y, float $w, float $h, array $fillRgb, array $strokeRgb, float $lineW = 1.0): string
    {
        $fr = $fillRgb[0] ?? 1.0;
        $fg = $fillRgb[1] ?? 1.0;
        $fb = $fillRgb[2] ?? 1.0;
        $sr = $strokeRgb[0] ?? 0.8;
        $sg = $strokeRgb[1] ?? 0.8;
        $sb = $strokeRgb[2] ?? 0.8;
        return "{$fr} {$fg} {$fb} rg {$sr} {$sg} {$sb} RG {$lineW} w {$x} {$y} {$w} {$h} re B\n";
    }

    private static function jpegDimensions(string $bytes): array
    {
        $len = strlen($bytes);
        $i = 2; // after FF D8

        while ($i + 9 < $len) {
            if (ord($bytes[$i]) !== 0xFF) {
                $i++;
                continue;
            }
            $marker = ord($bytes[$i + 1]);

            // SOF markers
            if (in_array($marker, [0xC0,0xC1,0xC2,0xC3,0xC5,0xC6,0xC7,0xC9,0xCA,0xCB,0xCD,0xCE,0xCF], true)) {
                $h = (ord($bytes[$i + 5]) << 8) + ord($bytes[$i + 6]);
                $w = (ord($bytes[$i + 7]) << 8) + ord($bytes[$i + 8]);
                if ($w > 0 && $h > 0) {
                    return ['w' => $w, 'h' => $h];
                }
            }

            if ($i + 3 >= $len) {
                break;
            }
            $segLen = (ord($bytes[$i + 2]) << 8) + ord($bytes[$i + 3]);
            if ($segLen < 2) {
                break;
            }
            $i += 2 + $segLen;
        }

        return ['w' => 1, 'h' => 1];
    }

    private static function pngToRgbRaw(string $path, array &$meta): string
    {
        if (!function_exists('imagecreatefrompng')) {
            throw new \RuntimeException('GD Extension fehlt, PNG Signatur kann nicht verarbeitet werden.');
        }

        $im = @imagecreatefrompng($path);
        if (!$im) {
            throw new \RuntimeException('Signatur PNG konnte nicht gelesen werden.');
        }

        $w = imagesx($im);
        $h = imagesy($im);

        // White background to remove transparency
        $bg = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($bg, 255, 255, 255);
        imagefilledrectangle($bg, 0, 0, $w, $h, $white);
        imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
        imagedestroy($im);

        $data = '';
        for ($yy = 0; $yy < $h; $yy++) {
            for ($xx = 0; $xx < $w; $xx++) {
                $rgb = imagecolorat($bg, $xx, $yy);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $data .= chr($r) . chr($g) . chr($b);
            }
        }
        imagedestroy($bg);

        $meta = ['w' => $w, 'h' => $h];
        return $data;
    }

    public static function createMandatePdf(array $data, string $signaturePath, string $outPath): void
    {
        $pageW = 595.28; // A4
        $pageH = 841.89;

        $mLeft = 50.0;
        $mRight = 50.0;
        $boxPad = 14.0;
        $contentW = $pageW - $mLeft - $mRight;

        $cmd = '';

        // Header
        $cmd .= self::text($mLeft, 800, 'F2', 18, 'SEPA Lastschriftmandat');
        $cmd .= self::text($mLeft, 782, 'F1', 10, 'Dieses Dokument wurde online ausgefüllt und unterschrieben.');
        $cmd .= self::text($mLeft + 310, 800, 'F1', 10, 'Mandatsreferenz: ' . (string)($data['mandate_reference'] ?? ''));

        // Boxes
        $cmd .= self::rect($mLeft, 700, $contentW, 75, [0.97, 0.97, 0.97], [0.85, 0.85, 0.85], 1.0);
        $cmd .= self::rect($mLeft, 585, $contentW, 110, [1.0, 1.0, 1.0], [0.85, 0.85, 0.85], 1.0);
        $cmd .= self::rect($mLeft, 260, $contentW, 300, [1.0, 1.0, 1.0], [0.85, 0.85, 0.85], 1.0);
        $cmd .= self::rect($mLeft, 90, $contentW, 140, [0.97, 0.97, 0.97], [0.85, 0.85, 0.85], 1.0);

        // Creditor section
        $cmd .= self::text($mLeft + $boxPad, 760, 'F2', 12, 'Gläubiger');
        $cmd .= self::text($mLeft + $boxPad, 742, 'F1', 11, (string)($data['creditor_name'] ?? ''));
        $creditorAddressParts = [];
        $creditorStreet = trim((string)($data['creditor_street'] ?? ''));
        if ($creditorStreet !== '') {
            $creditorAddressParts[] = $creditorStreet;
        }
        $creditorCityLine = trim((string)($data['creditor_zip'] ?? '') . ' ' . (string)($data['creditor_city'] ?? ''));
        if ($creditorCityLine !== '') {
            $creditorAddressParts[] = $creditorCityLine;
        }
        $creditorCountry = trim((string)($data['creditor_country'] ?? ''));
        if ($creditorCountry !== '') {
            $creditorAddressParts[] = $creditorCountry;
        }
        if (!empty($creditorAddressParts)) {
            $cmd .= self::text($mLeft + $boxPad, 728, 'F1', 10, 'Adresse: ' . implode(', ', $creditorAddressParts));
        }

        $cmd .= self::text($mLeft + $boxPad, 714, 'F1', 10, 'Gläubiger Identifikationsnummer: ' . (string)($data['creditor_id'] ?? ''));
        $paymentType = (string)($data['payment_type'] ?? '');
        $paymentLabel = 'unbekannt';
        if ($paymentType === 'OOFF') {
            $paymentLabel = 'Einmalige Zahlung';
        } elseif ($paymentType === 'RCUR') {
            $paymentLabel = 'Wiederkehrende Zahlungen';
        }
        $cmd .= self::text($mLeft + $boxPad, 700, 'F1', 10, 'Zahlungsart: ' . $paymentLabel);

        // Debtor section
        $cmd .= self::text($mLeft + $boxPad, 675, 'F2', 12, 'Zahlungspflichtiger');
        $cmd .= self::text($mLeft + $boxPad, 655, 'F1', 11, (string)($data['debtor_name'] ?? ''));

        $y = 639;
        $street = trim((string)($data['debtor_street'] ?? ''));
        if ($street !== '') {
            $cmd .= self::text($mLeft + $boxPad, $y, 'F1', 10, $street);
            $y -= 14;
        }
        $cityLine = trim((string)($data['debtor_zip'] ?? '') . ' ' . (string)($data['debtor_city'] ?? ''));
        if ($cityLine !== '') {
            $cmd .= self::text($mLeft + $boxPad, $y, 'F1', 10, $cityLine);
            $y -= 14;
        }

        $cmd .= self::text($mLeft + $boxPad, $y, 'F1', 10, 'IBAN: ' . (string)($data['debtor_iban'] ?? ''));
        $y -= 14;

        $bic = trim((string)($data['debtor_bic'] ?? ''));
        if ($bic !== '') {
            $cmd .= self::text($mLeft + $boxPad, $y, 'F1', 10, 'BIC: ' . $bic);
        }

        // Legal text
        $cmd .= self::text($mLeft + $boxPad, 540, 'F2', 12, 'Ermächtigung und Hinweis');
        $body = 'Ich ermächtige ' . (string)($data['creditor_name'] ?? '') . ', Zahlungen von meinem Konto mittels SEPA-Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die von ' . (string)($data['creditor_name'] ?? '') . ' auf mein Konto gezogenen SEPA-Lastschriften einzulösen.' . "\n\n" .
            'Hinweis: Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.';
        $cmd .= self::multiText($mLeft + $boxPad, 520, $contentW - (2 * $boxPad), 'F1', 10, 14, $body);

        // Signature block
        $place = trim((string)($data['signed_place'] ?? ''));
        $dateRaw = trim((string)($data['signed_date'] ?? ''));
        $date = $dateRaw;
        if ($dateRaw !== '') {
            $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
            if ($dateObj instanceof \DateTimeImmutable) {
                $date = $dateObj->format('d.m.Y');
            }
        }
        $cmd .= self::text($mLeft + $boxPad, 205, 'F1', 11, 'Ort: ' . $place);
        $cmd .= self::text($mLeft + $boxPad, 185, 'F1', 11, 'Datum: ' . $date);

        // Signature line and label
        $sigBoxW = 210;
        $sigBoxX = $mLeft + $contentW - $sigBoxW - $boxPad;
        $sigBoxY = 145;
        $sigBoxH = 75;
        $cmd .= self::rect($sigBoxX, $sigBoxY, $sigBoxW, $sigBoxH, [1.0, 1.0, 1.0], [0.85, 0.85, 0.85], 0.6);
        $sigLineY = $sigBoxY + 12;
        $sigLabelY = $sigBoxY + 4;
        $cmd .= "0 0 0 RG 1 w {$sigBoxX} {$sigLineY} m " . ($sigBoxX + $sigBoxW) . " {$sigLineY} l S\n";
        $cmd .= self::text($sigBoxX, $sigLabelY, 'F1', 9, 'Unterschrift');

        $signedAtRaw = trim((string)($data['signed_at'] ?? ''));
        $signedAt = $signedAtRaw;
        if ($signedAtRaw !== '') {
            $signedAtObj = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $signedAtRaw);
            if ($signedAtObj instanceof \DateTimeImmutable) {
                $signedAt = $signedAtObj->format('d.m.Y H:i');
            }
        }
        $signedIp = trim((string)($data['signed_ip'] ?? ''));
        $signedUa = trim((string)($data['signed_user_agent'] ?? ''));
        if ($signedUa !== '' && strlen($signedUa) > 90) {
            $signedUa = substr($signedUa, 0, 87) . '...';
        }

        $auditY = 165;
        $cmd .= self::text($mLeft + $boxPad, $auditY, 'F2', 9, 'Nachweis Online-Unterschrift');
        $auditY -= 13;
        if ($signedAt !== '') {
            $cmd .= self::text($mLeft + $boxPad, $auditY, 'F1', 9, 'Zeitstempel: ' . $signedAt);
            $auditY -= 12;
        }
        $cmd .= self::text($mLeft + $boxPad, $auditY, 'F1', 9, 'Unterzeichner: ' . (string)($data['debtor_name'] ?? ''));
        $auditY -= 12;
        $cmd .= self::text($mLeft + $boxPad, $auditY, 'F1', 9, 'Mandatsreferenz: ' . (string)($data['mandate_reference'] ?? ''));
        $auditY -= 12;
        if ($signedIp !== '') {
            $cmd .= self::text($mLeft + $boxPad, $auditY, 'F1', 9, 'IP: ' . $signedIp);
            $auditY -= 12;
        }
        if ($signedUa !== '') {
            $cmd .= self::text($mLeft + $boxPad, $auditY, 'F1', 9, 'Browser: ' . $signedUa);
        }

        // Build image object
        $imgMeta = ['w' => 1, 'h' => 1];
        $imgObj = '';
        $imgDraw = ''; // drawing commands

        $sigExt = strtolower(pathinfo($signaturePath, PATHINFO_EXTENSION));
        if (in_array($sigExt, ['jpg', 'jpeg'], true)) {
            $jpegBytes = (string)@file_get_contents($signaturePath);
            if ($jpegBytes === '') {
                $jpegBytes = chr(0xFF).chr(0xD8).chr(0xFF).chr(0xD9);
            }
            $imgMeta = self::jpegDimensions($jpegBytes);
            $imgDict = "<< /Type /XObject /Subtype /Image /Width {$imgMeta['w']} /Height {$imgMeta['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegBytes) . " >>";
            $imgObj = $imgDict . "\nstream\n" . $jpegBytes . "\nendstream";
        } else {
            // PNG: try GD, otherwise embed 1x1 white pixel (no compression, no zlib required)
            $imgData = chr(0xFF).chr(0xFF).chr(0xFF);
            try {
                if (is_file($signaturePath)) {
                    $imgData = self::pngToRgbRaw($signaturePath, $imgMeta);
                }
            } catch (\Throwable $e) {
                // keep placeholder
            }
            $imgDict = "<< /Type /XObject /Subtype /Image /Width {$imgMeta['w']} /Height {$imgMeta['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($imgData) . " >>";
            $imgObj = $imgDict . "\nstream\n" . $imgData . "\nendstream";
        }

        // Only draw image if we have an image object
        $drawW = $sigBoxW - 20.0;
        $drawH = $sigBoxH - 30.0;
        $imgDraw = "q {$sigBoxX} {$sigBoxY} {$sigBoxW} {$sigBoxH} re W n {$drawW} 0 0 {$drawH} " . ($sigBoxX + 10) . " " . ($sigBoxY + 18) . " cm /Im1 Do Q\n";
        $cmd .= $imgDraw;

        // Content stream
        $contentObj = "<< /Length " . strlen($cmd) . " >>\nstream\n" . $cmd . "endstream";

        // Objects
        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";

        $resources = "<< /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> /F2 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >> >> /XObject << /Im1 5 0 R >> >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /Resources {$resources} /MediaBox [0 0 {$pageW} {$pageH}] /Contents 4 0 R >>";
        $objects[] = $contentObj;
        $objects[] = $imgObj;

        // Build PDF
        $pdf = "%PDF-1.4\n%" . chr(0xE2) . chr(0xE3) . chr(0xCF) . chr(0xD3) . "\n";
        $offsets = [0];

        for ($i = 0; $i < count($objects); $i++) {
            $num = $i + 1;
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        $dir = dirname($outPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents($outPath, $pdf);
    }
}
