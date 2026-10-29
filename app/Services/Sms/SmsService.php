<?php

namespace App\Services\Sms;

use App\Data\Sms\SendSmsRequestData;
use App\Data\Sms\SmsResponseData;
use App\Enums\SmsMessageStatus;
use App\Http\Integrations\Sms\Requests\SendSmsRequest;
use App\Http\Integrations\Sms\SmsConnector;
use App\Models\SmsMessage;
use Exception;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function __construct(protected SmsConnector $connector) {}

    /**
     * Sends one SMS with the current clinic's InexPhone account and records it. Returns null
     * when it was not sent, including while the clinic has no SMS settings.
     */
    public function sendSms(SendSmsRequestData $requestData, ?string $contactId = null): ?SmsResponseData
    {
        if (SmsSettingsService::getInexPhoneSettings() === null) {
            $this->storeSmsMessage($requestData, $contactId, SmsMessageStatus::NotSent, responsePayload: ['error' => 'SMS is not configured']);

            return null;
        }

        $request = new SendSmsRequest($requestData);

        try {
            $response = $this->connector->send($request);

            if ($response->failed()) {
                /** @var array<string, mixed>|null $errorPayload */
                $errorPayload = $response->json();
                $this->storeSmsMessage(
                    requestData: $requestData,
                    contactId: $contactId,
                    status: SmsMessageStatus::NotSent,
                    responsePayload: $errorPayload,
                );

                Log::error('Failed to send SMS', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'request' => $requestData->toArray(),
                ]);

                return null;
            }

            /** @var SmsResponseData $dto */
            $dto = $response->dto();

            $this->storeSmsMessage(
                requestData: $requestData,
                contactId: $contactId,
                status: SmsMessageStatus::Submitted,
                responseData: $dto,
                responsePayload: $dto->toArray(),
            );

            return $dto;
        } catch (Exception $e) {
            $this->storeSmsMessage(
                requestData: $requestData,
                contactId: $contactId,
                status: SmsMessageStatus::NotSent,
                responsePayload: ['error' => $e->getMessage()],
            );

            Log::error('Failed to send SMS', [
                'error' => $e->getMessage(),
                'request' => $requestData->toArray(),
            ]);

            return null;
        }
    }

    /** @param array<string, mixed>|null $responsePayload */
    private function storeSmsMessage(
        SendSmsRequestData $requestData,
        ?string $contactId,
        SmsMessageStatus $status,
        ?SmsResponseData $responseData = null,
        ?array $responsePayload = null,
    ): void {
        SmsMessage::create([
            'contact_id' => $contactId,
            'uuid' => $responseData?->data?->id,
            'phone_number' => $requestData->phone,
            'message' => $requestData->message,
            'sms_message_status' => $status,
            'parts_submitted' => $responseData ? 0 : null,
            'parts_delivered' => $responseData ? 0 : null,
            'total_parts' => $responseData?->data?->attributes->parts,
            'response_payload' => $responsePayload,
        ]);
    }
}
