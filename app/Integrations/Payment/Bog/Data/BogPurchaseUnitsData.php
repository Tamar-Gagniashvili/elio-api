<?php

namespace App\Integrations\Payment\Bog\Data;

final class BogPurchaseUnitsData
{
    public function __construct(
        public readonly string $currency,
        public readonly float $totalAmount,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'total_amount' => $this->totalAmount,
        ];
    }
}
