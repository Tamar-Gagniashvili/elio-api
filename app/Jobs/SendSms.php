<?php

namespace App\Jobs;

use App\Data\Sms\SendSmsRequestData;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one SMS from the clinic that dispatched it (queued jobs keep their tenant).
 */
class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $phoneNumber,
        private readonly string $message,
        private readonly ?string $contactId = null,
    ) {}

    public function handle(SmsService $smsService): void
    {
        $settings = SmsSettingsService::getInexPhoneSettings();

        $smsService->sendSms(
            new SendSmsRequestData(
                phone: $this->phoneNumber,
                message: $this->message,
                subject: $settings?->defaultSubject,
                submitCallbackUrl: $settings?->submitCallbackUrl ?? route('sms.callbacks.submit'),
                deliveryCallbackUrl: $settings?->deliveryCallbackUrl ?? route('sms.callbacks.delivery'),
            ),
            $this->contactId,
        );
    }
}
