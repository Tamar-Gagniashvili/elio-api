<?php

namespace App\Data\Sms;

final class OtpResponseData
{
    /** @param array<int, OtpMessageData> $data */
    public function __construct(
        public readonly ?string $message = null,
        public readonly array $data = [],
    ) {}

    /** @param array<string, mixed> $json */
    public static function from(array $json): self
    {
        return new self(
            message: isset($json['message']) ? (string) $json['message'] : null,
            data: array_map(
                fn (array $entry): OtpMessageData => OtpMessageData::from($entry),
                (array) ($json['data'] ?? []),
            ),
        );
    }
}
