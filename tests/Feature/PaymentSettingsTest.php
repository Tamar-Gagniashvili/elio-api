<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PaymentSettings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const SETTINGS = 'http://clinic.localhost/api/payment-settings';

    private const PAYMENTS = 'http://clinic.localhost/api/payments';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Clinic', 'slug' => 'clinic']);
        $this->tenant->makeCurrent();
    }

    private function actingAsMember(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'role' => $role, 'is_active' => true]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_only_clinic_admins_can_manage_payment_settings(): void
    {
        $this->getJson(self::SETTINGS)->assertUnauthorized();

        $this->actingAsMember('sales');
        $this->getJson(self::SETTINGS)->assertForbidden();
        $this->putJson(self::SETTINGS, ['provider' => 'bog'])->assertForbidden();
    }

    public function test_saves_bog_details_without_returning_the_secret(): void
    {
        $this->actingAsMember();

        $this->putJson(self::SETTINGS, ['provider' => 'bog', 'bog_client_id' => 'clinic-id', 'bog_client_secret' => 'top-secret'])
            ->assertOk()
            ->assertJson([
                'provider' => 'bog',
                'is_configured' => true,
                'bog' => ['client_id' => 'clinic-id', 'has_client_secret' => true],
                'flitt' => ['has_secret_key' => false],
            ])
            ->assertJsonMissing(['top-secret']);

        $this->assertSame('top-secret', PaymentSettings::sole()->bog_settings['client_secret']);
    }

    public function test_switching_provider_keeps_both_sets_of_details(): void
    {
        $this->actingAsMember();
        $this->putJson(self::SETTINGS, ['provider' => 'bog', 'bog_client_id' => 'clinic-id', 'bog_client_secret' => 'bog-secret'])->assertOk();

        $this->putJson(self::SETTINGS, ['provider' => 'flitt', 'flitt_merchant_id' => 1549901, 'flitt_secret_key' => 'flitt-secret'])
            ->assertOk()
            ->assertJson(['provider' => 'flitt', 'is_configured' => true, 'bog' => ['has_client_secret' => true]]);

        // A blank secret keeps the saved one
        $this->putJson(self::SETTINGS, ['provider' => 'bog', 'bog_client_id' => 'clinic-id', 'bog_client_secret' => ''])
            ->assertOk()
            ->assertJson(['provider' => 'bog', 'is_configured' => true]);

        $settings = PaymentSettings::sole();
        $this->assertSame('bog-secret', $settings->bog_settings['client_secret']);
        $this->assertSame('flitt-secret', $settings->flitt_settings['secret_key']);
    }

    public function test_the_chosen_provider_needs_its_details(): void
    {
        $this->actingAsMember();

        $this->putJson(self::SETTINGS, ['provider' => 'flitt', 'flitt_merchant_id' => 1549901])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('flitt_secret_key');
    }

    public function test_creating_a_payment_needs_payment_settings(): void
    {
        $this->actingAsMember();

        $this->postJson(self::PAYMENTS, ['amount' => 80])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'not set up'));
    }

    public function test_creates_a_payment_with_the_clinics_merchant(): void
    {
        $this->actingAsMember();
        config(['payment.flitt.api_url' => 'https://flitt.test']);
        $this->putJson(self::SETTINGS, ['provider' => 'flitt', 'flitt_merchant_id' => 1549901, 'flitt_secret_key' => 'flitt-secret'])->assertOk();
        Http::fake(['flitt.test/api/checkout/url' => Http::response([
            'response' => ['response_status' => 'success', 'checkout_url' => 'https://pay.flitt.test/checkout'],
        ])]);

        $this->postJson(self::PAYMENTS, ['amount' => 80, 'description' => 'Session'])
            ->assertCreated()
            ->assertJson(['checkout_url' => 'https://pay.flitt.test/checkout', 'status' => 'created']);

        Http::assertSent(fn ($request) => $request['request']['merchant_id'] === 1549901);
        $this->getJson(self::PAYMENTS)->assertOk()->assertJsonCount(1);
    }

    public function test_a_rejected_order_is_marked_rejected(): void
    {
        $this->actingAsMember();
        config(['payment.flitt.api_url' => 'https://flitt.test']);
        $this->putJson(self::SETTINGS, ['provider' => 'flitt', 'flitt_merchant_id' => 1549901, 'flitt_secret_key' => 'wrong'])->assertOk();
        Http::fake(['flitt.test/*' => Http::response(['response' => ['response_status' => 'failure', 'error_message' => 'Invalid signature', 'error_code' => '1014']])]);

        $this->postJson(self::PAYMENTS, ['amount' => 80])->assertStatus(502);

        $this->getJson(self::PAYMENTS)->assertJsonPath('0.status', 'rejected');
    }
}
