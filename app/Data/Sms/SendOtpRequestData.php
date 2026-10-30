<?php

namespace App\Data\Sms;

final class SendOtpRequestData
{
    public function __construct(
        public readonly string $phone,
        public readonly ?string $subject = null,
        public readonly ?string $text = null,
        public readonly ?int $expiresIn = null,
        public readonly ?int $codeDigits = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'phone' => $this->phone,
            'subject' => $this->subject,
            'text' => $this->text,
            'expiresIn' => $this->expiresIn,
            'codeDigits' => $this->codeDigits,
        ], fn (mixed $value): bool => $value !== null);
    }
}
