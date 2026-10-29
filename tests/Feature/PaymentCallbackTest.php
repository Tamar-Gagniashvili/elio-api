<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Integrations\Payment\Flitt\FlittSignature;
use App\Models\Payment;
use App\Models\PaymentSettings;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const BOG_URL = 'http://localhost/api/payments/callbacks/bog';

    private const FLITT_URL = 'http://localhost/api/payments/callbacks/flitt';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.bog.api_url' => 'https://bog.test',
            'payment.bog.oauth_url' => 'https://bog-oauth.test',
            'payment.flitt.api_url' => 'https://flitt.test',
        ]);

        $this->tenant = Tenant::create(['name' => 'Clinic', 'slug' => 'clinic']);
        $this->tenant->makeCurrent();
        PaymentSettings::create([
            'provider' => 'flitt',
            'bog_settings' => ['client_id' => 'clinic', 'client_secret' => 'secret'],
            'flitt_settings' => ['merchant_id' => 1, 'secret_key' => 'test-secret'],
        ]);
        // Callbacks come in without a tenant
        Tenant::forgetCurrent();
    }

    private function payment(PaymentProvider $provider, PaymentStatus $status): Payment
    {
        return Payment::create([
            'tenant_id' => $this->tenant->id,
            'provider' => $provider,
            'external_id' => 'ext-1',
            'status' => $status,
            'ccy' => 'GEL',
            'amount' => 100,
        ]);
    }

    private function bogCallback(string $statusKey): array
    {
        return [
            'event' => 'order_payment',
            'zoned_request_time' => now()->toIso8601String(),
            'body' => ['order_id' => 'ext-1', 'order_status' => ['key' => $statusKey]],
        ];
    }

    private function fakeBogOrderStatus(string $statusKey): void
    {
        Http::fake([
            'bog-oauth.test/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'bog.test/payments/v1/receipt/ext-1' => Http::response(['order_status' => ['key' => $statusKey]]),
        ]);
    }

    private function flittCallback(string $orderStatus): array
    {
        $payload = [
            'order_id' => 'ext-1',
            'order_status' => $orderStatus,
            'response_status' => 'success',
            'amount' => 10000,
        ];

        // Signed with the clinic's secret key, as Flitt would
        $this->tenant->makeCurrent();
        $signature = app(FlittSignature::class)->generate($payload);
        Tenant::forgetCurrent();

        return [...$payload, 'signature' => $signature];
    }

    public function test_bog_callback_completes_and_refunds_a_payment(): void
    {
        Http::preventStrayRequests();
        $payment = $this->payment(PaymentProvider::Bog, PaymentStatus::Created);

        $this->postJson(self::BOG_URL, $this->bogCallback('completed'))->assertNoContent();
        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);

        $this->postJson(self::BOG_URL, $this->bogCallback('refunded'))->assertNoContent();
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
    }

    public static function staleBogStatuses(): array
    {
        return [['rejected'], ['in_progress'], ['expired']];
    }

    #[DataProvider('staleBogStatuses')]
    public function test_stale_bog_callback_keeps_a_completed_payment_completed(string $statusKey): void
    {
        $this->fakeBogOrderStatus('completed');
        $payment = $this->payment(PaymentProvider::Bog, PaymentStatus::Completed);

        $this->postJson(self::BOG_URL, $this->bogCallback($statusKey))->assertNoContent();

        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_bog_callback_undoes_a_completed_payment_when_bog_confirms(): void
    {
        $this->fakeBogOrderStatus('rejected');
        $payment = $this->payment(PaymentProvider::Bog, PaymentStatus::Completed);

        $this->postJson(self::BOG_URL, $this->bogCallback('rejected'))->assertNoContent();

        $this->assertSame(PaymentStatus::Rejected, $payment->refresh()->status);
    }

    public function test_bog_callback_for_an_unknown_order_returns_404(): void
    {
        $this->postJson(self::BOG_URL, $this->bogCallback('completed'))->assertNotFound();
    }

    public function test_signed_flitt_callback_completes_a_payment(): void
    {
        Http::preventStrayRequests();
        $payment = $this->payment(PaymentProvider::Flitt, PaymentStatus::Created);

        $this->postJson(self::FLITT_URL, $this->flittCallback('approved'))->assertNoContent();

        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_forged_flitt_callback_is_checked_with_flitt_instead(): void
    {
        Http::fake(['flitt.test/api/status/order_id' => Http::response(['response' => ['order_status' => 'declined']])]);
        $payment = $this->payment(PaymentProvider::Flitt, PaymentStatus::Created);

        $forged = [...$this->flittCallback('approved'), 'signature' => 'forged'];
        $this->postJson(self::FLITT_URL, $forged)->assertNoContent();

        $this->assertSame(PaymentStatus::Rejected, $payment->refresh()->status);
    }

    public function test_flitt_callback_signed_with_another_merchants_key_is_checked_with_flitt(): void
    {
        Http::fake(['flitt.test/api/status/order_id' => Http::response(['response' => ['order_status' => 'declined']])]);
        $payment = $this->payment(PaymentProvider::Flitt, PaymentStatus::Created);

        $payload = ['order_id' => 'ext-1', 'order_status' => 'approved', 'response_status' => 'success', 'amount' => 10000];
        config(['payment.flitt.secret_key' => 'platform-secret']);
        $payload['signature'] = app(FlittSignature::class)->generate($payload);

        $this->postJson(self::FLITT_URL, $payload)->assertNoContent();

        $this->assertSame(PaymentStatus::Rejected, $payment->refresh()->status);
    }

    public function test_stale_flitt_callback_keeps_a_completed_payment_completed(): void
    {
        Http::fake(['flitt.test/api/status/order_id' => Http::response(['response' => ['order_status' => 'approved']])]);
        $payment = $this->payment(PaymentProvider::Flitt, PaymentStatus::Completed);

        $this->postJson(self::FLITT_URL, $this->flittCallback('declined'))->assertNoContent();

        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }
}
