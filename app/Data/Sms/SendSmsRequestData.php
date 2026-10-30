<?php

namespace App\Data\Sms;

final class SendSmsRequestData
{
    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly ?string $subject = null,
        public readonly bool $ignoreBlacklist = false,
        public readonly ?string $submitCallbackUrl = null,
        public readonly ?string $deliveryCallbackUrl = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            'ignore_blacklist' => $this->ignoreBlacklist,
            'submit_callback_url' => $this->submitCallbackUrl,
            'delivery_callback_url' => $this->deliveryCallbackUrl,
        ];
    }
}
