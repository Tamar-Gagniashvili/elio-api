<?php

namespace App\Http\Controllers;

use App\Actions\InitiatePaymentAction;
use App\Exceptions\PaymentsNotConfiguredException;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Payment;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->latest();

        foreach (['contact_id', 'status', 'payable_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return response()->json($query->limit(200)->get()->makeHidden(['request_payload', 'response_payload', 'callback_payload']));
    }

    /** Starts a payment with the clinic's provider and returns the checkout link to send the client. */
    public function store(Request $request, InitiatePaymentAction $initiatePayment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'description' => ['nullable', 'string', 'max:255'],
            'contact_id' => ['nullable', 'uuid'],
            'booking_id' => ['nullable', 'uuid'],
            'redirect_url' => ['nullable', 'url', 'max:500'],
        ]);

        // Both are tenant-scoped, so another clinic's ids are not found
        $booking = isset($validated['booking_id']) ? Booking::findOrFail($validated['booking_id']) : null;
        $contact = isset($validated['contact_id'])
            ? Contact::findOrFail($validated['contact_id'])
            : $booking?->contact;

        try {
            $initiated = $initiatePayment->execute(
                amount: (float) $validated['amount'],
                description: $validated['description'] ?? null,
                payable: $booking,
                contact: $contact,
                redirectUrl: $validated['redirect_url'] ?? null,
            );
        } catch (PaymentsNotConfiguredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            report($e);

            return response()->json(['message' => 'The payment provider did not accept the payment. Check the merchant details in Settings.'], 502);
        }

        return response()->json([
            'id' => $initiated->payment->id,
            'status' => $initiated->payment->status,
            'amount' => $initiated->payment->amount,
            'checkout_url' => $initiated->checkoutUrl,
        ], 201);
    }
}
