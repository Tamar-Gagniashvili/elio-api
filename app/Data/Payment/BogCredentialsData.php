<?php

namespace App\Data\Payment;

final class BogCredentialsData
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
    ) {}

    /** @param array<string, mixed>|null $data */
    public static function from(?array $data): self
    {
        return new self(
            clientId: (string) ($data['client_id'] ?? ''),
            clientSecret: (string) ($data['client_secret'] ?? ''),
        );
    }

    public function isComplete(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }
}
