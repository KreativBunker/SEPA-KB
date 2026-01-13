<?php
declare(strict_types=1);

namespace App\Support;

final class Logger
{
    public static function error(string $message, ?\Throwable $e = null): void
    {
        $dir = App::basePath('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/app.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;

        if ($e) {
            $line .= "\n" . get_class($e) . ': ' . $e->getMessage();
            $line .= "\n" . $e->getFile() . ':' . $e->getLine();
            $line .= "\n" . $e->getTraceAsString();
        }

        $line .= "\n\n";
        @file_put_contents($file, $line, FILE_APPEND);
    }
}
