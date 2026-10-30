<?php

namespace App\Integrations\Payment\Bog\Data;

final class BogRedirectUrlsData
{
    public function __construct(
        public readonly ?string $success = null,
        public readonly ?string $fail = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'fail' => $this->fail,
        ];
    }
}
