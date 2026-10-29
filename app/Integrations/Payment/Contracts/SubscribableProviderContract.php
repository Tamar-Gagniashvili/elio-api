<?php

namespace App\Integrations\Payment\Contracts;

use App\Integrations\Payment\Data\CreatePaymentData;

interface SubscribableProviderContract
{
    public function chargeRecurring(string $token, CreatePaymentData $data): CreatePaymentResult;
}
