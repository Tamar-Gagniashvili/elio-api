<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stores an inbound Messenger / Instagram message: contact, conversation (with routing) and message.
 */
class MetaMessageIngestor
{
    public function __construct(protected RoutingRuleService $routingService) {}

    public function ingest(string $tenantId, Channel $channel, string $senderId, string $text, string $messageId, array $contactMetadata = [], ?string $pageAccessToken = null): void
    {
        $channelType = $channel->channel_type;
        $defaultName = $channelType === 'instagram' ? 'Instagram User' : 'Messenger User';

        $contact = Contact::firstOrCreate(
            ['tenant_id' => $tenantId, 'external_id' => $senderId, 'channel_type' => $channelType],
            ['name' => $defaultName, 'metadata' => $contactMetadata]
        );

        // Replace the placeholder with the sender's real name (retried on later messages if the lookup fails)
        if ($pageAccessToken && $contact->name === $defaultName) {
            $profileName = $this->fetchProfileName($senderId, $channelType, $pageAccessToken);
            if ($profileName) {
                $contact->update(['name' => $profileName]);
            }
        }

        // Keep the page routing key current so replies go out from the right page
        $mergedMetadata = array_merge($contact->metadata ?? [], $contactMetadata);
        if ($mergedMetadata !== ($contact->metadata ?? [])) {
            $contact->update(['metadata' => $mergedMetadata]);
        }

        $now = now();
        $ruleContext = [
            'channel_type' => $channelType, 'message_content' => $text, 'message_language' => 'en',
            'contact_name' => $contact->name ?? '', 'contact_email' => $contact->email ?? '', 'contact_phone' => $contact->phone ?? '',
            'hour' => $now->hour, 'day_of_week' => $now->dayOfWeek,
        ];

        $conversation = Conversation::where('tenant_id', $tenantId)->where('contact_id', $contact->id)->whereIn('status', ['open', 'assigned'])->first();

        if (! $conversation) {
            $assignedTo = $this->routingService->evaluateRoutingRules($tenantId, null, $contact->id, $ruleContext, $channel->config ?? [])['assignedTo'] ?? $this->routingService->getNextSalesRep($tenantId);
            $conversation = Conversation::create([
                'tenant_id' => $tenantId, 'contact_id' => $contact->id, 'channel_id' => $channel->id,
                'status' => $assignedTo ? 'assigned' : 'open', 'assigned_to' => $assignedTo, 'assignment_seen_by_assignee' => ! $assignedTo,
                'last_message_at' => $now, 'last_message_preview' => mb_substr($text, 0, 100),
            ]);
        } else {
            $conversation->update(['last_message_at' => $now, 'last_message_preview' => mb_substr($text, 0, 100), 'unread_count' => $conversation->unread_count + 1]);
        }
        $this->routingService->evaluateRoutingRules($tenantId, $conversation->id, $contact->id, $ruleContext, $channel->config ?? []);

        Message::create([
            'tenant_id' => $tenantId, 'conversation_id' => $conversation->id, 'sender_type' => 'contact', 'content' => $text,
            'metadata' => ["{$channelType}_message_id" => $messageId],
        ]);
    }

    /**
     * Name of a Messenger PSID / Instagram IGSID. New apps only get `name` (no profile_pic),
     * so avatars fall back to initials in the UI.
     */
    private function fetchProfileName(string $senderId, string $channelType, string $pageAccessToken): ?string
    {
        try {
            $profile = Http::timeout(5)->get('https://graph.facebook.com/'.config('services.meta.graph_version')."/{$senderId}", [
                'fields' => $channelType === 'instagram' ? 'name,username' : 'name',
                'access_token' => $pageAccessToken,
            ]);

            if (! $profile->successful()) {
                Log::warning('Meta profile lookup failed', ['sender_id' => $senderId, 'response' => $profile->json('error.message')]);

                return null;
            }

            return $profile->json('name') ?: $profile->json('username');
        } catch (\Throwable $e) {
            Log::warning('Meta profile lookup failed', ['sender_id' => $senderId, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
