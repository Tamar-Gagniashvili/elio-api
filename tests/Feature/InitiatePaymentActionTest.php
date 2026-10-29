<?php

namespace Tests\Feature;

use App\Actions\InitiatePaymentAction;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentSettings;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InitiatePaymentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::create(['name' => 'Clinic', 'slug' => 'clinic'])->makeCurrent();
    }

    public function test_creates_a_bog_order_and_returns_its_checkout_url(): void
    {
        config(['payment.bog.api_url' => 'https://bog.test', 'payment.bog.oauth_url' => 'https://bog-oauth.test']);
        PaymentSettings::create(['provider' => 'bog', 'bog_settings' => ['client_id' => 'clinic', 'client_secret' => 'secret']]);
        Http::fake([
            'bog-oauth.test/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'bog.test/payments/v1/ecommerce/orders' => Http::response([
                'id' => 'bog-order-1',
                '_links' => ['redirect' => ['href' => 'https://payment.bog.ge/?order_id=bog-order-1']],
            ]),
        ]);

        $initiated = app(InitiatePaymentAction::class)->execute(80, 'Session');

        $this->assertSame('https://payment.bog.ge/?order_id=bog-order-1', $initiated->checkoutUrl);
        $this->assertTrue($initiated->payment->is(Payment::sole()));
        $payment = Payment::sole();
        $this->assertSame(PaymentProvider::Bog, $payment->provider);
        $this->assertSame(PaymentStatus::Created, $payment->status);
        $this->assertSame('bog-order-1', $payment->external_id);
        $this->assertSame(Tenant::current()->id, $payment->tenant_id);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://bog.test/payments/v1/ecommerce/orders'
            && $request['purchase_units']['total_amount'] == 80
            && $request['purchase_units']['basket'] === [[
                'product_id' => $payment->id,
                'description' => 'Session',
                'quantity' => 1,
                'unit_price' => 80.0,
            ]]
            && $request['redirect_urls']['success'] === "http://localhost/api/payments/{$payment->id}/return");
    }

    public function test_creates_a_signed_flitt_order_using_the_payment_id(): void
    {
        config(['payment.flitt.api_url' => 'https://flitt.test']);
        PaymentSettings::create(['provider' => 'flitt', 'flitt_settings' => ['merchant_id' => 1, 'secret_key' => 'secret']]);
        Http::fake([
            'flitt.test/api/checkout/url' => Http::response([
                'response' => ['response_status' => 'success', 'checkout_url' => 'https://pay.flitt.test/checkout'],
            ]),
        ]);

        $initiated = app(InitiatePaymentAction::class)->execute(80.5, 'Session');

        $this->assertSame('https://pay.flitt.test/checkout', $initiated->checkoutUrl);
        $payment = Payment::sole();
        $this->assertSame($payment->id, $payment->external_id);
        Http::assertSent(fn (Request $request) => $request['request']['amount'] === 8050
            && $request['request']['order_id'] === $payment->id
            && isset($request['request']['signature']));
    }
}
