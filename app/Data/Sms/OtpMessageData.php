<?php

namespace App\Data\Sms;

final class OtpMessageData
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $type,
        public readonly OtpAttributesData $attributes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            type: (string) ($data['type'] ?? ''),
            attributes: OtpAttributesData::from((array) ($data['attributes'] ?? [])),
        );
    }
}
