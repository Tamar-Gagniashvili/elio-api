<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Psych clinic demo data for the "test" tenant: psychologists, clients with
 * chat threads, and bookings around the current week.
 *
 * Safe to re-run — users, contacts and conversations are reused, bookings are regenerated.
 *
 *   php artisan db:seed --class=ClinicDemoSeeder
 */
class ClinicDemoSeeder extends Seeder
{
    private const TENANT_SLUG = 'test';

    private const TIMEZONE = 'Asia/Tbilisi';

    private const PASSWORD = 'password123';

    public function run(): void
    {
        Model::withoutEvents(function () {
            $tenant = Tenant::where('domain', self::TENANT_SLUG)->orWhere('slug', self::TENANT_SLUG)->first()
                ?? Tenant::create(['name' => 'Test Clinic', 'slug' => self::TENANT_SLUG, 'domain' => self::TENANT_SLUG]);

            $psychologists = $this->seedPsychologists($tenant);
            $clients = $this->seedClients($tenant, $psychologists);
            $count = $this->seedBookings($tenant, $psychologists, $clients);

            $this->command?->info("Seeded tenant \"{$tenant->name}\": ".count($psychologists).' psychologists, '.count($clients)." clients, {$count} bookings.");
            $this->command?->info('Log in as any psychologist with password "'.self::PASSWORD.'", e.g. '.$psychologists[0]->email);
        });
    }

    /**
     * @return array<int, User>
     */
    private function seedPsychologists(Tenant $tenant): array
    {
        $people = [
            ['name' => 'Dr. Nana Kvaratskhelia', 'email' => 'nana@clinic.test', 'role' => 'admin'],
            ['name' => 'Irakli Javakhishvili', 'email' => 'irakli@clinic.test', 'role' => 'sales'],
            ['name' => 'Eka Mchedlidze', 'email' => 'eka@clinic.test', 'role' => 'sales'],
            ['name' => 'Salome Abashidze', 'email' => 'salome@clinic.test', 'role' => 'sales'],
        ];

        return array_map(function (array $person) use ($tenant) {
            $user = User::firstOrCreate(
                ['email' => $person['email']],
                ['name' => $person['name'], 'password' => Hash::make(self::PASSWORD)]
            );

            Profile::firstOrCreate(
                ['user_id' => $user->id],
                ['email' => $person['email'], 'full_name' => $person['name']]
            );

            Membership::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                ['role' => $person['role'], 'is_active' => true]
            );

            return $user;
        }, $people);
    }

    /**
     * @param  array<int, User>  $psychologists
     * @return array<int, Conversation>
     */
    private function seedClients(Tenant $tenant, array $psychologists): array
    {
        $scenarios = [
            [
                'channel_type' => 'web_chat',
                'contact' => ['name' => 'Mariam Tsereteli', 'email' => 'mariam.t@example.com', 'external_id' => 'clinic_web_1'],
                'assigned' => 0,
                'thread' => [
                    ['contact', 'Hello, I would like to book a first consultation. I have been dealing with anxiety for a few months.'],
                    ['agent', 'Hi Mariam, thank you for reaching out. Dr. Nana has availability this week — would Tuesday morning work?'],
                    ['contact', 'Tuesday works, thank you!'],
                ],
            ],
            [
                'channel_type' => 'whatsapp',
                'contact' => ['name' => 'Luka Gelashvili', 'phone' => '+995599201301', 'external_id' => 'clinic_wa_1'],
                'assigned' => 1,
                'thread' => [
                    ['contact', 'Can I move my Thursday session to a later time?'],
                    ['agent', 'Of course Luka, Irakli has a slot at 17:00. Should I move it?'],
                    ['contact', 'Yes please'],
                ],
            ],
            [
                'channel_type' => 'instagram',
                'contact' => ['name' => 'ani.kh', 'external_id' => 'clinic_ig_1'],
                'assigned' => null,
                'thread' => [
                    ['contact', 'Hi, do you offer couples therapy? How much is a session?'],
                ],
            ],
            [
                'channel_type' => 'telegram',
                'contact' => ['name' => 'Giorgi Maisuradze', 'phone' => '+995555301402', 'external_id' => 'clinic_tg_1'],
                'assigned' => 2,
                'thread' => [
                    ['contact', 'Is it possible to have the session online?'],
                    ['agent', 'Yes, Eka offers online sessions. I will send you the link before the session.'],
                    ['contact', 'Great, thanks'],
                ],
            ],
            [
                'channel_type' => 'web_chat',
                'contact' => ['name' => 'Tamta Japaridze', 'email' => 'tamta.j@example.com', 'external_id' => 'clinic_web_2'],
                'assigned' => 3,
                'thread' => [
                    ['contact', 'I need to cancel tomorrow, I am sick. Sorry!'],
                    ['agent', 'No problem Tamta, get well soon. Let us know when you want to reschedule.'],
                ],
            ],
            [
                'channel_type' => 'whatsapp',
                'contact' => ['name' => 'Nika Bakradze', 'phone' => '+995599501603', 'external_id' => 'clinic_wa_2'],
                'assigned' => 0,
                'thread' => [
                    ['contact', 'Hello, my son is 14, do you work with teenagers?'],
                    ['agent', 'Hi Nika, yes — Dr. Nana works with adolescents. We can start with a parent consultation.'],
                    ['contact', 'Sounds good, let us book that'],
                ],
            ],
            [
                'channel_type' => 'messenger',
                'contact' => ['name' => 'Keti Lobjanidze', 'external_id' => 'clinic_fb_1'],
                'assigned' => 1,
                'thread' => [
                    ['contact', 'Thank you for today, the session really helped.'],
                    ['agent', 'Glad to hear it Keti. See you next week at the same time.'],
                ],
            ],
            [
                'channel_type' => 'telegram',
                'contact' => ['name' => 'Saba Nozadze', 'phone' => '+995555601704', 'external_id' => 'clinic_tg_2'],
                'assigned' => null,
                'thread' => [
                    ['contact', 'Do you have any evening slots? I work until 18:00.'],
                ],
            ],
        ];

        $channels = $this->ensureChannels($tenant);
        $baseTime = now()->subDays(2);
        $conversations = [];

        foreach ($scenarios as $i => $scenario) {
            $contact = Contact::firstOrCreate(
                ['tenant_id' => $tenant->id, 'external_id' => $scenario['contact']['external_id']],
                array_merge(['channel_type' => $scenario['channel_type']], $scenario['contact'])
            );

            $existing = Conversation::where('tenant_id', $tenant->id)->where('contact_id', $contact->id)->first();
            if ($existing) {
                $conversations[] = $existing->setRelation('contact', $contact);

                continue;
            }

            $assignee = $scenario['assigned'] !== null ? $psychologists[$scenario['assigned']] : null;
            $conversation = Conversation::create([
                'tenant_id' => $tenant->id,
                'contact_id' => $contact->id,
                'channel_id' => $channels[$scenario['channel_type']]->id,
                'assigned_to' => $assignee?->id,
                'status' => $assignee ? 'assigned' : 'open',
                'assignment_reason' => $assignee ? 'manual' : null,
                'assignment_seen_by_assignee' => (bool) $assignee,
            ]);

            $timestamp = $baseTime->clone()->addHours($i * 5);
            $unread = 0;
            $last = null;

            foreach ($scenario['thread'] as [$senderType, $content]) {
                $timestamp = $timestamp->clone()->addMinutes(rand(3, 30));
                $last = Message::create([
                    'tenant_id' => $tenant->id,
                    'conversation_id' => $conversation->id,
                    'sender_type' => $senderType,
                    'content' => $content,
                    'is_read' => $senderType !== 'contact',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                $unread = $senderType === 'contact' ? $unread + 1 : 0;
            }

            $conversation->update([
                'last_message_at' => $last->created_at,
                'last_message_preview' => Str::limit($last->content, 120),
                'unread_count' => $unread,
            ]);

            $conversations[] = $conversation->setRelation('contact', $contact);
        }

        return $conversations;
    }

    /**
     * @param  array<int, User>  $psychologists
     * @param  array<int, Conversation>  $clients
     */
    private function seedBookings(Tenant $tenant, array $psychologists, array $clients): int
    {
        // Regenerate so the schedule always sits around the current week
        Booking::where('tenant_id', $tenant->id)
            ->whereIn('contact_id', array_map(fn ($c) => $c->contact_id, $clients))
            ->delete();

        $now = CarbonImmutable::now(self::TIMEZONE);
        $monday = $now->startOfWeek();

        // [day offset from this Monday, start time, minutes, psychologist, client, session type, status override]
        $schedule = [
            // Last week
            [-7, '10:00', 50, 0, 6, 'Individual therapy', null],
            [-6, '12:00', 50, 1, 6, 'Individual therapy', null],
            [-5, '15:00', 60, 2, 3, 'Online session', 'no_show'],
            [-3, '11:00', 50, 3, 4, 'Individual therapy', null],
            // This week
            [0, '09:00', 60, 0, 0, 'Initial consultation', null],
            [0, '11:00', 50, 1, 6, 'Individual therapy', null],
            [0, '11:00', 50, 2, 3, 'Online session', null],
            [0, '16:00', 90, 3, 2, 'Couples therapy', null],
            [1, '10:00', 50, 0, 5, 'Parent consultation', null],
            [1, '10:30', 50, 1, 7, 'Initial consultation', null],
            [1, '14:00', 50, 2, 3, 'Online session', null],
            [2, '09:30', 50, 0, 0, 'Individual therapy', null],
            [2, '13:00', 50, 3, 4, 'Individual therapy', 'cancelled'],
            [2, '18:00', 50, 1, 7, 'Individual therapy', null],
            [3, '12:00', 50, 2, 3, 'Online session', null],
            [3, '17:00', 50, 1, 1, 'Individual therapy', null],
            [4, '10:00', 50, 0, 5, 'Adolescent therapy', null],
            [4, '11:00', 50, 3, 4, 'Individual therapy', null],
            [4, '15:00', 90, 3, 2, 'Couples therapy', null],
            [5, '11:00', 60, 0, 0, 'Group session', null],
            // Next week
            [7, '09:00', 50, 0, 0, 'Individual therapy', null],
            [7, '11:00', 50, 1, 6, 'Individual therapy', null],
            [8, '14:00', 50, 2, 3, 'Online session', null],
            [9, '10:00', 50, 0, 5, 'Adolescent therapy', null],
            [10, '17:00', 50, 1, 1, 'Individual therapy', null],
            [11, '16:00', 90, 3, 2, 'Couples therapy', null],
        ];

        foreach ($schedule as [$day, $time, $minutes, $staff, $client, $title, $status]) {
            [$hour, $minute] = array_map('intval', explode(':', $time));
            $start = $monday->addDays($day)->setTime($hour, $minute);
            $end = $start->addMinutes($minutes);
            $conversation = $clients[$client];

            Booking::create([
                'tenant_id' => $tenant->id,
                'contact_id' => $conversation->contact_id,
                'conversation_id' => $conversation->id,
                'staff_user_id' => $psychologists[$staff]->id,
                'title' => $title,
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'status' => $status ?? ($end->lessThan($now) ? 'completed' : 'scheduled'),
                'notes' => $title === 'Initial consultation' ? 'First visit — send intake form beforehand.' : null,
                'created_by' => $psychologists[0]->id,
            ]);
        }

        return count($schedule);
    }

    /**
     * @return array<string, Channel>
     */
    private function ensureChannels(Tenant $tenant): array
    {
        $definitions = [
            'web_chat' => 'Website Chat',
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'telegram' => 'Telegram',
            'messenger' => 'Messenger',
        ];

        $channels = [];
        foreach ($definitions as $type => $name) {
            $channels[$type] = Channel::firstOrCreate(
                ['tenant_id' => $tenant->id, 'channel_type' => $type],
                ['name' => $name, 'is_active' => true, 'config' => []]
            );
        }

        return $channels;
    }
}
