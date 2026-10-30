<?php

namespace App\Data\Sms;

final class InexPhoneSettingsData
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $apiUrl,
        public readonly string $defaultSubject,
        public readonly ?string $submitCallbackUrl = null,
        public readonly ?string $deliveryCallbackUrl = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            apiKey: (string) ($data['apiKey'] ?? ''),
            apiUrl: (string) ($data['apiUrl'] ?? ''),
            defaultSubject: (string) ($data['defaultSubject'] ?? ''),
            submitCallbackUrl: isset($data['submitCallbackUrl']) ? (string) $data['submitCallbackUrl'] : null,
            deliveryCallbackUrl: isset($data['deliveryCallbackUrl']) ? (string) $data['deliveryCallbackUrl'] : null,
        );
    }
}
