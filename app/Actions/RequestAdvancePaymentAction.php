<?php

namespace App\Actions;

use App\Jobs\SendSms;
use App\Models\Booking;
use App\Models\PaymentSettings;
use Exception;

/**
 * Creates the advance payment for a session and texts the client the payment link.
 */
class RequestAdvancePaymentAction
{
    /** Charges the booking's own price */
    public const DEFAULT_MESSAGE = 'Hi {client}, your session with {specialist} is on {date} at {time}. Please pay {amount} GEL in advance: {link}';

    public function __construct(private InitiatePaymentAction $initiatePayment) {}

    /** @throws Exception when the gateway does not accept the payment; the booking is then left to retry */
    public function execute(Booking $booking, PaymentSettings $settings): void
    {
        $contact = $booking->contact;
        $amount = (float) $booking->price;
        $startsAt = $booking->starts_at->copy()->setTimezone(config('bookings.timezone'));

        $initiated = $this->initiatePayment->execute(
            amount: $amount,
            description: 'Session '.$startsAt->format('d.m.Y H:i'),
            payable: $booking,
            contact: $contact,
        );

        $message = self::render($settings->advance_payment_message ?: self::DEFAULT_MESSAGE, [
            'client' => $contact?->name ?: '',
            'specialist' => $booking->staffProfile?->full_name ?: '',
            'date' => $startsAt->format('d.m.Y'),
            'time' => $startsAt->format('H:i'),
            'amount' => number_format($amount, 2, '.', ''),
            'link' => $initiated->checkoutUrl,
        ]);

        SendSms::dispatch((string) $contact?->phone, $message, $contact?->id);

        $booking->update(['advance_payment_requested_at' => now()]);
    }

    /** @param array<string, string> $values */
    public static function render(string $template, array $values): string
    {
        $text = strtr($template, collect($values)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => $v])->all());

        // Tidy up after empty values, e.g. "Hi , your session"
        return trim(preg_replace(['/ +([,.!?])/', '/ {2,}/'], ['$1', ' '], $text));
    }
}
