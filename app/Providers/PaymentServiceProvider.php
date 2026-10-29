<?php

namespace App\Providers;

use App\Integrations\Payment\Bog\BogPaymentService;
use App\Integrations\Payment\Flitt\FlittPaymentService;
use App\Services\Payment\PaymentManager;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BogPaymentService::class);
        $this->app->singleton(FlittPaymentService::class);
        $this->app->singleton(PaymentManager::class);
    }

    public function boot(): void {}
}
