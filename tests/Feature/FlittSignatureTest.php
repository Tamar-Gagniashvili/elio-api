<?php

namespace Tests\Feature;

use App\Integrations\Payment\Flitt\Data\FlittOrderRequestData;
use App\Integrations\Payment\Flitt\FlittSignature;
use Tests\TestCase;

class FlittSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.flitt.secret_key' => 'test', 'payment.flitt.merchant_id' => 1549901]);
    }

    public function test_signature_uses_non_empty_values_ordered_by_name(): void
    {
        $signature = app(FlittSignature::class)->generate([
            'order_id' => 'TestOrder2',
            'merchant_id' => 1549901,
            'order_desc' => 'Test payment',
            'rrn' => '',
            'fee' => null,
            'currency' => 'GEL',
            'amount' => 1000,
            'fee_oplata' => '0',
        ]);

        $this->assertSame(sha1('test|1000|GEL|0|1549901|Test payment|TestOrder2'), $signature);
    }

    public function test_accepts_a_signed_callback_ignoring_the_debug_signature_string(): void
    {
        $payload = ['order_id' => '7', 'order_status' => 'approved', 'amount' => '1000', 'rectoken' => ''];
        $payload['signature'] = sha1('test|1000|7|approved');
        $payload['response_signature_string'] = '**********|1000|7|approved';

        $this->assertTrue(app(FlittSignature::class)->isValid($payload));
    }

    public function test_rejects_tampered_unsigned_or_keyless_callbacks(): void
    {
        $payload = ['order_id' => '7', 'order_status' => 'declined', 'amount' => '1000'];
        $signed = [...$payload, 'signature' => sha1('test|1000|7|declined')];

        $this->assertFalse(app(FlittSignature::class)->isValid([...$signed, 'order_status' => 'approved']));
        $this->assertFalse(app(FlittSignature::class)->isValid($payload));

        config(['payment.flitt.secret_key' => null]);

        $this->assertFalse(app(FlittSignature::class)->isValid([...$payload, 'signature' => sha1('|1000|7|declined')]));
    }

    public function test_rounds_the_amount_to_minor_units(): void
    {
        $request = new FlittOrderRequestData(
            order_id: '7',
            amount: 19.99,
            currency: 'GEL',
            redirect_url: 'https://example.test/back',
            callback_url: 'https://example.test/callback',
            description: 'C-1',
        );

        $this->assertSame(1999, $request->toArray()['amount']);
    }
}
