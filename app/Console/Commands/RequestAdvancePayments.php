<?php

namespace App\Console\Commands;

use App\Actions\RequestAdvancePaymentAction;
use App\Models\Booking;
use App\Models\PaymentSettings;
use App\Models\Tenant;
use App\Services\Payment\PaymentSettingsService;
use App\Services\Sms\SmsSettingsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * For clinics with pay in advance turned on, texts a payment link to clients whose session
 * starts within the clinic's chosen number of hours. Each booking is asked once.
 */
class RequestAdvancePayments extends Command
{
    protected $signature = 'bookings:request-advance-payments';

    protected $description = 'Text payment links to clients ahead of their sessions';

    public function handle(RequestAdvancePaymentAction $requestAdvancePayment): int
    {
        Tenant::query()->each(function (Tenant $tenant) use ($requestAdvancePayment) {
            $tenant->makeCurrent();

            try {
                $this->processTenant($tenant, $requestAdvancePayment);
            } finally {
                Tenant::forgetCurrent();
            }
        });

        return self::SUCCESS;
    }

    private function processTenant(Tenant $tenant, RequestAdvancePaymentAction $requestAdvancePayment): void
    {
        $settings = PaymentSettings::first();

        if (! $settings?->advance_payment_enabled) {
            return;
        }

        // Without a way to charge or to text, nothing is marked as sent, so it goes out once both work
        if (! PaymentSettingsService::isConfigured() || SmsSettingsService::getInexPhoneSettings() === null) {
            return;
        }

        Booking::query()
            ->with(['contact', 'staffProfile'])
            ->where('status', 'scheduled')
            ->whereNull('advance_payment_requested_at')
            // Only sessions with a price can be charged
            ->where('price', '>', 0)
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addHours($settings->advance_payment_hours_before))
            ->whereHas('contact', fn ($q) => $q->whereNotNull('phone')->where('phone', '!=', ''))
            ->each(function (Booking $booking) use ($requestAdvancePayment, $settings, $tenant) {
                try {
                    $requestAdvancePayment->execute($booking, $settings);
                    $this->line("Sent payment link for booking {$booking->id}");
                } catch (Throwable $e) {
                    report($e);
                    $this->warn("Could not request payment for booking {$booking->id} ({$tenant->slug}): {$e->getMessage()}");
                }
            });
    }
}
