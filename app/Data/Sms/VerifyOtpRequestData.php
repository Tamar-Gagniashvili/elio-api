<?php

namespace App\Data\Sms;

final class VerifyOtpRequestData
{
    public function __construct(
        public readonly string $phone,
        public readonly string $code,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'phone' => $this->phone,
            'code' => $this->code,
        ];
    }
}
