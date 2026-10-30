<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentSettings;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentReturnTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.bog.api_url' => 'https://bog.test', 'payment.bog.oauth_url' => 'https://bog-oauth.test', 'payment.flitt.api_url' => 'https://flitt.test']);
        $this->tenant = Tenant::create(['name' => 'Calm Clinic', 'slug' => 'clinic']);
        $this->tenant->makeCurrent();
        PaymentSettings::create([
            'provider' => 'bog',
            'bog_settings' => ['client_id' => 'clinic', 'client_secret' => 'secret'],
            'flitt_settings' => ['merchant_id' => 1, 'secret_key' => 'secret'],
        ]);
    }

    private function payment(PaymentStatus $status, PaymentProvider $provider = PaymentProvider::Bog): Payment
    {
        $payment = Payment::create([
            'provider' => $provider,
            'external_id' => 'ext-1',
            'status' => $status,
            'ccy' => 'GEL',
            'amount' => 120,
        ]);
        // The client arrives without a clinic
        Tenant::forgetCurrent();

        return $payment;
    }

    private function url(Payment $payment): string
    {
        return "http://localhost/api/payments/{$payment->id}/return";
    }

    public function test_shows_a_completed_payment(): void
    {
        Http::preventStrayRequests();
        $payment = $this->payment(PaymentStatus::Completed);

        $this->get($this->url($payment))
            ->assertOk()
            ->assertSee('Payment received')
            ->assertSee('120.00 GEL')
            ->assertSee('Calm Clinic');
    }

    public function test_asks_bog_when_the_callback_has_not_arrived(): void
    {
        Http::fake([
            'bog-oauth.test/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'bog.test/payments/v1/receipt/ext-1' => Http::response(['order_status' => ['key' => 'completed']]),
        ]);
        $payment = $this->payment(PaymentStatus::Created);

        $this->get($this->url($payment))->assertOk()->assertSee('Payment received');

        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_flitt_posts_the_client_back_and_a_declined_payment_shows_as_failed(): void
    {
        Http::fake(['flitt.test/api/status/order_id' => Http::response(['response' => ['order_status' => 'declined']])]);
        $payment = $this->payment(PaymentStatus::Created, PaymentProvider::Flitt);

        $this->post($this->url($payment), ['order_id' => 'ext-1'])->assertOk()->assertSee('Payment didn&#039;t go through', false);
    }

    public function test_keeps_waiting_when_the_gateway_cannot_be_reached(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);
        $payment = $this->payment(PaymentStatus::Created);

        $this->get($this->url($payment))->assertOk()->assertSee('Confirming your payment');
    }

    public function test_speaks_the_clients_language(): void
    {
        Http::preventStrayRequests();
        $payment = $this->payment(PaymentStatus::Completed);

        $this->get($this->url($payment), ['Accept-Language' => 'ka-GE,ka;q=0.9'])->assertSee('გადახდა მიღებულია');
    }

    public function test_unknown_payment_is_not_found(): void
    {
        $this->get('http://localhost/api/payments/01a18927-0000-7000-8000-000000000000/return')->assertNotFound();
    }
}
