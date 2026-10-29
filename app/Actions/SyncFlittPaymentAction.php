<?php

namespace App\Actions;

use App\Integrations\Payment\Flitt\Data\FlittOrderData;
use App\Integrations\Payment\Flitt\FlittPaymentService;
use App\Models\Payment;
use Exception;

/**
 * Asks Flitt for the current state of a payment's order and applies it, for when a callback
 * cannot be trusted or never arrived.
 */
class SyncFlittPaymentAction
{
    public function __construct(
        private FlittPaymentService $flittService,
        private ApplyFlittOrderAction $applyFlittOrderAction,
    ) {}

    /** @throws Exception */
    public function execute(Payment $payment): void
    {
        $details = $this->flittService->getPaymentDetails((string) $payment->external_id);
        $response = $details['response'] ?? null;

        if (! is_array($response) || ! is_string($response['order_status'] ?? null)) {
            throw new Exception('Flitt returned no order status for payment '.$payment->id);
        }

        /** @var array<string, mixed> $response */
        $this->applyFlittOrderAction->execute($payment, FlittOrderData::fromArray($response));
    }
}
