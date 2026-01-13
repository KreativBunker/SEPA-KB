<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\App;

final class CryptoService
{
    private function key(): string
    {
        $key = (string)App::config('app_key');
        if ($key === '') {
            throw new \RuntimeException('App key fehlt');
        }

        if (str_starts_with($key, 'base64:')) {
            $raw = base64_decode(substr($key, 7), true);
            if ($raw === false) {
                throw new \RuntimeException('App key ungültig');
            }
            return $raw;
        }

        return $key;
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encrypt fehlgeschlagen');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $ciphertext): string
    {
        $key = $this->key();
        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('Ciphertext ungültig');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Decrypt fehlgeschlagen');
        }
        return $plain;
    }
}
