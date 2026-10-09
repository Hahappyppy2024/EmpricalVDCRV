<?php
declare(strict_types=1);
namespace App\Infrastructure;

final class Mail
{
    public static function send(string $to, string $subject, string $body): void
    {
        $dir = Config::get('MAIL_PATH') ?: 'var/mail';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . substr(hash('sha256', $to . '|' . $subject), 0, 8) . '.txt';
        $content = "TO: {$to}\nSUBJECT: {$subject}\nDATE: " . date('c') . "\n\n{$body}\n";
        file_put_contents($file, $content);
    }
}