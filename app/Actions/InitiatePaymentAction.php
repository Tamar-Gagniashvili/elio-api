<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Integrations\Payment\Data\CreatePaymentData;
use App\Models\Contact;
use App\Models\Payment;
use App\Services\Payment\PaymentManager;
use Illuminate\Database\Eloquent\Model;

class InitiatePaymentAction
{
    public function __construct(private PaymentManager $paymentManager) {}

    /**
     * Creates a payment with the configured gateway and returns the gateway's checkout URL.
     * $payable is what the payment is for (e.g. a booking); $redirectUrl is where the gateway
     * sends the customer afterwards, when it should not be the configured default.
     */
    public function execute(float $amount, ?string $description = null, ?Model $payable = null, ?Contact $contact = null, ?string $redirectUrl = null): string
    {
        $provider = $this->paymentManager->configuredProvider();

        $payment = Payment::create([
            'provider' => $provider,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
            'contact_id' => $contact?->id,
            'status' => PaymentStatus::Created,
            'description' => $description,
            'ccy' => (string) config("payment.{$provider->value}.currency", 'GEL'),
            'amount' => $amount,
        ]);

        $data = CreatePaymentData::fromPayment($payment, redirectUrl: $redirectUrl);
        $result = $this->paymentManager->createPayment($data);

        $payment->update([
            'external_id' => $result->externalId(),
            'request_payload' => $result->toArray(),
        ]);

        return $result->redirectUrl();
    }
}
