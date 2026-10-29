<?php

namespace App\Data\Payment;

use App\Models\Payment;

final class InitiatedPaymentData
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string $checkoutUrl,
    ) {}
}
