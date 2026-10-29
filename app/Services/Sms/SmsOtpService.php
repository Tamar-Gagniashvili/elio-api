<?php

namespace App\Services\Sms;

use App\Data\Sms\SendOtpRequestData;
use App\Data\Sms\VerifyOtpRequestData;
use App\Http\Integrations\Sms\Requests\SendOtpRequest;
use App\Http\Integrations\Sms\Requests\VerifyOtpRequest;
use App\Http\Integrations\Sms\SmsConnector;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends and verifies OTP codes via Inex Phone's dedicated /otp/send and /otp/verify
 * endpoints. The provider generates, delivers, and tracks expiry of the code itself — this app
 * never sees or stores the code, unlike the bulk/template SMS domain (SmsService).
 */
class SmsOtpService
{
    public function __construct(protected SmsConnector $connector) {}

    /** $text must contain {{CODE}}, where InexPhone puts the code. */
    public function send(string $phone, string $text): bool
    {
        // The subject/sender ID must be pre-registered with Inex — reuse the account's already
        // approved defaultSubject (same one the bulk/template SMS domain sends under) rather than
        // an arbitrary string, which the provider rejects with 403 "Specified subject is not
        // allowed".
        $settings = SmsSettingsService::getInexPhoneSettings();

        $request = new SendOtpRequest(new SendOtpRequestData(
            phone: $phone,
            subject: $settings?->defaultSubject,
            text: $text,
            expiresIn: 300,
        ));

        try {
            $response = $this->connector->send($request);

            if ($response->failed()) {
                Log::error('Failed to send OTP SMS', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send OTP SMS', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function verify(string $phone, string $code): bool
    {
        $request = new VerifyOtpRequest(new VerifyOtpRequestData(phone: $phone, code: $code));

        try {
            return $this->connector->send($request)->successful();
        } catch (Throwable $e) {
            Log::error('Failed to verify OTP code', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
