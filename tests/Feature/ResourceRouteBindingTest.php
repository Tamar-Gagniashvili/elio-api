<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\LeadStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resources whose controllers name the model argument without underscores (e.g. $leadstatus)
 * must still bind the model from the URL, or updates and deletes silently do nothing.
 */
class ResourceRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'http://clinic.localhost/api';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Clinic', 'slug' => 'clinic']);
        $this->tenant->makeCurrent();
    }

    private function leadStatus(string $name = 'First visit'): LeadStatus
    {
        return LeadStatus::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'color' => 'blue']);
    }

    public function test_updates_a_lead_status(): void
    {
        $status = $this->leadStatus();

        $this->putJson(self::BASE."/lead-statuses/{$status->id}", ['name' => 'Ongoing therapy', 'color' => 'green', 'icon' => null])
            ->assertOk()
            ->assertJson(['id' => $status->id, 'name' => 'Ongoing therapy', 'color' => 'green']);

        $this->assertSame('Ongoing therapy', $status->refresh()->name);
    }

    public function test_shows_and_deletes_a_lead_status(): void
    {
        $status = $this->leadStatus();

        $this->getJson(self::BASE."/lead-statuses/{$status->id}")->assertOk()->assertJson(['id' => $status->id]);
        $this->deleteJson(self::BASE."/lead-statuses/{$status->id}")->assertNoContent();

        $this->assertModelMissing($status);
    }

    public function test_removes_a_status_from_a_client(): void
    {
        $contact = Contact::create(['tenant_id' => $this->tenant->id, 'channel_type' => 'offline', 'name' => 'Client']);
        $link = ContactStatus::create(['tenant_id' => $this->tenant->id, 'contact_id' => $contact->id, 'status_id' => $this->leadStatus()->id]);

        $this->deleteJson(self::BASE."/contact-statuses/{$link->id}")->assertNoContent();

        $this->assertModelMissing($link);
    }
}
