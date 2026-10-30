<?php

namespace Tests\Feature;

use App\Enums\SmsProvider;
use App\Models\PaymentSettings;
use App\Models\SmsSettings;
use App\Models\Tenant;
use App\Services\Payment\PaymentSettingsService;
use App\Services\Sms\SmsSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Production caches serialize values and refuse to unserialize objects
 * (cache.serializable_classes = false), so settings must survive a real round trip.
 */
class SettingsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database', 'cache.serializable_classes' => false]);
        Tenant::create(['name' => 'Clinic', 'slug' => 'clinic'])->makeCurrent();
    }

    public function test_sms_settings_are_read_back_from_the_cache(): void
    {
        SmsSettings::create([
            'sms_provider' => SmsProvider::InexPhone,
            'inex_phone_settings' => ['apiKey' => 'key', 'apiUrl' => 'https://sms.test', 'defaultSubject' => 'Clinic'],
        ]);

        $this->assertSame('Clinic', SmsSettingsService::getInexPhoneSettings()?->defaultSubject);
        // Second read comes from the cache
        $this->assertSame('key', SmsSettingsService::getInexPhoneSettings()?->apiKey);
        $this->assertStringNotContainsString('key', (string) json_encode(Cache::get('sms_settings.'.Tenant::current()->id)));
    }

    public function test_payment_settings_are_read_back_from_the_cache(): void
    {
        PaymentSettings::create(['provider' => 'flitt', 'flitt_settings' => ['merchant_id' => 7, 'secret_key' => 'secret']]);

        $this->assertTrue(PaymentSettingsService::isConfigured());
        $this->assertSame(7, PaymentSettingsService::flitt()->merchantId);
        $this->assertSame('flitt', PaymentSettingsService::provider()?->value);
    }

    public function test_replaces_settings_cached_as_a_whole_model(): void
    {
        $settings = SmsSettings::create([
            'sms_provider' => SmsProvider::InexPhone,
            'inex_phone_settings' => ['apiKey' => 'key', 'apiUrl' => 'https://sms.test', 'defaultSubject' => 'Clinic'],
        ]);
        // How the previous version cached it
        Cache::put('sms_settings.'.Tenant::current()->id, $settings, 3600);

        $this->assertSame('Clinic', SmsSettingsService::getInexPhoneSettings()?->defaultSubject);
    }

    public function test_missing_settings_are_cached_as_none(): void
    {
        $this->assertNull(SmsSettingsService::getInexPhoneSettings());
        $this->assertNull(SmsSettingsService::getInexPhoneSettings());
        $this->assertFalse(PaymentSettingsService::isConfigured());
    }
}
