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

    /**
     * Gateways need a full URL to send the client back to: the configured one when it is a full
     * URL, otherwise Elio's own payment result page.
     */
    private static function defaultRedirectUrl(Payment $payment): string
    {
        $configured = config('payment.redirect_url');

        return is_string($configured) && filter_var($configured, FILTER_VALIDATE_URL)
            ? $configured
            : route('payments.return', $payment->id);
    }

    public static function fromPayment(Payment $payment, bool $saveCard = false, ?string $redirectUrl = null): self
    {
        $provider = $payment->provider->value;

        return new self(
            orderId: (string) $payment->id,
            amount: (float) $payment->amount,
            currency: $payment->ccy ?: (string) config("payment.{$provider}.currency", 'GEL'),
            callbackUrl: route("payments.callbacks.{$provider}"),
            redirectUrl: $redirectUrl ?? self::defaultRedirectUrl($payment),
            description: $payment->description,
            saveCard: $saveCard,
        );
    }
}
