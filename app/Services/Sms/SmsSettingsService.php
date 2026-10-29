<?php

namespace App\Services\Sms;

use App\Data\Sms\InexPhoneSettingsData;
use App\Models\SmsSettings;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * The current clinic's SMS settings. Every clinic has its own InexPhone account, so settings
 * are read and cached per tenant.
 */
class SmsSettingsService
{
    public static function clearCache(): void
    {
        Cache::forget(self::cacheKey());
    }

    public static function getInexPhoneSettings(): ?InexPhoneSettingsData
    {
        $settings = self::cachedSettings();

        if (! $settings || ! $settings->inex_phone_settings) {
            return null;
        }

        return InexPhoneSettingsData::from($settings->inex_phone_settings);
    }

    /** The standard subject set in SMS settings, or null while none is configured. */
    public static function defaultSubject(): ?string
    {
        $subject = self::getInexPhoneSettings()?->defaultSubject;

        return filled($subject) ? $subject : null;
    }

    private static function cachedSettings(): ?SmsSettings
    {
        if (! Tenant::current()) {
            return null;
        }

        return Cache::remember(self::cacheKey(), 3600, fn () => SmsSettings::first());
    }

    private static function cacheKey(): string
    {
        return 'sms_settings.'.Tenant::current()?->id;
    }
}
