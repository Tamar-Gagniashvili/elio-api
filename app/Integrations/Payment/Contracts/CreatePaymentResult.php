<?php

namespace App\Integrations\Payment\Contracts;

interface CreatePaymentResult
{
    public function externalId(): string;

    public function redirectUrl(): string;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
