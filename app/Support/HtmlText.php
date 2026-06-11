<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Hilfsfunktionen für formatierte (HTML-)E-Mail-Texte aus dem WYSIWYG-Editor:
 * Whitelist-Sanitizing beim Speichern, Plaintext-Fallback für den Versand
 * und Konvertierung von Alttexten (reiner Text) in HTML.
 */
final class HtmlText
{
    private const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><s><strike><ul><ol><li><a><h2><h3><blockquote><div><span>';

    public static function isHtml(string $value): bool
    {
        return preg_match('/<[a-z][^>]*>/i', $value) === 1;
    }

    /**
     * Erlaubt nur einfache Formatierungs-Tags und entfernt sämtliche
     * Attribute (onclick, style, ...). Auf <a> bleibt ausschließlich
     * ein http(s)/mailto-href erhalten.
     */
    public static function sanitize(string $html): string
    {
        $html = strip_tags($html, self::ALLOWED_TAGS);

        $html = preg_replace_callback('/<a\b[^>]*>/i', static function (array $m): string {
            if (preg_match('/href\s*=\s*"([^"]*)"/i', $m[0], $h) || preg_match("/href\s*=\s*'([^']*)'/i", $m[0], $h)) {
                $href = trim($h[1]);
                if (preg_match('#^(https?:|mailto:)#i', $href)) {
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '">';
                }
            }
            return '<a>';
        }, $html) ?? '';

        $html = preg_replace('/<(?!a\b)([a-z0-9]+)\b[^>]*>/i', '<$1>', $html) ?? '';

        return trim($html);
    }

    /** Plaintext-Variante eines HTML-Texts (für text/plain-Mailteil und Logs). */
    public static function toPlain(string $html): string
    {
        $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\s*\/\s*(p|div|h[1-6]|blockquote)\s*>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\s*li\b[^>]*>/i', '- ', $text) ?? $text;
        $text = preg_replace('/<\s*\/\s*li\s*>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    /** Reinen Text (z.B. Alt-Bestand vor WYSIWYG) als HTML mit Zeilenumbrüchen. */
    public static function fromPlain(string $text): string
    {
        return nl2br(htmlspecialchars($text, ENT_QUOTES), false);
    }

    /** Liefert beide Varianten eines gespeicherten Felds (HTML oder Plaintext). */
    public static function variants(string $value): array
    {
        if (self::isHtml($value)) {
            return ['text' => self::toPlain($value), 'html' => $value];
        }
        return ['text' => $value, 'html' => self::fromPlain($value)];
    }
}
