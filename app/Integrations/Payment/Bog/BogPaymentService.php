<?php

namespace App\Integrations\Payment\Bog;

use App\Integrations\Payment\Bog\Data\BogOrderRequestData;
use App\Integrations\Payment\Bog\Responses\BogCreatePaymentResult;
use App\Integrations\Payment\Contracts\CreatePaymentResult;
use App\Integrations\Payment\Contracts\PaymentProviderContract;
use App\Integrations\Payment\Data\CreatePaymentData;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BogPaymentService implements PaymentProviderContract
{
    /** @throws Exception */
    public function createPayment(CreatePaymentData $data): CreatePaymentResult
    {
        $requestData = BogOrderRequestData::fromCreatePaymentData($data);

        $response = Http::withHeaders($this->defaultHeaders())
            ->timeout(30)
            ->post($this->baseUrl().'/payments/v1/ecommerce/orders', $requestData->toArray());

        if ($response->failed()) {
            logger('BOG API Error:', ['status' => $response->status(), 'body' => $response->body()]);
            throw new Exception('BOG payment creation failed: '.$response->body());
        }

        /** @var array<string, mixed> $responseData */
        $responseData = $response->json();

        return new BogCreatePaymentResult($responseData);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getPaymentDetails(string $externalId): array
    {
        $response = Http::withHeaders($this->defaultHeaders())
            ->timeout(30)
            ->get($this->baseUrl()."/payments/v1/receipt/{$externalId}");

        if ($response->failed()) {
            throw new Exception('BOG order details fetch failed: '.$response->body());
        }

        /** @var array<string, mixed> $result */
        $result = $response->json();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function refundPayment(string $externalId, ?float $amount = null): array
    {
        $body = $amount !== null ? ['amount' => $amount] : [];

        $response = Http::withHeaders($this->defaultHeaders())
            ->timeout(30)
            ->post($this->baseUrl()."/payments/v1/refund/{$externalId}", $body);

        if ($response->failed()) {
            throw new Exception('BOG refund failed: '.$response->body());
        }

        /** @var array<string, mixed> $result */
        $result = $response->json() ?? [];

        return $result;
    }

    public function isRefundSupported(): bool
    {
        return true;
    }

    public function isPartialRefundSupported(): bool
    {
        return true;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('payment.bog.api_url'), '/');
    }

    private function oauthUrl(): string
    {
        return rtrim((string) config('payment.bog.oauth_url'), '/');
    }

    private function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Language' => app()->getLocale() === 'ka' ? 'ka' : 'en',
            'Authorization' => 'Bearer '.$this->getAccessToken(),
        ];
    }

    private function getAccessToken(): string
    {
        /** @var string|null $cachedToken */
        $cachedToken = Cache::get('bog.auth.access_token');
        /** @var string|null $cachedExpiry */
        $cachedExpiry = Cache::get('bog.auth.expires_in');

        if (! $cachedToken || Carbon::now()->gt(Carbon::parse($cachedExpiry ?? ''))) {
            return $this->authenticate()['access_token'];
        }

        return $cachedToken;
    }

    /** @return array<string, string> */
    private function authenticate(): array
    {
        $clientId = (string) config('payment.bog.client_id');
        $clientSecret = (string) config('payment.bog.client_secret');

        $response = Http::acceptJson()
            ->asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($this->oauthUrl().'/auth/realms/bog/protocol/openid-connect/token', [
                'grant_type' => 'client_credentials',
            ]);

        /** @var string|null $rawToken */
        $rawToken = $response->json('access_token');
        $accessToken = $rawToken ?? '';

        /** @var int|null $rawExpiry */
        $rawExpiry = $response->json('expires_in');
        $expiresIn = Carbon::now()->addSeconds($rawExpiry ?? 0)->format('Y-m-d H:i:s');

        Cache::put('bog.auth.access_token', $accessToken);
        Cache::put('bog.auth.expires_in', $expiresIn);

        return ['access_token' => $accessToken, 'expires_in' => $expiresIn];
    }
}
