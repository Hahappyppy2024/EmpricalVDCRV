<?php

declare(strict_types=1);

namespace Shop;

/**
 * Deterministic local payment simulator.
 *
 *  - Any card ending in "0002" is declined (documented test fixture).
 *  - All other cards are approved and produce a stable reference number.
 */
final class PaymentService
{
    public const DECLINE_SUFFIX = '0002';

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $payment
     * @return array{success:bool,status:string,reference:string,message:string}
     */
    public function charge(array $order, array $payment): array
    {
        $card = preg_replace('/\s+/', '', (string) ($payment['card_number'] ?? ''));
        $reference = 'PAY-' . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT);

        if ($card === '' || strlen($card) < 13 || strlen($card) > 19) {
            throw new ValidationException(['card_number' => 'A valid card number is required.']);
        }
        if (substr($card, -4) === self::DECLINE_SUFFIX) {
            return [
                'success' => false,
                'status' => 'declined',
                'reference' => '',
                'message' => 'Payment was declined. Cards ending in ' . self::DECLINE_SUFFIX . ' are rejected by the test simulator.',
            ];
        }
        return [
            'success' => true,
            'status' => 'approved',
            'reference' => $reference,
            'message' => 'Payment approved.',
        ];
    }
}
