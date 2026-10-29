<?php

namespace App\Data\Sms;

final class SmsResponseData
{
    public function __construct(
        public readonly ?string $message = null,
        public readonly ?SmsMessageData $data = null,
    ) {}

    /** @param array<string, mixed> $json */
    public static function from(array $json): self
    {
        return new self(
            message: isset($json['message']) ? (string) $json['message'] : null,
            data: isset($json['data']) && is_array($json['data'])
                ? SmsMessageData::from($json['data'])
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'data' => $this->data ? [
                'id' => $this->data->id,
                'type' => $this->data->type,
                'attributes' => [
                    'number' => $this->data->attributes->number,
                    'subject' => $this->data->attributes->subject,
                    'message' => $this->data->attributes->message,
                    'status' => $this->data->attributes->status,
                    'parts' => $this->data->attributes->parts,
                    'deliveryStatus' => $this->data->attributes->deliveryStatus,
                ],
            ] : null,
        ];
    }
}
