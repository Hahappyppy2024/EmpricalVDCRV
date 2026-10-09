<?php
declare(strict_types=1);
namespace App\Infrastructure;

final class Token
{
    public static function key(): string
    {
        $raw = (string)(Config::get('APP_KEY') ?: 'demo-key-please-replace-32-bytes');
        $bin = base64_decode($raw, true);
        if (!is_string($bin) || strlen($bin) !== 32) {
            $bin = substr(hash('sha256', $raw, true), 0, 32);
        }
        return $bin;
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $enc): ?string
    {
        $bin = base64_decode($enc, true);
        if (!is_string($bin) || strlen($bin) < 28) {
            return null;
        }
        $iv = substr($bin, 0, 12);
        $tag = substr($bin, 12, 16);
        $cipher = substr($bin, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }
}