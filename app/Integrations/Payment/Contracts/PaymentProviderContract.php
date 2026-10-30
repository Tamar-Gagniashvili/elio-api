<?php

namespace App\Integrations\Payment\Contracts;

use App\Integrations\Payment\Data\CreatePaymentData;

interface PaymentProviderContract
{
    public function createPayment(CreatePaymentData $data): CreatePaymentResult;

    /** @return array<string, mixed> */
    public function getPaymentDetails(string $externalId): array;

    /** @return array<string, mixed> */
    public function refundPayment(string $externalId, ?float $amount = null): array;

    public function isRefundSupported(): bool;

    public function isPartialRefundSupported(): bool;
}
