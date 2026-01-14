<?php
declare(strict_types=1);

namespace App\Support;

final class DateFormatter
{
    public static function toDisplay(?string $value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }

        $formats = [
            'Y-m-d',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sP',
        ];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('d.m.Y');
            }
        }

        $timestamp = strtotime($raw);
        if ($timestamp !== false) {
            return date('d.m.Y', $timestamp);
        }

        return $raw;
    }
}
