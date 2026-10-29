<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFromCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_offline_client_can_be_created_updated_and_booked(): void
    {
        $tenant = Tenant::create(['name' => 'Clinic', 'slug' => 'clinic']);
        $tenant->makeCurrent();
        $psychologist = User::factory()->create();

        $contact = $this->postJson('http://clinic.localhost/api/contacts', [
            'channel_type' => 'offline',
            'name' => 'Walk-in Client',
            'phone' => '+995555000111',
            'email' => null,
        ])->assertCreated()->json();

        $this->assertSame($tenant->id, $contact['tenant_id']);

        $this->putJson("http://clinic.localhost/api/contacts/{$contact['id']}", ['email' => 'walkin@gmail.com'])
            ->assertOk()
            ->assertJsonPath('email', 'walkin@gmail.com')
            ->assertJsonPath('phone', '+995555000111');

        $this->postJson('http://clinic.localhost/api/bookings', [
            'staff_user_id' => $psychologist->id,
            'contact_id' => $contact['id'],
            'starts_at' => '2026-11-02T10:00:00Z',
            'ends_at' => '2026-11-02T10:50:00Z',
        ])->assertCreated()->assertJsonPath('contact.email', 'walkin@gmail.com');
    }
}
