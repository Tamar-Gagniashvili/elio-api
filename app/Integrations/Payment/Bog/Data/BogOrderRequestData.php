<?php

namespace App\Integrations\Payment\Bog\Data;

use App\Integrations\Payment\Data\CreatePaymentData;
use Illuminate\Support\Str;

final class BogOrderRequestData
{
    public function __construct(
        public readonly string $callbackUrl,
        public readonly string $externalOrderId,
        public readonly BogPurchaseUnitsData $purchaseUnits,
        public readonly BogRedirectUrlsData $redirectUrls,
    ) {}

    public static function fromCreatePaymentData(CreatePaymentData $data): self
    {
        return new self(
            callbackUrl: $data->callbackUrl,
            externalOrderId: Str::uuid()->toString(),
            purchaseUnits: new BogPurchaseUnitsData(
                currency: $data->currency,
                totalAmount: $data->amount,
            ),
            redirectUrls: new BogRedirectUrlsData(
                success: $data->redirectUrl,
                fail: $data->redirectUrl,
            ),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'callback_url' => $this->callbackUrl,
            'external_order_id' => $this->externalOrderId,
            'purchase_units' => $this->purchaseUnits->toArray(),
            'redirect_urls' => $this->redirectUrls->toArray(),
        ];
    }
}
