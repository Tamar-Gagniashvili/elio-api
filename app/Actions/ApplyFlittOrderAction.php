<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Integrations\Payment\Flitt\Data\FlittOrderData;
use App\Models\Payment;

class ApplyFlittOrderAction
{
    public function execute(Payment $payment, FlittOrderData $order): void
    {
        if ($order->orderStatus === null) {
            return;
        }

        $update = [
            'status' => PaymentStatus::flittStatus($order->orderStatus, $order->reversalAmount),
            'callback_payload' => $order->payload,
        ];

        if ($order->reversalAmount > 0) {
            $update['refund_amount'] = $order->reversalAmount;
        }

        $payment->update($update);
    }
}
