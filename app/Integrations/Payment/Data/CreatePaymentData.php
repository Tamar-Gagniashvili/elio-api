<?php

namespace App\Integrations\Payment\Data;

use App\Models\Payment;

final class CreatePaymentData
{
    public function __construct(
        public readonly string $orderId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $callbackUrl,
        public readonly string $redirectUrl,
        public readonly ?string $description = null,
        public readonly bool $saveCard = false,
    ) {}

    public static function fromPayment(Payment $payment, bool $saveCard = false, ?string $redirectUrl = null): self
    {
        $provider = config('payment.provider');

        return new self(
            orderId: (string) $payment->id,
            amount: (float) $payment->amount,
            currency: (string) config("payment.{$provider}.currency", 'GEL'),
            callbackUrl: route("payments.callbacks.{$provider}"),
            redirectUrl: $redirectUrl ?? (string) config('payment.redirect_url', '/'),
            description: $payment->description,
            saveCard: $saveCard,
        );
    }
}
