<?php

namespace App\Integrations\Payment\Bog\Responses;

use App\Integrations\Payment\Contracts\CreatePaymentResult;

final class BogCreatePaymentResult implements CreatePaymentResult
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public function externalId(): string
    {
        $id = $this->data['id'] ?? null;

        return is_string($id) ? $id : '';
    }

    public function redirectUrl(): string
    {
        /** @var array<string, mixed> $links */
        $links = $this->data['_links'] ?? [];
        /** @var array<string, mixed> $redirect */
        $redirect = $links['redirect'] ?? [];
        $href = $redirect['href'] ?? null;

        return is_string($href) ? $href : '';
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
