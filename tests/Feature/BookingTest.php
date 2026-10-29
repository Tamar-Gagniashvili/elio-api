<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'http://clinic.localhost/api/bookings';

    private Tenant $tenant;

    private User $psychologist;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Clinic', 'slug' => 'clinic']);
        $this->tenant->makeCurrent();
        $this->psychologist = User::factory()->create();
        $this->contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'channel_type' => 'web_chat',
            'name' => 'Client',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'staff_user_id' => $this->psychologist->id,
            'contact_id' => $this->contact->id,
            'title' => 'Initial consultation',
            'starts_at' => '2026-11-02T10:00:00Z',
            'ends_at' => '2026-11-02T10:50:00Z',
        ], $overrides);
    }

    public function test_creates_booking_scoped_to_current_tenant(): void
    {
        $response = $this->postJson(self::BASE, $this->payload());

        $response->assertCreated()
            ->assertJsonPath('status', 'scheduled')
            ->assertJsonPath('contact.name', 'Client');
        $this->assertSame($this->tenant->id, Booking::first()->tenant_id);
    }

    public function test_rejects_overlapping_booking_for_same_psychologist(): void
    {
        $this->postJson(self::BASE, $this->payload())->assertCreated();

        $this->postJson(self::BASE, $this->payload([
            'starts_at' => '2026-11-02T10:30:00Z',
            'ends_at' => '2026-11-02T11:20:00Z',
        ]))->assertStatus(409)->assertJsonStructure(['message', 'conflict' => ['id']]);
    }

    public function test_allows_back_to_back_and_cancelled_slot_reuse(): void
    {
        $first = $this->postJson(self::BASE, $this->payload())->assertCreated()->json('id');

        // Starts exactly when the previous one ends
        $this->postJson(self::BASE, $this->payload([
            'starts_at' => '2026-11-02T10:50:00Z',
            'ends_at' => '2026-11-02T11:40:00Z',
        ]))->assertCreated();

        // Once cancelled, the original slot is free again
        $this->putJson(self::BASE."/{$first}", ['status' => 'cancelled'])->assertOk();
        $this->postJson(self::BASE, $this->payload())->assertCreated();
    }

    public function test_update_does_not_conflict_with_itself(): void
    {
        $id = $this->postJson(self::BASE, $this->payload())->assertCreated()->json('id');

        $this->putJson(self::BASE."/{$id}", ['notes' => 'Bring intake form', 'ends_at' => '2026-11-02T11:00:00Z'])
            ->assertOk()
            ->assertJsonPath('notes', 'Bring intake form');
    }

    public function test_rejects_end_before_start(): void
    {
        $this->postJson(self::BASE, $this->payload(['ends_at' => '2026-11-02T09:00:00Z']))
            ->assertStatus(422);
    }

    public function test_filters_by_date_range(): void
    {
        $this->postJson(self::BASE, $this->payload())->assertCreated();
        $this->postJson(self::BASE, $this->payload([
            'starts_at' => '2026-11-12T10:00:00Z',
            'ends_at' => '2026-11-12T10:50:00Z',
        ]))->assertCreated();

        $this->getJson(self::BASE.'?from=2026-11-02T00:00:00Z&to=2026-11-09T00:00:00Z')
            ->assertOk()
            ->assertJsonCount(1);
    }
}
