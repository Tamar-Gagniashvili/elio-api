<?php

namespace App\Actions;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Integrations\Payment\Bog\BogPaymentService;
use App\Models\Payment;
use Exception;

/**
 * Reads a payment's current state from its gateway, for when the callback has not arrived yet.
 * The payment's clinic must be current, as its merchant credentials are used.
 */
class SyncPaymentStatusAction
{
    public function __construct(
        private SyncFlittPaymentAction $syncFlittPayment,
        private BogPaymentService $bogService,
    ) {}

    /** @throws Exception */
    public function execute(Payment $payment): void
    {
        if (! $payment->external_id) {
            return;
        }

        match ($payment->provider) {
            PaymentProvider::Flitt => $this->syncFlittPayment->execute($payment),
            PaymentProvider::Bog => $this->syncBog($payment),
        };
    }

    /** @throws Exception */
    private function syncBog(Payment $payment): void
    {
        $details = $this->bogService->getPaymentDetails((string) $payment->external_id);
        $orderStatus = $details['order_status'] ?? null;
        $key = is_array($orderStatus) ? ($orderStatus['key'] ?? null) : null;

        if (is_string($key)) {
            // Read straight from BOG, so it is trusted as is
            $payment->update(['status' => PaymentStatus::bogStatus($key)]);
        }
    }
}
