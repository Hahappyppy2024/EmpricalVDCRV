<?php
declare(strict_types=1);

namespace Shop\Support;

final class Mailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        $pdo = \Shop\Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO outbox (channel, recipient, subject, body) VALUES (:c, :to, :sub, :body)'
        );
        $stmt->execute([
            ':c' => 'fixture-log',
            ':to' => $to,
            ':sub' => $subject,
            ':body' => $body,
        ]);

        $logPath = \Shop\Config::get('EMAIL_LOG_PATH', 'data/outbox.log');
        if (!str_starts_with($logPath, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $logPath)) {
            $logPath = \Shop\Config::get('APP_ROOT', dirname(__DIR__, 2)) . DIRECTORY_SEPARATOR . $logPath;
        }
        @file_put_contents(
            $logPath,
            sprintf("[%s] %s -> %s | %s\n", date('c'), $to, $subject, str_replace("\n", " ", $body)),
            FILE_APPEND
        );
    }
}