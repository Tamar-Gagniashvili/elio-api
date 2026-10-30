<?php

namespace App\Exceptions;

use RuntimeException;

/** The clinic has not chosen a payment provider or entered its merchant details yet. */
class PaymentsNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Payments are not set up. Choose a provider and enter the merchant details in Settings.');
    }
}
