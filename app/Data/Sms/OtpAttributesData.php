<?php

namespace App\Data\Sms;

final class OtpAttributesData
{
    public function __construct(
        public readonly string $phone,
        public readonly string $subject,
        public readonly string $createdAt,
        public readonly string $expiresAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            phone: (string) ($data['phone'] ?? ''),
            subject: (string) ($data['subject'] ?? ''),
            createdAt: (string) ($data['createdAt'] ?? ''),
            expiresAt: (string) ($data['expiresAt'] ?? ''),
        );
    }
}
