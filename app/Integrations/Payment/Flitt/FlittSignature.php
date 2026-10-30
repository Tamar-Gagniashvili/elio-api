<?php

namespace App\Integrations\Payment\Flitt;

use App\Services\Payment\PaymentSettingsService;

/**
 * Flitt signs requests, responses and callbacks the same way: sha1 of the secret key followed by
 * every non-empty parameter value, ordered by parameter name and joined with "|".
 *
 * @see https://docs.flitt.com/api/building-signature/
 */
class FlittSignature
{
    /** @param array<string, mixed> $params */
    public function generate(array $params): string
    {
        ksort($params);

        $values = array_filter(
            array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $params),
            fn (string $value): bool => $value !== '',
        );

        return sha1(implode('|', [$this->secretKey(), ...$values]));
    }

    /** @param array<string, mixed> $payload */
    public function isValid(array $payload): bool
    {
        $signature = $payload['signature'] ?? null;

        if (! is_string($signature) || $signature === '' || $this->secretKey() === '') {
            return false;
        }

        unset($payload['signature'], $payload['response_signature_string']);

        return hash_equals($this->generate($payload), $signature);
    }

    private function secretKey(): string
    {
        return PaymentSettingsService::flitt()->secretKey;
    }
}
