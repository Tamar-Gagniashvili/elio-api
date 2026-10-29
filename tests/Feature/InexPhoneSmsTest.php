<?php

namespace Tests\Feature;

use App\Enums\SmsMessageStatus;
use App\Enums\SmsProvider;
use App\Http\Integrations\Sms\Requests\SendOtpRequest;
use App\Http\Integrations\Sms\Requests\SendSmsRequest;
use App\Jobs\SendSms;
use App\Models\SmsMessage;
use App\Models\SmsSettings;
use App\Models\Tenant;
use App\Services\Sms\SmsOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class InexPhoneSmsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Clinic', 'slug' => 'clinic']);
        $this->tenant->makeCurrent();
    }

    private function configureSms(string $subject = 'Clinic'): void
    {
        SmsSettings::create([
            'sms_provider' => SmsProvider::InexPhone,
            'inex_phone_settings' => [
                'apiKey' => 'test-key',
                'apiUrl' => 'https://smsservice.inexphone.ge/api/v1',
                'defaultSubject' => $subject,
            ],
        ]);
    }

    private function sentResponse(string $id = 'msg-1', int $parts = 1): MockResponse
    {
        return MockResponse::make([
            'message' => 'ok',
            'data' => [
                'id' => $id,
                'type' => 'sms',
                'attributes' => ['number' => '995555123456', 'subject' => 'Clinic', 'message' => 'Hi', 'status' => 0, 'parts' => $parts, 'deliveryStatus' => 0],
            ],
        ], 201);
    }

    public function test_sends_an_sms_with_the_clinic_account_and_records_it(): void
    {
        $this->configureSms();
        Saloon::fake(['*' => $this->sentResponse()]);

        SendSms::dispatchSync('995555123456', 'Your session is tomorrow at 10:00');

        Saloon::assertSent(function (SendSmsRequest $request): bool {
            $body = $request->body()->all();

            return $body['phone'] === '995555123456'
                && $body['subject'] === 'Clinic'
                && $body['message'] === 'Your session is tomorrow at 10:00'
                && str_ends_with($body['delivery_callback_url'], '/api/sms/callbacks/delivery');
        });

        $message = SmsMessage::sole();
        $this->assertSame('msg-1', $message->uuid);
        $this->assertSame(SmsMessageStatus::Submitted, $message->sms_message_status);
        $this->assertSame($this->tenant->id, $message->tenant_id);
    }

    public function test_records_a_failed_send(): void
    {
        $this->configureSms();
        Saloon::fake(['*' => MockResponse::make(['message' => 'Specified subject is not allowed'], 403)]);

        SendSms::dispatchSync('995555123456', 'Hi');

        $this->assertSame(SmsMessageStatus::NotSent, SmsMessage::sole()->sms_message_status);
    }

    public function test_does_not_call_inexphone_while_sms_is_not_configured(): void
    {
        Saloon::fake([]);

        SendSms::dispatchSync('995555123456', 'Hi');

        Saloon::assertNothingSent();
        $this->assertSame(SmsMessageStatus::NotSent, SmsMessage::sole()->sms_message_status);
    }

    public function test_uses_each_clinics_own_settings(): void
    {
        $this->configureSms('ClinicA');
        Saloon::fake(['*' => $this->sentResponse()]);
        SendSms::dispatchSync('995555123456', 'Hi');

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        $other->makeCurrent();
        $this->configureSms('ClinicB');
        SendSms::dispatchSync('995555123456', 'Hi');

        Saloon::assertSent(fn (SendSmsRequest $request): bool => $request->body()->all()['subject'] === 'ClinicA');
        Saloon::assertSent(fn (SendSmsRequest $request): bool => $request->body()->all()['subject'] === 'ClinicB');
    }

    public function test_callbacks_mark_a_message_delivered_once_all_parts_arrive(): void
    {
        $this->configureSms();
        Saloon::fake(['*' => $this->sentResponse('msg-2', parts: 2)]);
        SendSms::dispatchSync('995555123456', 'A long message');
        Tenant::forgetCurrent();

        $callback = fn (int $status, int $part) => [
            'uuid' => 'msg-2', 'report_uuid' => 'r-'.$part, 'message_status' => $status, 'event_type' => 'delivery',
            'part_no' => $part, 'part_total' => 2, 'receiver' => '995555123456', 'sender' => 'Clinic',
            'created_at' => now()->toDateTimeString(), 'result' => 'ok',
        ];

        $this->postJson('http://localhost/api/sms/callbacks/submit', $callback(0, 1))->assertNoContent();
        $this->postJson('http://localhost/api/sms/callbacks/delivery', $callback(2, 1))->assertNoContent();
        $this->assertSame(SmsMessageStatus::Submitted, SmsMessage::sole()->sms_message_status);

        $this->postJson('http://localhost/api/sms/callbacks/delivery', $callback(2, 2))->assertNoContent();
        $this->assertSame(SmsMessageStatus::Delivered, SmsMessage::sole()->sms_message_status);
    }

    public function test_callback_for_an_unknown_message_returns_404(): void
    {
        Tenant::forgetCurrent();

        $this->postJson('http://localhost/api/sms/callbacks/delivery', [
            'uuid' => 'nope', 'report_uuid' => 'r', 'message_status' => 2, 'event_type' => 'delivery', 'part_no' => 1,
            'part_total' => 1, 'receiver' => '995555123456', 'sender' => 'Clinic', 'created_at' => 'now', 'result' => 'ok',
        ])->assertNotFound();
    }

    public function test_otp_uses_the_clinics_approved_subject(): void
    {
        $this->configureSms();
        Saloon::fake(['*' => MockResponse::make(['message' => 'ok', 'data' => []], 201)]);

        $this->assertTrue(app(SmsOtpService::class)->send('995555123456', 'Your code is {{CODE}}'));

        Saloon::assertSent(fn (SendOtpRequest $request): bool => $request->body()->all()['subject'] === 'Clinic'
            && $request->body()->all()['text'] === 'Your code is {{CODE}}');
    }
}
