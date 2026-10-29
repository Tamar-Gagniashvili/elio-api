<?php

namespace App\Integrations\Payment\Bog\Data;

final class BogPurchaseUnitsData
{
    public function __construct(
        public readonly string $currency,
        public readonly float $totalAmount,
        public readonly string $productId,
        public readonly ?string $description = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'total_amount' => $this->totalAmount,
            // BOG requires at least one basket item; the whole payment is charged as one
            'basket' => [
                array_filter([
                    'product_id' => $this->productId,
                    'description' => $this->description,
                    'quantity' => 1,
                    'unit_price' => $this->totalAmount,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
        ];
    }
}
