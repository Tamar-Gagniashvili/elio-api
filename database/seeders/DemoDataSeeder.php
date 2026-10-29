<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Seeds a handful of channels, contacts, conversations, and messages
     * for the first tenant so the inbox has realistic demo data.
     */
    public function run(): void
    {
        Model::withoutEvents(function () {
            $tenant = Tenant::query()->orderBy('created_at')->firstOrFail();
            $agents = User::whereIn('id', [3, 4])->get();

            $channels = $this->ensureChannels($tenant);

            $scenarios = [
                [
                    'channel_type' => 'web_chat',
                    'contact' => ['name' => 'Nino Beridze', 'email' => 'nino.beridze@example.com'],
                    'status' => 'open',
                    'thread' => [
                        ['contact', "Hi, do you offer same-day delivery in Tbilisi?"],
                        ['agent', "Hey Nino! Yes, orders placed before 2pm ship same day."],
                        ['contact', "Great, how much is shipping?"],
                        ['agent', "It's free for orders over 100 GEL, otherwise 8 GEL."],
                        ['contact', "Perfect, placing an order now, thanks!"],
                    ],
                ],
                [
                    'channel_type' => 'web_chat',
                    'contact' => ['name' => 'Giorgi Kapanadze', 'email' => 'g.kapanadze@example.com'],
                    'status' => 'assigned',
                    'thread' => [
                        ['contact', "My order #4821 hasn't arrived yet, it's been 5 days"],
                        ['system', "Conversation assigned to sale 1"],
                        ['agent', "Sorry about that Giorgi, let me check the tracking for you."],
                        ['agent', "Looks like it's stuck at customs, I'm escalating now."],
                        ['contact', "Ok thank you, please keep me posted"],
                    ],
                ],
                [
                    'channel_type' => 'telegram',
                    'contact' => ['name' => 'Ana Lomidze', 'phone' => '+995555123456'],
                    'status' => 'assigned',
                    'thread' => [
                        ['contact', "Salami! Can I pay with card on delivery?"],
                        ['agent', "Hi Ana, yes card and cash on delivery are both available."],
                        ['contact', "Sounds good, thanks"],
                    ],
                ],
                [
                    'channel_type' => 'telegram',
                    'contact' => ['name' => 'Levan Tsiklauri', 'phone' => '+995555987654'],
                    'status' => 'open',
                    'thread' => [
                        ['contact', "Is the blue jacket still in stock in size L?"],
                        ['contact', "Also, what's the return policy?"],
                    ],
                ],
                [
                    'channel_type' => 'whatsapp',
                    'contact' => ['name' => 'Mariam Kiladze', 'phone' => '+995599112233'],
                    'status' => 'closed',
                    'thread' => [
                        ['contact', "Hello, I'd like to cancel order #4790"],
                        ['agent', "Hi Mariam, I've cancelled it and refunded your payment."],
                        ['contact', "Thank you so much!"],
                        ['agent', "You're welcome, have a great day!"],
                    ],
                ],
                [
                    'channel_type' => 'whatsapp',
                    'contact' => ['name' => 'Davit Chkheidze', 'phone' => '+995599445566'],
                    'status' => 'open',
                    'thread' => [
                        ['contact', "Do you have a store in Batumi?"],
                    ],
                ],
                [
                    'channel_type' => 'instagram',
                    'contact' => ['name' => 'sopho.ig', 'external_id' => 'ig_17841400001'],
                    'status' => 'assigned',
                    'thread' => [
                        ['contact', "saw your story, is the discount code still active?"],
                        ['agent', "Yes! Use WELCOME10 at checkout for 10% off."],
                        ['contact', "amazing, thank you!! 😍"],
                    ],
                ],
                [
                    'channel_type' => 'instagram',
                    'contact' => ['name' => 'beka_official', 'external_id' => 'ig_17841400002'],
                    'status' => 'open',
                    'thread' => [
                        ['contact', "can you dm me pricing for bulk orders?"],
                    ],
                ],
                [
                    'channel_type' => 'messenger',
                    'contact' => ['name' => 'Tamar Gogua', 'external_id' => 'fb_10236600001'],
                    'status' => 'assigned',
                    'thread' => [
                        ['contact', "Hi, I never received a confirmation email for my order"],
                        ['agent', "Let me resend that for you now, one sec."],
                        ['agent', "Sent! Let me know if it still doesn't show up."],
                        ['contact', "Got it, thank you!"],
                    ],
                ],
                [
                    'channel_type' => 'tiktok',
                    'contact' => ['name' => 'niniko_shops', 'external_id' => 'tt_9001'],
                    'status' => 'open',
                    'thread' => [
                        ['contact', "saw this on your tiktok live, still available?"],
                        ['contact', "the black one in the video"],
                    ],
                ],
            ];

            $baseTime = now()->subDays(3);

            foreach ($scenarios as $i => $scenario) {
                $channel = $channels[$scenario['channel_type']];

                $contact = Contact::create(array_merge([
                    'tenant_id' => $tenant->id,
                    'channel_type' => $scenario['channel_type'],
                    'external_id' => $scenario['contact']['external_id'] ?? Str::uuid(),
                ], $scenario['contact']));

                $assignedAgent = in_array($scenario['status'], ['assigned', 'closed'], true)
                    ? $agents->random()
                    : null;

                $conversation = Conversation::create([
                    'tenant_id' => $tenant->id,
                    'contact_id' => $contact->id,
                    'channel_id' => $channel->id,
                    'assigned_to' => $assignedAgent?->id,
                    'status' => $scenario['status'],
                    'assignment_reason' => $assignedAgent ? 'manual' : null,
                    'assignment_seen_by_assignee' => (bool) $assignedAgent,
                ]);

                $timestamp = (clone $baseTime)->addHours($i * 4);
                $lastMessage = null;
                $unread = 0;

                foreach ($scenario['thread'] as [$senderType, $content]) {
                    $timestamp = $timestamp->clone()->addMinutes(rand(2, 25));

                    $message = Message::create([
                        'tenant_id' => $tenant->id,
                        'conversation_id' => $conversation->id,
                        'sender_type' => $senderType,
                        'sender_id' => $senderType === 'agent' ? ($assignedAgent?->id ?? $agents->random()->id) : null,
                        'content' => $content,
                        'is_read' => $senderType !== 'contact',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    if ($senderType === 'contact') {
                        $unread++;
                    }

                    $lastMessage = $message;
                }

                $conversation->update([
                    'last_message_at' => $lastMessage->created_at,
                    'last_message_preview' => Str::limit($lastMessage->content, 120),
                    'unread_count' => $scenario['status'] === 'closed' ? 0 : $unread,
                ]);
            }
        });
    }

    /**
     * @return array<string, Channel>
     */
    private function ensureChannels(Tenant $tenant): array
    {
        $definitions = [
            'web_chat' => ['name' => 'Web Chat', 'config' => []],
            'telegram' => ['name' => 'Telegram', 'config' => ['bot_token' => 'demo_bot_token']],
            'whatsapp' => ['name' => 'WhatsApp', 'config' => ['phone_number_id' => 'demo_phone_id', 'access_token' => 'demo_access_token']],
            'instagram' => ['name' => 'Instagram', 'config' => ['page_id' => 'demo_page_id', 'access_token' => 'demo_access_token']],
            'messenger' => ['name' => 'Messenger', 'config' => ['page_id' => 'demo_page_id', 'access_token' => 'demo_access_token']],
            'tiktok' => ['name' => 'TikTok', 'config' => ['app_id' => 'demo_app_id', 'access_token' => 'demo_access_token']],
        ];

        $channels = [];

        foreach ($definitions as $type => $attrs) {
            $channels[$type] = Channel::firstOrCreate(
                ['tenant_id' => $tenant->id, 'channel_type' => $type],
                array_merge(['tenant_id' => $tenant->id, 'channel_type' => $type, 'is_active' => true], $attrs)
            );
        }

        return $channels;
    }
}
