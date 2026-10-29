<?php

namespace Tests\Feature;

use App\Enums\SmsProvider;
use App\Http\Integrations\Sms\Requests\SendSmsRequest;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PaymentSettings;
use App\Models\Profile;
use App\Models\SmsSettings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class AdvancePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'http://clinic.localhost/api/payment-settings/advance-payment';

    private Tenant $tenant;

    private User $specialist;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.flitt.api_url' => 'https://flitt.test', 'bookings.timezone' => 'Asia/Tbilisi']);
        $this->tenant = Tenant::create(['name' => 'Clinic', 'slug' => 'clinic']);
        $this->tenant->makeCurrent();
        $this->specialist = User::factory()->create();
        Profile::create(['user_id' => $this->specialist->id, 'email' => 'nana@clinic.test', 'full_name' => 'Nana Beridze']);
    }

    private function actingAsAdmin(): void
    {
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user);
    }

    private function configurePayments(array $advance = []): void
    {
        PaymentSettings::create([
            'provider' => 'flitt',
            'flitt_settings' => ['merchant_id' => 1, 'secret_key' => 'secret'],
            'advance_payment_enabled' => true,
            'advance_payment_hours_before' => 24,
            'advance_payment_message' => 'Hi {client}, session with {specialist} on {date} at {time}. Pay {amount} GEL: {link}',
            ...$advance,
        ]);
        SmsSettings::create([
            'sms_provider' => SmsProvider::InexPhone,
            'inex_phone_settings' => ['apiKey' => 'key', 'apiUrl' => 'https://sms.test/api/v1', 'defaultSubject' => 'Clinic'],
        ]);

        Http::fake(['flitt.test/api/checkout/url' => Http::response([
            'response' => ['response_status' => 'success', 'checkout_url' => 'https://pay.flitt.test/abc'],
        ])]);
        Saloon::fake(['*' => MockResponse::make(['message' => 'ok', 'data' => [
            'id' => 'sms-1', 'type' => 'sms',
            'attributes' => ['number' => '', 'subject' => '', 'message' => '', 'status' => 0, 'parts' => 1, 'deliveryStatus' => 0],
        ]], 201)]);
    }

    private function booking(string $startsIn, array $attributes = [], ?string $phone = '995555123456'): Booking
    {
        $contact = Contact::create(['tenant_id' => $this->tenant->id, 'channel_type' => 'offline', 'name' => 'Mariam', 'phone' => $phone]);
        $start = now()->add($startsIn);

        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'staff_user_id' => $this->specialist->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(50),
            'price' => 80,
            ...$attributes,
        ]);
    }

    private function runCommand(): void
    {
        Tenant::forgetCurrent();
        $this->artisan('bookings:request-advance-payments')->assertSuccessful();
        $this->tenant->makeCurrent();
    }

    public function test_texts_a_payment_link_for_sessions_inside_the_window(): void
    {
        $this->configurePayments();
        $this->travelTo(now()->setTimezone('Asia/Tbilisi')->setTime(9, 0)->utc());
        $booking = $this->booking('+20 hours');

        $this->runCommand();

        $payment = Payment::sole();
        $this->assertTrue($payment->payable->is($booking));
        $this->assertSame('80.00', $payment->amount);
        $this->assertNotNull($booking->refresh()->advance_payment_requested_at);

        $start = $booking->starts_at->setTimezone('Asia/Tbilisi');
        Saloon::assertSent(fn (SendSmsRequest $request) => $request->body()->all()['phone'] === '995555123456'
            && $request->body()->all()['message'] === "Hi Mariam, session with Nana Beridze on {$start->format('d.m.Y')} at {$start->format('H:i')}. Pay 80.00 GEL: https://pay.flitt.test/abc");
    }

    public function test_sends_only_once_per_booking(): void
    {
        $this->configurePayments();
        $this->booking('+3 hours');

        $this->runCommand();
        $this->runCommand();

        $this->assertSame(1, Payment::count());
        Saloon::assertSentCount(1);
    }

    public function test_skips_sessions_outside_the_window_cancelled_or_without_a_phone(): void
    {
        $this->configurePayments();
        $this->booking('+30 hours');
        $this->booking('+2 hours', ['status' => 'cancelled']);
        $this->booking('-1 hour');
        $this->booking('+2 hours', phone: null);
        $this->booking('+2 hours', ['price' => null]);

        $this->runCommand();

        $this->assertSame(0, Payment::count());
        Saloon::assertNothingSent();
    }

    public function test_charges_each_bookings_own_price(): void
    {
        $this->configurePayments();
        $this->booking('+2 hours', ['price' => 120]);
        $this->booking('+3 hours', ['price' => 90.5]);

        $this->runCommand();

        $this->assertEqualsCanonicalizing(['120.00', '90.50'], Payment::pluck('amount')->all());
        Saloon::assertSent(fn (SendSmsRequest $request) => str_contains($request->body()->all()['message'], 'Pay 120.00 GEL'));
        Saloon::assertSent(fn (SendSmsRequest $request) => str_contains($request->body()->all()['message'], 'Pay 90.50 GEL'));
    }

    public function test_does_nothing_while_turned_off(): void
    {
        $this->configurePayments(['advance_payment_enabled' => false]);
        $this->booking('+2 hours');

        $this->runCommand();

        $this->assertSame(0, Payment::count());
    }

    public function test_waits_for_sms_to_be_set_up_before_marking_anything_sent(): void
    {
        $this->configurePayments();
        SmsSettings::query()->delete();
        $booking = $this->booking('+2 hours');

        $this->runCommand();

        $this->assertSame(0, Payment::count());
        $this->assertNull($booking->refresh()->advance_payment_requested_at);
    }

    public function test_admin_saves_advance_payment_settings(): void
    {
        $this->actingAsAdmin();
        PaymentSettings::create(['provider' => 'flitt', 'flitt_settings' => ['merchant_id' => 1, 'secret_key' => 'secret']]);

        $this->putJson(self::URL, ['enabled' => true, 'hours_before' => 24, 'message' => 'Pay here'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->putJson(self::URL, ['enabled' => true, 'hours_before' => 48, 'message' => 'Pay {amount} GEL: {link}'])
            ->assertOk()
            ->assertJson(['advance_payment' => ['enabled' => true, 'hours_before' => 48, 'message' => 'Pay {amount} GEL: {link}']]);
    }

    public function test_cannot_turn_on_without_a_payment_provider(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URL, ['enabled' => true, 'hours_before' => 24, 'message' => '{link}'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enabled');
    }
}
