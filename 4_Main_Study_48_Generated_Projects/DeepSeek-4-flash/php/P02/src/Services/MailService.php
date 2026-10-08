<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic local mail adapter. Messages are appended to a text log file
 * (storage/mail.log). No external mail service is used.
 */
final class MailService
{
    public function __construct(private readonly string $logFile)
    {
    }

    public function send(string $to, string $subject, string $body): array
    {
        $line = sprintf(
            "[%s] TO:%s SUBJECT:%s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_repeat('-', 60),
            $body
        );
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->logFile, $line, FILE_APPEND);

        return [
            'delivered' => true,
            'to' => $to,
            'subject' => $subject,
            'message_id' => hash('sha256', $to . $subject . $line),
        ];
    }
}
