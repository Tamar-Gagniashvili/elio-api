<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\Membership;
use Database\Seeders\ClinicDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_clinic_and_is_rerunnable(): void
    {
        $this->seed(ClinicDemoSeeder::class);
        $this->seed(ClinicDemoSeeder::class);

        $this->assertSame(4, Membership::count());
        $this->assertSame(8, Contact::count());
        $this->assertSame(26, Booking::count());

        // No psychologist is double-booked
        foreach (Booking::where('status', '!=', 'cancelled')->get() as $booking) {
            $this->assertSame(1, Booking::overlapping($booking->staff_user_id, $booking->starts_at, $booking->ends_at)->count());
        }
    }
}
