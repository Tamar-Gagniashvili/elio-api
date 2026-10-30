<?php

namespace App\Data\Sms;

final class SmsMessageAttributesData
{
    public function __construct(
        public readonly string $number,
        public readonly string $subject,
        public readonly string $message,
        public readonly int $status,
        public readonly int $parts,
        public readonly int $deliveryStatus,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            number: (string) ($data['number'] ?? ''),
            subject: (string) ($data['subject'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            status: (int) ($data['status'] ?? 0),
            parts: (int) ($data['parts'] ?? 0),
            deliveryStatus: (int) ($data['deliveryStatus'] ?? 0),
        );
    }
}
