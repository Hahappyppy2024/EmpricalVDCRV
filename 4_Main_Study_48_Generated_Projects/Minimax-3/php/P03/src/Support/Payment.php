<?php
declare(strict_types=1);

namespace Shop\Support;

use Shop\Config;

final class Payment
{
    public static function charge(string $method, string $token, int $amountCents): array
    {
        $mode = Config::get('PAYMENT_FIXTURE_MODE', 'always_approve');
        $reference = 'PMT-' . strtoupper(bin2hex(random_bytes(4)));
        if ($mode === 'always_approve') {
            return [
                'ok' => true,
                'reference' => $reference,
                'method' => $method,
                'amount_cents' => $amountCents,
                'message' => 'Approved (fixture).',
            ];
        }
        if ($mode === 'always_decline') {
            return [
                'ok' => false,
                'reference' => $reference,
                'method' => $method,
                'amount_cents' => $amountCents,
                'message' => 'Declined (fixture).',
            ];
        }
        if ($token === 'tok_decline') {
            return [
                'ok' => false,
                'reference' => $reference,
                'method' => $method,
                'amount_cents' => $amountCents,
                'message' => 'Declined (fixture token).',
            ];
        }
        return [
            'ok' => true,
            'reference' => $reference,
            'method' => $method,
            'amount_cents' => $amountCents,
            'message' => 'Approved (fixture).',
        ];
    }
}