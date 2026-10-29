<?php

namespace App\Data\Sms;

final class SmsMessageData
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly SmsMessageAttributesData $attributes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            attributes: SmsMessageAttributesData::from((array) ($data['attributes'] ?? [])),
        );
    }
}
