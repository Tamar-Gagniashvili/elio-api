<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\SmsSettings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'http://clinic.localhost/api/sms-settings';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Clinic', 'slug' => 'clinic']);
        $this->tenant->makeCurrent();
    }

    private function actingAsMember(string $role = 'admin'): void
    {
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'role' => $role, 'is_active' => true]);
        Sanctum::actingAs($user);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'api_key' => 'secret-key',
            'api_url' => 'https://smsservice.inexphone.ge/api/v1',
            'default_subject' => 'Clinic',
            ...$overrides,
        ];
    }

    public function test_requires_login(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->putJson(self::URL, $this->payload())->assertUnauthorized();
    }

    public function test_only_clinic_admins_can_manage_sms_settings(): void
    {
        $this->actingAsMember('sales');

        $this->getJson(self::URL)->assertForbidden();
        $this->putJson(self::URL, $this->payload())->assertForbidden();
    }

    public function test_defaults_to_the_inexphone_api_url(): void
    {
        $this->actingAsMember();

        $this->getJson(self::URL)->assertOk()->assertJson(['is_configured' => false, 'api_url' => 'https://smsservice.inexphone.ge/api/v1']);

        $this->putJson(self::URL, $this->payload(['api_url' => null]))
            ->assertOk()
            ->assertJson(['is_configured' => true, 'api_url' => 'https://smsservice.inexphone.ge/api/v1']);
    }

    public function test_saves_settings_without_ever_returning_the_api_key(): void
    {
        $this->actingAsMember();

        $this->putJson(self::URL, $this->payload())
            ->assertOk()
            ->assertJson(['sms_provider' => 'inex_phone', 'has_api_key' => true, 'default_subject' => 'Clinic'])
            ->assertJsonMissing(['secret-key']);

        $this->assertSame('secret-key', SmsSettings::sole()->inex_phone_settings['apiKey']);
        $this->getJson(self::URL)->assertOk()->assertJsonMissing(['secret-key']);
    }

    public function test_a_blank_api_key_keeps_the_saved_one(): void
    {
        $this->actingAsMember();
        $this->putJson(self::URL, $this->payload())->assertOk();

        $this->putJson(self::URL, $this->payload(['api_key' => '', 'default_subject' => 'NewName']))->assertOk();

        $settings = SmsSettings::sole()->inex_phone_settings;
        $this->assertSame('secret-key', $settings['apiKey']);
        $this->assertSame('NewName', $settings['defaultSubject']);
    }

    public function test_the_first_save_needs_an_api_key(): void
    {
        $this->actingAsMember();

        $this->putJson(self::URL, $this->payload(['api_key' => null]))->assertUnprocessable();
    }
}
