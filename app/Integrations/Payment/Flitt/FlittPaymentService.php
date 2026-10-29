<?php

namespace App\Integrations\Payment\Flitt;

use App\Integrations\Payment\Contracts\CreatePaymentResult;
use App\Integrations\Payment\Contracts\PaymentProviderContract;
use App\Integrations\Payment\Contracts\SubscribableProviderContract;
use App\Integrations\Payment\Data\CreatePaymentData;
use App\Integrations\Payment\Flitt\Data\FlittOrderRequestData;
use App\Integrations\Payment\Flitt\Data\FlittRecurringRequestData;
use App\Integrations\Payment\Flitt\Responses\FlittCreatePaymentResult;
use Exception;
use Illuminate\Support\Facades\Http;

class FlittPaymentService implements PaymentProviderContract, SubscribableProviderContract
{
    /** @throws Exception */
    public function createPayment(CreatePaymentData $data): CreatePaymentResult
    {
        $requestData = FlittOrderRequestData::fromCreatePaymentData($data);

        $response = Http::withHeaders($this->defaultHeaders())
            ->timeout(30)
            ->post($this->baseUrl().'/api/checkout/url', ['request' => $requestData->toArray()]);

        if ($response->failed()) {
            logger('Flitt API Error:', ['status' => $response->status(), 'body' => $response->body()]);
            throw new Exception('Flitt payment creation failed: '.$response->body());
        }

        /** @var array<string, mixed> $result */
        $result = $response->json();

        /** @var array<string, mixed> $responseData */
        $responseData = $result['response'] ?? [];

        if (($responseData['response_status'] ?? '') === 'failure') {
            $errorMessage = is_string($responseData['error_message'] ?? null) ? $responseData['error_message'] : 'Unknown error';
            $errorCode = is_string($responseData['error_code'] ?? null) ? $responseData['error_code'] : 'N/A';

            logger('Flitt API Error:', ['error_code' => $errorCode, 'error_message' => $errorMessage]);
            throw new Exception("Flitt payment failed: {$errorMessage} (Code: {$errorCode})");
        }

        $result['order_id'] = $requestData->order_id;

        if (isset($result['response']) && is_array($result['response'])) {
            /** @var array<string, mixed> $resultResponse */
            $resultResponse = $result['response'];
            $resultResponse['order_id'] = $requestData->order_id;
            $result['response'] = $resultResponse;
        }

        return new FlittCreatePaymentResult($result);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getPaymentDetails(string $externalId): array
    {
        $merchantId = (int) config('payment.flitt.merchant_id');

        $data = [
            'order_id' => $externalId,
            'merchant_id' => $merchantId,
        ];

        $data['signature'] = app(FlittSignature::class)->generate($data);

        $response = Http::withHeaders($this->defaultHeaders())
            ->timeout(30)
            ->post($this->baseUrl().'/api/status/order_id', ['request' => $data]);

        if ($response->failed()) {
            throw new Exception('Flitt payment details fetch failed: '.$response->body());
        }

        /** @var array<string, mixed> $result */
        $result = $response->json();

        return $result;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function refundPayment(string $externalId, ?float $amount = null): array
    {
        $amountMinor = $this->resolveAmountMinor($externalId, $amount);

        $merchantId = (int) config('payment.flitt.merchant_id');

        $data = [
            'order_id' => $externalId,
            'merchant_id' => $merchantId,
            'amount' => $amountMinor,
        ];

        $data['signature'] = app(FlittSignature::class)->generate($data);

        $response = Http::withHeaders($this->defaultHeaders())
            ->timeout(30)
            ->post($this->baseUrl().'/api/reverse/order_id', ['request' => $data]);

        if ($response->failed()) {
            throw new Exception('Flitt refund failed: '.$response->body());
        }

        /** @var array<string, mixed> $result */
        $result = $response->json();

        /** @var array<string, mixed> $responseData */
        $responseData = $result['response'] ?? [];

        if (($responseData['response_status'] ?? '') !== 'success') {
            $description = is_string($responseData['response_description'] ?? null)
                ? $responseData['response_description']
                : 'Unknown error';
            throw new Exception('Flitt refund failed: '.$description);
        }

        return $result;
    }

    /** @throws Exception */
    public function chargeRecurring(string $token, CreatePaymentData $data): CreatePaymentResult
    {
        $requestData = FlittRecurringRequestData::from($data, $token);

        $response = Http::withHeaders($this->defaultHeaders())
            ->timeout(30)
            ->post($this->baseUrl().'/api/recurring', ['request' => $requestData->toArray()]);

        if ($response->failed()) {
            logger('Flitt Recurring API Error:', ['status' => $response->status(), 'body' => $response->body()]);
            throw new Exception('Flitt recurring charge failed: '.$response->body());
        }

        /** @var array<string, mixed> $result */
        $result = $response->json();

        /** @var array<string, mixed> $responseData */
        $responseData = $result['response'] ?? [];

        if (($responseData['response_status'] ?? '') === 'failure') {
            $errorMessage = is_string($responseData['error_message'] ?? null) ? $responseData['error_message'] : 'Unknown error';
            throw new Exception('Flitt recurring charge failed: '.$errorMessage);
        }

        $result['order_id'] = $requestData->order_id;

        if (isset($result['response']) && is_array($result['response'])) {
            /** @var array<string, mixed> $resultResponse */
            $resultResponse = $result['response'];
            $resultResponse['order_id'] = $requestData->order_id;
            $result['response'] = $resultResponse;
        }

        return new FlittCreatePaymentResult($result);
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
        return rtrim((string) config('payment.flitt.api_url'), '/');
    }

    private function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Accept-Language' => app()->getLocale() === 'ka' ? 'ka' : 'en',
        ];
    }

    /** @throws Exception */
    private function resolveAmountMinor(string $externalId, ?float $amount): int
    {
        if ($amount !== null) {
            return (int) round($amount * 100);
        }

        $orderDetails = $this->getPaymentDetails($externalId);

        /** @var array<string, mixed> $orderResponse */
        $orderResponse = $orderDetails['response'] ?? [];
        $actualAmount = $orderResponse['actual_amount'] ?? null;

        if (! is_numeric($actualAmount)) {
            throw new Exception('Could not determine order amount for Flitt full refund.');
        }

        return (int) $actualAmount;
    }
}
