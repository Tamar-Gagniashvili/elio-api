<?php

namespace App\Services\Payment;

use App\Data\Payment\BogCredentialsData;
use App\Data\Payment\FlittCredentialsData;
use App\Enums\PaymentProvider;
use App\Models\PaymentSettings;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Payment provider and merchant credentials. Inside a clinic (a current tenant) they come from
 * that clinic's settings only; outside one they come from config/payment.php.
 */
class PaymentSettingsService
{
    public static function clearCache(): void
    {
        Cache::forget(self::cacheKey());
    }

    public static function provider(): ?PaymentProvider
    {
        if (! Tenant::current()) {
            return PaymentProvider::tryFrom((string) config('payment.provider'));
        }

        return self::settings()?->provider;
    }

    public static function bog(): BogCredentialsData
    {
        if (! Tenant::current()) {
            return BogCredentialsData::from((array) config('payment.bog'));
        }

        return BogCredentialsData::from(self::settings()?->bog_settings);
    }

    public static function flitt(): FlittCredentialsData
    {
        if (! Tenant::current()) {
            return FlittCredentialsData::from((array) config('payment.flitt'));
        }

        return FlittCredentialsData::from(self::settings()?->flitt_settings);
    }

    /** Whether payments can be taken: a provider is chosen and its credentials are filled in. */
    public static function isConfigured(): bool
    {
        return match (self::provider()) {
            PaymentProvider::Bog => self::bog()->isComplete(),
            PaymentProvider::Flitt => self::flitt()->isComplete(),
            null => false,
        };
    }

    private static function settings(): ?PaymentSettings
    {
        // The cache only stores plain values (no objects), so the row's raw attributes are cached,
        // secrets still encrypted, and the model is rebuilt from them
        $load = fn () => PaymentSettings::first()?->getAttributes();
        $attributes = Cache::remember(self::cacheKey(), 3600, $load);

        // Entries cached as whole models before this format can't be read back; replace them
        if ($attributes !== null && ! is_array($attributes)) {
            Cache::forget(self::cacheKey());
            $attributes = Cache::remember(self::cacheKey(), 3600, $load);
        }

        return is_array($attributes) ? (new PaymentSettings)->newFromBuilder($attributes) : null;
    }

    private static function cacheKey(): string
    {
        return 'payment_settings.'.Tenant::current()?->id;
    }
}
