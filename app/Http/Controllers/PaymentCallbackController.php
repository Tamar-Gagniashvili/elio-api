<?php

namespace App\Http\Controllers;

use App\Actions\ApplyFlittOrderAction;
use App\Actions\SyncFlittPaymentAction;
use App\Enums\PaymentStatus;
use App\Http\Requests\Payment\BogCallbackRequest;
use App\Http\Requests\Payment\FlittCallbackRequest;
use App\Integrations\Payment\Bog\BogPaymentService;
use App\Integrations\Payment\Flitt\Data\FlittOrderData;
use App\Integrations\Payment\Flitt\FlittSignature;
use App\Models\Payment;
use Exception;
use Illuminate\Http\Response;

/**
 * Gateway callbacks arrive without a tenant (no subdomain), so payments are looked up across
 * tenants by their gateway order id.
 */
class PaymentCallbackController extends Controller
{
    /**
     * A callback that would undo a settled payment may be stale or a genuine reversal, so the
     * order's current state is read from BOG instead.
     *
     * @throws Exception
     */
    public function bog(BogCallbackRequest $request): Response
    {
        /** @var array<string, mixed> $body */
        $body = $request->input('body');
        $orderId = $body['order_id'] ?? null;
        $orderStatus = $body['order_status'] ?? null;
        $orderStatusKey = is_array($orderStatus) ? ($orderStatus['key'] ?? null) : null;

        if (! is_string($orderId) || ! is_string($orderStatusKey)) {
            return response()->noContent(422);
        }

        $payment = Payment::where('external_id', $orderId)->first();

        if (! $payment) {
            return response()->noContent(404);
        }

        $status = PaymentStatus::bogStatus($orderStatusKey);

        if (! $payment->status->canTransitionTo($status)) {
            $status = $this->currentBogStatus($orderId);
        }

        $payment->update([
            'status' => $status,
            'callback_payload' => $request->all(),
        ]);

        return response()->noContent();
    }

    /**
     * A callback is applied as is only when it is correctly signed and does not undo a settled
     * payment. Otherwise it is treated as a hint that the order changed and its current state is
     * fetched from Flitt: a forged callback cannot mark a payment paid, a stale "declined" cannot
     * undo a real one, and a genuine reversal by the bank still gets through. A failed fetch
     * surfaces as a 500, which makes Flitt retry.
     *
     * @throws Exception
     */
    public function flitt(FlittCallbackRequest $request): Response
    {
        /** @var string $orderId */
        $orderId = $request->input('order_id');

        $payment = Payment::where('external_id', $orderId)->first();

        if (! $payment) {
            return response()->noContent(404);
        }

        $payload = $request->payload();
        $order = FlittOrderData::fromArray($payload);
        $status = PaymentStatus::flittStatus((string) $order->orderStatus, $order->reversalAmount);

        if (app(FlittSignature::class)->isValid($payload) && $payment->status->canTransitionTo($status)) {
            app(ApplyFlittOrderAction::class)->execute($payment, $order);

            return response()->noContent();
        }

        logger()->warning('Flitt callback not applied directly, checking order with Flitt', [
            'payment_id' => $payment->id,
            'current_status' => $payment->status->value,
            'order_status' => $order->orderStatus,
        ]);

        app(SyncFlittPaymentAction::class)->execute($payment);

        return response()->noContent();
    }

    /** @throws Exception */
    private function currentBogStatus(string $orderId): PaymentStatus
    {
        $details = app(BogPaymentService::class)->getPaymentDetails($orderId);
        $orderStatus = $details['order_status'] ?? null;
        $statusKey = is_array($orderStatus) ? ($orderStatus['key'] ?? null) : null;

        if (! is_string($statusKey)) {
            throw new Exception('BOG returned no order status for order '.$orderId);
        }

        return PaymentStatus::bogStatus($statusKey);
    }
}
