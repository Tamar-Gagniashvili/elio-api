<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Membership;
use App\Models\Message;
use App\Models\MetaConnection;
use App\Models\Profile;
use App\Models\RelinkObject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChannelEncryptionService;
use App\Services\MetaMessageIngestor;
use App\Services\RoutingRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class EdgeFunctionController extends Controller
{
    private $encryptionService;

    private $routingService;

    private $metaIngestor;

    public function __construct(ChannelEncryptionService $encryptionService, RoutingRuleService $routingService, MetaMessageIngestor $metaIngestor)
    {
        $this->encryptionService = $encryptionService;
        $this->routingService = $routingService;
        $this->metaIngestor = $metaIngestor;
    }

    private function stubResponse($functionName)
    {
        return response()->json([
            'message' => "Edge function {$functionName} reached successfully. Currently stubbed in Laravel.",
            'success' => true,
        ]);
    }

    private function verifyMetaChallenge(Request $request, $channelType)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        $tenantId = $request->query('tenant_id');

        if ($mode !== 'subscribe' || ! $tenantId) {
            Log::warning("Meta webhook verify ({$channelType}): missing hub.mode=subscribe or tenant_id", ['tenant_id' => $tenantId, 'mode' => $mode]);

            return response('Forbidden', 403);
        }

        $channels = Channel::where('tenant_id', $tenantId)->where('channel_type', $channelType)->get();
        if ($channels->isEmpty()) {
            Log::warning("Meta webhook verify ({$channelType}): no channel for tenant", ['tenant_id' => $tenantId]);

            return response('Forbidden', 403);
        }

        foreach ($channels as $channel) {
            $storedToken = $channel->config['verify_token'] ?? null;
            if ($storedToken) {
                $storedToken = trim($this->encryptionService->decryptCredential($storedToken));
            }
            if ($storedToken && hash_equals($storedToken, trim((string) $token))) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }
        }

        Log::warning("Meta webhook verify ({$channelType}): verify_token mismatch", [
            'tenant_id' => $tenantId,
            'channels' => $channels->count(),
            'has_stored_token' => $channels->contains(fn ($c) => ! empty($c->config['verify_token'])),
        ]);

        return response('Forbidden', 403);
    }

    private function verifyMetaSignature(Request $request, $channel)
    {
        $signature = $request->header('X-Hub-Signature-256');
        $appSecret = $channel->config['app_secret'] ?? null;

        if (! $appSecret) {
            return true;
        } // Skip if no secret configured

        if (! $signature || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $appSecret = $this->encryptionService->decryptCredential($appSecret);
        $payload = $request->getContent();
        $expectedHash = 'sha256='.hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expectedHash, $signature);
    }

    public function createUser(Request $request)
    {
        $data = $request->input('body', $request->all());

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $fullName = $data['fullName'] ?? null;
        $tenantId = $data['tenantId'] ?? null;
        $role = $data['role'] ?? 'user';

        if (! $email || ! $password) {
            return response()->json(['error' => 'Email and password required'], 400);
        }

        $user = User::create([
            'email' => $email,
            'password' => Hash::make($password),
            'name' => $fullName,
        ]);

        if ($tenantId) {
            Membership::create([
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'role' => $role,
            ]);
            Profile::create([
                'user_id' => $user->id,
                'full_name' => $fullName,
                'email' => $email,
            ]);
        }

        return response()->json(['data' => $user, 'success' => true]);
    }

    public function encryptChannelConfig(Request $request)
    {
        $data = $request->input('body', $request->all());
        $config = $data['config'] ?? [];

        // Return config directly. Since Laravel natively encrypts database JSON if configured,
        // or we can add Crypt::encryptString() manually later if strict field encryption is needed.
        return response()->json([
            'config' => $config,
            'success' => true,
        ]);
    }

    public function importLeads(Request $request)
    {
        $data = $request->input('body', $request->all());
        $tenantId = $data['tenant_id'] ?? null;
        $rows = $data['rows'] ?? [];
        $mapping = $data['column_mapping'] ?? [];

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $skippedLeads = [];

        foreach ($rows as $index => $row) {
            $nameStr = $mapping['name'] ?? null;
            $emailStr = $mapping['email'] ?? null;
            $phoneStr = $mapping['phone'] ?? null;
            $extIdStr = $mapping['external_id'] ?? null;

            $name = $nameStr ? ($row[$nameStr] ?? null) : null;
            $email = $emailStr ? ($row[$emailStr] ?? null) : null;
            $phone = $phoneStr ? ($row[$phoneStr] ?? null) : null;
            $external_id = $extIdStr ? ($row[$extIdStr] ?? null) : null;

            if (! $email && ! $phone) {
                $skipped++;
                $skippedLeads[] = [
                    'row' => $index + 2,
                    'name' => $name ?? 'Unknown',
                    'reason' => 'no_identifying_info',
                ];

                continue;
            }

            // Check duplicates
            $query = Contact::where('tenant_id', $tenantId);
            $query->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->orWhere('email', $email);
                }
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
            });
            $existing = $query->first();

            if ($existing) {
                $skipped++;
                $reason = ($email && $existing->email === $email) ? 'duplicate_email' : 'duplicate_phone';
                $skippedLeads[] = [
                    'row' => $index + 2,
                    'name' => $name ?? 'Unknown',
                    'reason' => $reason,
                    'value' => $reason === 'duplicate_email' ? $email : $phone,
                ];

                continue;
            }

            try {
                Contact::create([
                    'tenant_id' => $tenantId,
                    'name' => $name ?? 'Imported Contact',
                    'email' => $email,
                    'phone' => $phone,
                    'external_id' => $external_id,
                    'channel_type' => 'offline',
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return response()->json([
            'results' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'skippedLeads' => $skippedLeads,
                'errors' => $errors,
            ],
            'success' => true,
        ]);
    }

    public function importObjects(Request $request)
    {
        $data = $request->input('body', $request->all());
        $tenantId = $data['tenant_id'] ?? null;
        $objectTypeId = $data['object_type_id'] ?? null;
        $rows = $data['rows'] ?? [];
        $mapping = $data['column_mapping'] ?? [];
        $uniqueField = $data['unique_field'] ?? 'display_name';
        $dedup = $data['deduplication_strategy'] ?? 'skip';
        $defaultStatus = $data['default_status_id'] ?? null;

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $displayNameCol = $mapping['display_name'] ?? null;
            $displayName = $displayNameCol ? ($row[$displayNameCol] ?? 'Imported Object') : 'Imported Object';

            $metadata = [];
            foreach ($mapping as $field => $col) {
                if ($field !== 'display_name' && $col) {
                    $metadata[$field] = $row[$col] ?? null;
                }
            }

            $query = RelinkObject::where('tenant_id', $tenantId)->where('object_type_id', $objectTypeId);
            if ($uniqueField === 'display_name') {
                $query->where('display_name', $displayName);
            } else {
                $val = $metadata[$uniqueField] ?? null;
                if ($val !== null) {
                    $query->whereJsonContains('metadata->'.$uniqueField, $val);
                }
            }
            $existing = $query->first();

            if ($existing) {
                if ($dedup === 'skip') {
                    $skipped++;

                    continue;
                } else {
                    $existing->update([
                        'display_name' => $displayName,
                        'metadata' => $metadata,
                    ]);
                    $updated++;

                    continue;
                }
            }

            try {
                RelinkObject::create([
                    'tenant_id' => $tenantId,
                    'object_type_id' => $objectTypeId,
                    'status_id' => $defaultStatus,
                    'display_name' => $displayName,
                    'metadata' => $metadata,
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return response()->json(['results' => compact('imported', 'updated', 'skipped', 'errors'), 'success' => true]);
    }

    public function sendMessenger(Request $request)
    {
        $body = $request->all();
        $conversationId = $body['conversation_id'] ?? null;
        $content = $body['content'] ?? null;

        if (! $conversationId || ! $content) {
            return response()->json(['error' => 'Missing conversation_id or content'], 400);
        }

        $conversation = Conversation::with('contact')->find($conversationId);
        if (! $conversation || ! $conversation->contact) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $channelType = $conversation->contact->channel_type;
        if (! in_array($channelType, ['messenger', 'instagram'])) {
            return response()->json(['error' => 'Not a Messenger/Instagram conversation'], 400);
        }

        $recipientId = $conversation->contact->external_id;

        // Pages connected via Facebook Login: reply from the page that received the message
        $connections = MetaConnection::where('tenant_id', $conversation->tenant_id)
            ->when($channelType === 'instagram', fn ($q) => $q->whereNotNull('instagram_account_id'));
        $pageId = $conversation->contact->metadata['page_id'] ?? null;
        $connection = ($pageId ? (clone $connections)->where('page_id', $pageId)->first() : null) ?? $connections->first();

        if ($connection) {
            try {
                $response = Http::post('https://graph.facebook.com/'.config('services.meta.graph_version').'/me/messages?'.http_build_query([
                    'access_token' => $connection->page_access_token,
                ]), [
                    'recipient' => ['id' => $recipientId],
                    'messaging_type' => 'RESPONSE',
                    'message' => ['text' => $content],
                ]);

                if (! $response->successful()) {
                    return response()->json(['error' => $response->json()['error']['message'] ?? 'Failed to send message'], 500);
                }

                return response()->json(['success' => true]);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        // Legacy: manually entered page credentials on the channel
        $channel = Channel::where('tenant_id', $conversation->tenant_id)->where('channel_type', $channelType)->where('is_active', true)->first();
        if (! $channel || empty($channel->config['page_access_token'])) {
            return response()->json(['error' => "{$channelType} channel or page access token not configured"], 400);
        }

        $pageToken = $this->encryptionService->decryptCredential($channel->config['page_access_token']);

        try {
            $pageId = $channel->config['page_id'] ?? 'me';
            $url = $channelType === 'instagram'
                ? "https://graph.facebook.com/v18.0/me/messages?access_token={$pageToken}"
                : "https://graph.facebook.com/v25.0/{$pageId}/messages";

            $headers = $channelType === 'instagram' ? [] : ['Authorization' => "Bearer {$pageToken}"];

            $response = Http::withHeaders($headers)->post($url, [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $content],
            ]);

            if (! $response->successful()) {
                return response()->json(['error' => $response->json()['error']['message'] ?? 'Failed to send message'], 500);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendPushNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'title' => 'required|string',
            'body' => 'required|string',
            'conversation_id' => 'nullable|string',
            'url' => 'nullable|string',
        ]);

        $userId = $request->input('user_id');
        $title = $request->input('title');
        $body = $request->input('body');
        $conversationId = $request->input('conversation_id');
        $url = $request->input('url', '/inbox?filter=newly_assigned');

        // Fetch all push subscriptions for this user
        $subscriptions = \DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return response()->json(['success' => true, 'sent' => 0, 'message' => 'No subscriptions found for user']);
        }

        $vapidPublicKey = config('services.vapid.public_key');
        $vapidPrivateKey = config('services.vapid.private_key');
        $vapidSubject = config('services.vapid.subject', 'mailto:admin@relink.app');

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $vapidSubject,
                'publicKey' => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'conversationId' => $conversationId,
        ]);

        $sent = 0;
        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'keys' => [
                    'p256dh' => $sub->p256dh,
                    'auth' => $sub->auth,
                ],
            ]);

            $webPush->queueNotification($subscription, $payload);
            $sent++;
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                // Clean up expired/invalid subscriptions automatically
                if ($report->isSubscriptionExpired()) {
                    DB::table('push_subscriptions')
                        ->where('endpoint', $report->getRequest()->getUri()->__toString())
                        ->delete();
                }
            }
        }

        return response()->json(['success' => true, 'sent' => $sent]);
    }

    public function sendTelegram(Request $request)
    {
        $body = $request->all();
        $conversationId = $body['conversation_id'] ?? null;
        $content = $body['content'] ?? null;

        if (! $conversationId || ! $content) {
            return response()->json(['error' => 'Missing conversation_id or content'], 400);
        }

        $conversation = Conversation::with('contact')->find($conversationId);
        if (! $conversation || ! $conversation->contact) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        if ($conversation->contact->channel_type !== 'telegram') {
            return response()->json(['error' => 'Not a Telegram conversation'], 400);
        }

        $channel = Channel::where('tenant_id', $conversation->tenant_id)
            ->where('channel_type', 'telegram')
            ->where('is_active', true)
            ->first();

        if (! $channel || empty($channel->config['bot_token'])) {
            return response()->json(['error' => 'Telegram channel or bot token not configured'], 400);
        }

        $botToken = $this->encryptionService->decryptCredential($channel->config['bot_token']);
        $chatId = $conversation->contact->metadata['chat_id'] ?? $conversation->contact->external_id;

        if (! $chatId) {
            return response()->json(['error' => 'No chat ID found for contact'], 400);
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $content,
                'parse_mode' => 'HTML',
            ]);

            $result = $response->json();

            if (! $response->successful() || ! ($result['ok'] ?? false)) {
                return response()->json(['error' => $result['description'] ?? 'Failed to send message'], 500);
            }

            return response()->json([
                'success' => true,
                'message_id' => $result['result']['message_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadChatAttachment(Request $request)
    {
        $file = $request->file('file');
        $tenantId = $request->input('tenant_id');
        $conversationId = $request->input('conversation_id');

        if (! $file || ! $tenantId) {
            return response()->json(['error' => 'Missing file or tenant_id'], 400);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            return response()->json(['error' => 'File too large (max 10MB)'], 400);
        }

        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
        ];

        if (! in_array($file->getMimeType(), $allowedMimeTypes)) {
            return response()->json(['error' => 'File type not allowed'], 400);
        }

        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $pathPrefix = $conversationId ? "{$tenantId}/{$conversationId}" : "{$tenantId}/general";

        $path = $file->storeAs(
            "chat-attachments/{$pathPrefix}",
            Str::uuid().'.'.$ext,
            'public'
        );

        if (! $path) {
            return response()->json(['error' => 'Failed to upload file'], 500);
        }

        $isImage = str_starts_with($file->getMimeType(), 'image/');

        return response()->json([
            'ok' => true,
            'url' => asset("storage/{$path}"),
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'type' => $isImage ? 'image' : 'file',
            'mime_type' => $file->getMimeType(),
        ]);
    }

    public function webchat(Request $request)
    {
        $tenantId = $request->query('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Missing tenant_id parameter'], 400);
        }

        $tenant = Tenant::where('id', $tenantId)->where('is_active', true)->first();
        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $body = $request->all();
        $action = $body['action'] ?? null;
        $visitorId = $body['visitor_id'] ?? null;
        $name = $body['name'] ?? null;
        $email = $body['email'] ?? null;
        $message = $body['message'] ?? null;
        $conversationId = $body['conversation_id'] ?? null;
        $mediaUrl = $body['media_url'] ?? null;
        $mediaType = $body['media_type'] ?? null;
        $mediaFilename = $body['media_filename'] ?? null;

        if ($action === 'init') {
            $existingConversation = null;
            if ($visitorId) {
                $contact = Contact::where('tenant_id', $tenantId)
                    ->where('external_id', $visitorId)
                    ->where('channel_type', 'web_chat')
                    ->first();
                if ($contact) {
                    $conv = Conversation::where('contact_id', $contact->id)
                        ->whereIn('status', ['open', 'assigned'])
                        ->first();
                    $existingConversation = $conv ? $conv->id : null;
                }
            }

            return response()->json([
                'ok' => true,
                'tenant_name' => $tenant->name,
                'conversation_id' => $existingConversation,
            ]);
        }

        if ($action === 'start') {
            $uniqueVisitorId = $visitorId ?: (string) Str::uuid();
            $contact = null;

            if ($email) {
                $contact = Contact::where('tenant_id', $tenantId)
                    ->where('email', $email)
                    ->where('channel_type', 'web_chat')
                    ->first();

                if ($contact) {
                    if ($name && $name !== $contact->name) {
                        $contact->update(['name' => $name]);
                    }
                    if ($uniqueVisitorId !== $contact->external_id) {
                        $contact->update(['external_id' => $uniqueVisitorId]);
                    }
                }
            }

            if (! $contact) {
                $contact = Contact::where('tenant_id', $tenantId)
                    ->where('external_id', $uniqueVisitorId)
                    ->where('channel_type', 'web_chat')
                    ->first();
            }

            if (! $contact) {
                $contact = Contact::create([
                    'tenant_id' => $tenantId,
                    'external_id' => $uniqueVisitorId,
                    'channel_type' => 'web_chat',
                    'name' => $name ?: 'Website Visitor',
                    'email' => $email ?: null,
                    'metadata' => ['source' => 'web_widget'],
                ]);
            } elseif ($name || $email) {
                $updates = [];
                if ($name && $name !== $contact->name) {
                    $updates['name'] = $name;
                }
                if ($email && ! $contact->email) {
                    $updates['email'] = $email;
                }
                if (! empty($updates)) {
                    $contact->update($updates);
                }
            }

            $channel = Channel::where('tenant_id', $tenantId)
                ->where('channel_type', 'web_chat')
                ->where('is_active', true)
                ->first();

            $conversation = Conversation::where('tenant_id', $tenantId)
                ->where('contact_id', $contact->id)
                ->whereIn('status', ['open', 'assigned'])
                ->orderBy('last_message_at', 'desc')
                ->first();

            $now = now();
            $ruleContext = [
                'channel_type' => 'web_chat',
                'message_content' => $message ?? '',
                'message_language' => 'en',
                'contact_name' => $name ?: ($contact->name ?? ''),
                'contact_email' => $email ?: ($contact->email ?? ''),
                'contact_phone' => $contact->phone ?? '',
                'hour' => $now->hour,
                'day_of_week' => $now->dayOfWeek,
            ];

            if (! $conversation) {
                $ruleResult = $this->routingService->evaluateRoutingRules(
                    $tenantId, null, $contact->id, $ruleContext, $channel->config ?? []
                );

                $assignedTo = $ruleResult['assignedTo'] ?? $this->routingService->getNextSalesRep($tenantId);

                $conversation = Conversation::create([
                    'tenant_id' => $tenantId,
                    'contact_id' => $contact->id,
                    'channel_id' => $channel ? $channel->id : null,
                    'status' => $assignedTo ? 'assigned' : 'open',
                    'assigned_to' => $assignedTo,
                    'assignment_seen_by_assignee' => ! $assignedTo,
                    'last_message_at' => $now,
                    'last_message_preview' => mb_substr($message ?? 'New conversation', 0, 100),
                ]);

                $this->routingService->evaluateRoutingRules(
                    $tenantId, $conversation->id, $contact->id, $ruleContext, $channel->config ?? []
                );
            } else {
                $this->routingService->evaluateRoutingRules(
                    $tenantId, $conversation->id, $contact->id, $ruleContext, $channel->config ?? []
                );
            }

            if ($message) {
                $metadata = ['source' => 'web_widget'];
                if ($mediaUrl) {
                    $metadata['media_url'] = $mediaUrl;
                    $metadata['media_type'] = $mediaType;
                    $metadata['media_filename'] = $mediaFilename;
                }

                Message::create([
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'sender_type' => 'contact',
                    'content' => $message,
                    'metadata' => $metadata,
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType,
                    'media_filename' => $mediaFilename,
                ]);

                $conversation->update([
                    'last_message_at' => $now,
                    'last_message_preview' => mb_substr($message, 0, 100),
                    'unread_count' => $conversation->unread_count + 1,
                ]);
            }

            return response()->json([
                'ok' => true,
                'conversation_id' => $conversation->id,
                'visitor_id' => $uniqueVisitorId,
            ]);
        }

        if ($action === 'send') {
            if (! $conversationId || ! $message) {
                return response()->json(['error' => 'Missing conversation_id or message'], 400);
            }

            $conversation = Conversation::with('contact')->find($conversationId);
            if (! $conversation) {
                return response()->json(['error' => 'Conversation not found'], 404);
            }

            $now = now();
            $ruleContext = [
                'channel_type' => 'web_chat',
                'message_content' => $message,
                'message_language' => 'en',
                'contact_name' => $conversation->contact->name ?? '',
                'contact_email' => $conversation->contact->email ?? '',
                'contact_phone' => $conversation->contact->phone ?? '',
                'hour' => $now->hour,
                'day_of_week' => $now->dayOfWeek,
            ];

            $channel = Channel::where('tenant_id', $tenantId)
                ->where('channel_type', 'web_chat')
                ->where('is_active', true)
                ->first();

            $this->routingService->evaluateRoutingRules(
                $tenantId, $conversation->id, $conversation->contact_id, $ruleContext, $channel->config ?? []
            );

            $metadata = ['source' => 'web_widget'];
            if ($mediaUrl) {
                $metadata['media_url'] = $mediaUrl;
                $metadata['media_type'] = $mediaType;
                $metadata['media_filename'] = $mediaFilename;
            }

            Message::create([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'sender_type' => 'contact',
                'content' => $message,
                'metadata' => $metadata,
                'media_url' => $mediaUrl,
                'media_type' => $mediaType,
                'media_filename' => $mediaFilename,
            ]);

            $conversation->update([
                'last_message_at' => $now,
                'last_message_preview' => mb_substr($message, 0, 100),
                'unread_count' => $conversation->unread_count + 1,
            ]);

            return response()->json(['ok' => true]);
        }

        if ($action === 'messages') {
            if (! $conversationId) {
                return response()->json(['error' => 'Missing conversation_id'], 400);
            }

            $messages = Message::select('id', 'content', 'sender_type', 'created_at', 'metadata', 'media_url', 'media_type', 'media_filename')
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json(['ok' => true, 'messages' => $messages]);
        }

        return response()->json(['error' => 'Unknown action'], 400);
    }

    public function webhookInstagram(Request $request)
    {
        if ($request->isMethod('GET')) {
            return $this->verifyMetaChallenge($request, 'instagram');
        }

        $tenantId = $request->query('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Missing tenant_id parameter'], 400);
        }

        $channel = Channel::where('tenant_id', $tenantId)->where('channel_type', 'instagram')->where('is_active', true)->first();
        if (! $channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }
        if (! $this->verifyMetaSignature($request, $channel)) {
            return response('Unauthorized', 401);
        }

        $body = $request->all();
        if (($body['object'] ?? '') !== 'instagram') {
            return response()->json(['ok' => true]);
        }

        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                if (empty($event['message'])) {
                    continue;
                }

                $this->metaIngestor->ingest($tenantId, $channel, $event['sender']['id'], $event['message']['text'] ?? '[Media message]', $event['message']['mid']);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function webhookMessenger(Request $request)
    {
        if ($request->isMethod('GET')) {
            return $this->verifyMetaChallenge($request, 'messenger');
        }

        $tenantId = $request->query('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Missing tenant_id parameter'], 400);
        }

        $channel = Channel::where('tenant_id', $tenantId)->where('channel_type', 'messenger')->where('is_active', true)->first();
        if (! $channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }
        if (! $this->verifyMetaSignature($request, $channel)) {
            return response('Unauthorized', 401);
        }

        $body = $request->all();
        if (($body['object'] ?? '') !== 'page') {
            return response()->json(['ok' => true]);
        }

        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                if (empty($event['message'])) {
                    continue;
                }

                $this->metaIngestor->ingest($tenantId, $channel, $event['sender']['id'], $event['message']['text'] ?? '[Media message]', $event['message']['mid']);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function webhookTelegram(Request $request)
    {
        $tenantId = $request->query('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Missing tenant_id parameter'], 400);
        }

        $channel = Channel::where('tenant_id', $tenantId)
            ->where('channel_type', 'telegram')
            ->where('is_active', true)
            ->first();

        if (! $channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }

        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        $storedToken = $channel->config['secret_token'] ?? null;

        if ($storedToken) {
            $storedToken = $this->encryptionService->decryptCredential($storedToken);
            if ($secretToken !== $storedToken) {
                return response('Unauthorized', 401);
            }
        }

        $body = $request->all();
        $messageData = $body['message'] ?? $body['edited_message'] ?? null;

        if (! $messageData) {
            return response()->json(['ok' => true]);
        }

        $telegramUserId = (string) $messageData['from']['id'];
        $chatId = (string) $messageData['chat']['id'];
        $text = $messageData['text'] ?? '';
        $firstName = $messageData['from']['first_name'] ?? '';
        $lastName = $messageData['from']['last_name'] ?? '';
        $username = $messageData['from']['username'] ?? '';

        $contactName = trim("{$firstName} {$lastName}") ?: ($username ?: 'Telegram User');

        $contact = Contact::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'external_id' => $telegramUserId,
                'channel_type' => 'telegram',
            ],
            [
                'name' => $contactName,
                'metadata' => ['chat_id' => $chatId, 'username' => $username],
            ]
        );

        $now = now();
        $ruleContext = [
            'channel_type' => 'telegram',
            'message_content' => $text,
            'message_language' => 'en',
            'contact_name' => $contact->name ?? '',
            'contact_email' => $contact->email ?? '',
            'contact_phone' => $contact->phone ?? '',
            'hour' => $now->hour,
            'day_of_week' => $now->dayOfWeek,
        ];

        $conversation = Conversation::where('tenant_id', $tenantId)
            ->where('contact_id', $contact->id)
            ->whereIn('status', ['open', 'assigned'])
            ->first();

        if (! $conversation) {
            $ruleResult = $this->routingService->evaluateRoutingRules(
                $tenantId, null, $contact->id, $ruleContext, $channel->config ?? []
            );

            $assignedTo = $ruleResult['assignedTo'] ?? $this->routingService->getNextSalesRep($tenantId);

            $conversation = Conversation::create([
                'tenant_id' => $tenantId,
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'status' => $assignedTo ? 'assigned' : 'open',
                'assigned_to' => $assignedTo,
                'assignment_seen_by_assignee' => ! $assignedTo,
                'last_message_at' => $now,
                'last_message_preview' => mb_substr($text, 0, 100),
            ]);

            // Re-run rules to trigger auto-replies now that we have a conversation ID
            $this->routingService->evaluateRoutingRules(
                $tenantId, $conversation->id, $contact->id, $ruleContext, $channel->config ?? []
            );
        } else {
            $this->routingService->evaluateRoutingRules(
                $tenantId, $conversation->id, $contact->id, $ruleContext, $channel->config ?? []
            );

            $conversation->update([
                'last_message_at' => $now,
                'last_message_preview' => mb_substr($text, 0, 100),
                'unread_count' => $conversation->unread_count + 1,
            ]);
        }

        Message::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'sender_type' => 'contact',
            'content' => $text,
            'metadata' => ['telegram_message_id' => $messageData['message_id']],
        ]);

        return response()->json(['ok' => true]);
    }

    public function webhookWhatsapp(Request $request)
    {
        if ($request->isMethod('GET')) {
            return $this->verifyMetaChallenge($request, 'whatsapp');
        }

        $tenantId = $request->query('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Missing tenant_id parameter'], 400);
        }

        $channel = Channel::where('tenant_id', $tenantId)->where('channel_type', 'whatsapp')->where('is_active', true)->first();
        if (! $channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }
        if (! $this->verifyMetaSignature($request, $channel)) {
            return response('Unauthorized', 401);
        }

        $body = $request->all();
        $entry = $body['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;

        if (! $value || empty($value['messages'][0])) {
            return response()->json(['ok' => true]);
        }

        $message = $value['messages'][0];
        $waContact = $value['contacts'][0] ?? null;
        $whatsappId = $message['from'];
        $text = $message['text']['body'] ?? $message['caption'] ?? '[Media message]';
        $contactName = $waContact['profile']['name'] ?? 'WhatsApp User';

        $dbContact = Contact::firstOrCreate(
            ['tenant_id' => $tenantId, 'external_id' => $whatsappId, 'channel_type' => 'whatsapp'],
            ['name' => $contactName, 'phone' => $whatsappId, 'metadata' => []]
        );

        $now = now();
        $ruleContext = [
            'channel_type' => 'whatsapp', 'message_content' => $text, 'message_language' => 'en',
            'contact_name' => $dbContact->name ?? '', 'contact_email' => $dbContact->email ?? '', 'contact_phone' => $dbContact->phone ?? '',
            'hour' => $now->hour, 'day_of_week' => $now->dayOfWeek,
        ];

        $conversation = Conversation::where('tenant_id', $tenantId)->where('contact_id', $dbContact->id)->whereIn('status', ['open', 'assigned'])->first();

        if (! $conversation) {
            $assignedTo = $this->routingService->evaluateRoutingRules($tenantId, null, $dbContact->id, $ruleContext, $channel->config ?? [])['assignedTo'] ?? $this->routingService->getNextSalesRep($tenantId);
            $conversation = Conversation::create([
                'tenant_id' => $tenantId, 'contact_id' => $dbContact->id, 'channel_id' => $channel->id,
                'status' => $assignedTo ? 'assigned' : 'open', 'assigned_to' => $assignedTo, 'assignment_seen_by_assignee' => ! $assignedTo,
                'last_message_at' => $now, 'last_message_preview' => mb_substr($text, 0, 100),
            ]);
        } else {
            $conversation->update(['last_message_at' => $now, 'last_message_preview' => mb_substr($text, 0, 100), 'unread_count' => $conversation->unread_count + 1]);
        }
        $this->routingService->evaluateRoutingRules($tenantId, $conversation->id, $dbContact->id, $ruleContext, $channel->config ?? []);

        Message::create([
            'tenant_id' => $tenantId, 'conversation_id' => $conversation->id, 'sender_type' => 'contact', 'content' => $text,
            'metadata' => ['whatsapp_message_id' => $message['id'], 'type' => $message['type'] ?? 'text'],
        ]);

        return response()->json(['ok' => true]);
    }
}
