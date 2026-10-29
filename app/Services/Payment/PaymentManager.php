<?php

namespace App\Services\Payment;

use App\Enums\PaymentProvider;
use App\Integrations\Payment\Bog\BogPaymentService;
use App\Integrations\Payment\Contracts\CreatePaymentResult;
use App\Integrations\Payment\Contracts\PaymentProviderContract;
use App\Integrations\Payment\Data\CreatePaymentData;
use App\Integrations\Payment\Flitt\FlittPaymentService;

class PaymentManager
{
    public function __construct(
        private BogPaymentService $bogService,
        private FlittPaymentService $flittService,
    ) {}

    public function createPayment(CreatePaymentData $data): CreatePaymentResult
    {
        return $this->service()->createPayment($data);
    }

    /** @return array<string, mixed> */
    public function getPaymentDetails(string $externalId): array
    {
        return $this->service()->getPaymentDetails($externalId);
    }

    /** @return array<string, mixed> */
    public function refundPayment(string $externalId, ?float $amount = null): array
    {
        return $this->service()->refundPayment($externalId, $amount);
    }

    public function isRefundSupported(): bool
    {
        return $this->service()->isRefundSupported();
    }

    public function isPartialRefundSupported(): bool
    {
        return $this->service()->isPartialRefundSupported();
    }

    public function configuredProvider(): PaymentProvider
    {
        return PaymentProvider::from((string) config('payment.provider'));
    }

    /** The service for the given provider, or for the configured one. */
    public function service(?PaymentProvider $provider = null): PaymentProviderContract
    {
        return match ($provider ?? $this->configuredProvider()) {
            PaymentProvider::Bog => $this->bogService,
            PaymentProvider::Flitt => $this->flittService,
        };
    }
}
