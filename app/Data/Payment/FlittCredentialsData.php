<?php

namespace App\Data\Payment;

final class FlittCredentialsData
{
    public function __construct(
        public readonly int $merchantId,
        public readonly string $secretKey,
    ) {}

    /** @param array<string, mixed>|null $data */
    public static function from(?array $data): self
    {
        return new self(
            merchantId: (int) ($data['merchant_id'] ?? 0),
            secretKey: (string) ($data['secret_key'] ?? ''),
        );
    }

    public function isComplete(): bool
    {
        return $this->merchantId > 0 && $this->secretKey !== '';
    }
}
