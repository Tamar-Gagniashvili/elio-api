<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentManager;

class RefundPaymentAction
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function execute(Payment $payment, ?float $amount = null): void
    {
        $this->paymentManager->service($payment->provider)->refundPayment((string) $payment->external_id, $amount);

        $refundAmount = $amount ?? (float) $payment->amount;
        $status = $refundAmount < (float) $payment->amount
            ? PaymentStatus::RefundedPartially
            : PaymentStatus::Refunded;

        $payment->update([
            'status' => $status,
            'refund_amount' => $refundAmount,
        ]);
    }
}
