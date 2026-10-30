<?php

namespace App\Integrations\Payment\Flitt\Responses;

use App\Integrations\Payment\Contracts\CreatePaymentResult;

final class FlittCreatePaymentResult implements CreatePaymentResult
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public function externalId(): string
    {
        $orderId = $this->data['order_id'] ?? null;

        if ($orderId === null) {
            /** @var array<string, mixed> $response */
            $response = $this->data['response'] ?? [];
            $orderId = $response['order_id'] ?? null;
        }

        return is_string($orderId) ? $orderId : '';
    }

    public function redirectUrl(): string
    {
        /** @var array<string, mixed> $response */
        $response = $this->data['response'] ?? [];
        $url = $response['checkout_url'] ?? null;

        return is_string($url) ? $url : '';
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
