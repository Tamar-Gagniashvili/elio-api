<?php

namespace App\Integrations\Payment\Flitt\Data;

/**
 * The state of an order as Flitt reports it, whether in a callback or in an order status response.
 */
final class FlittOrderData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly ?string $orderStatus,
        public readonly float $reversalAmount,
        public readonly ?string $rectoken,
        public readonly ?string $maskedCard,
        public readonly array $payload,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $reversalAmount = $payload['reversal_amount'] ?? null;

        return new self(
            orderStatus: self::filledString($payload['order_status'] ?? null),
            // Flitt reports amounts in minor units (tetri).
            reversalAmount: is_numeric($reversalAmount) ? round((float) $reversalAmount / 100, 2) : 0.0,
            rectoken: self::filledString($payload['rectoken'] ?? null),
            maskedCard: self::filledString($payload['masked_card'] ?? null),
            payload: $payload,
        );
    }

    private static function filledString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
