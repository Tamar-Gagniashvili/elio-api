<?php

namespace App\Integrations\Payment\Flitt\Data;

use App\Integrations\Payment\Data\CreatePaymentData;
use App\Integrations\Payment\Flitt\FlittSignature;

final class FlittRecurringRequestData
{
    public function __construct(
        public readonly string $order_id,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $rectoken,
        public readonly string $callback_url,
    ) {}

    public static function from(CreatePaymentData $data, string $rectoken): self
    {
        return new self(
            order_id: $data->orderId,
            amount: $data->amount,
            currency: $data->currency,
            description: $data->description ?? '',
            rectoken: $rectoken,
            callback_url: $data->callbackUrl,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $merchantId = (int) config('payment.flitt.merchant_id');

        $data = [
            'merchant_id' => $merchantId,
            'order_id' => $this->order_id,
            'order_desc' => $this->description,
            'amount' => (int) round($this->amount * 100),
            'currency' => $this->currency,
            'rectoken' => $this->rectoken,
            'server_callback_url' => $this->callback_url,
        ];

        $data['signature'] = app(FlittSignature::class)->generate($data);

        return $data;
    }
}
