<?php

namespace App\Integrations\Payment\Flitt\Data;

use App\Integrations\Payment\Data\CreatePaymentData;
use App\Integrations\Payment\Flitt\FlittSignature;
use App\Services\Payment\PaymentSettingsService;

final class FlittOrderRequestData
{
    public function __construct(
        public readonly string $order_id,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $redirect_url,
        public readonly string $callback_url,
        public readonly string $description,
        public readonly bool $save_card = false,
        public readonly string $language = 'en',
    ) {}

    public static function fromCreatePaymentData(CreatePaymentData $data): self
    {
        return new self(
            order_id: $data->orderId,
            amount: $data->amount,
            currency: $data->currency,
            redirect_url: $data->redirectUrl,
            callback_url: $data->callbackUrl,
            description: $data->description ?? '',
            save_card: $data->saveCard,
            language: app()->getLocale(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $merchantId = PaymentSettingsService::flitt()->merchantId;

        $data = [
            'merchant_id' => $merchantId,
            'order_id' => $this->order_id,
            'order_desc' => $this->description,
            'amount' => (int) round($this->amount * 100),
            'currency' => $this->currency,
            'server_callback_url' => $this->callback_url,
            'response_url' => $this->redirect_url,
            'lang' => $this->language,
        ];

        if ($this->save_card) {
            $data['required_rectoken'] = 'y';
        }

        $data['signature'] = app(FlittSignature::class)->generate($data);

        return $data;
    }
}
