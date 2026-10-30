<?php

namespace App\Actions;

use App\Data\Payment\InitiatedPaymentData;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentsNotConfiguredException;
use App\Integrations\Payment\Data\CreatePaymentData;
use App\Models\Contact;
use App\Models\Payment;
use App\Services\Payment\PaymentManager;
use Exception;
use Illuminate\Database\Eloquent\Model;

class InitiatePaymentAction
{
    public function __construct(private PaymentManager $paymentManager) {}

    /**
     * Creates a payment with the clinic's gateway and returns it with the gateway's checkout URL.
     * $payable is what the payment is for (e.g. a booking); $redirectUrl is where the gateway
     * sends the customer afterwards, when it should not be the configured default.
     *
     * @throws PaymentsNotConfiguredException
     * @throws Exception when the gateway rejects the order; the payment is then marked rejected
     */
    public function execute(float $amount, ?string $description = null, ?Model $payable = null, ?Contact $contact = null, ?string $redirectUrl = null): InitiatedPaymentData
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

        try {
            $result = $this->paymentManager->createPayment($data);
        } catch (Exception $e) {
            $payment->update(['status' => PaymentStatus::Rejected, 'response_payload' => ['error' => $e->getMessage()]]);

            throw $e;
        }

        $payment->update([
            'external_id' => $result->externalId(),
            'request_payload' => $result->toArray(),
        ]);

        return new InitiatedPaymentData($payment, $result->redirectUrl());
    }
}
