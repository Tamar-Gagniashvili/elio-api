<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Membership;
use App\Models\MetaConnection;
use App\Services\MetaMessageIngestor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

/**
 * Facebook Login for Business: tenants connect their Pages (+ linked Instagram accounts) with one login,
 * and a single app-level webhook routes events to tenants by page / Instagram account id.
 */
class MetaController extends Controller
{
    private const PAGE_WEBHOOK_FIELDS = 'messages,messaging_postbacks,messaging_optins,message_deliveries,message_reads';

    public function __construct(protected MetaMessageIngestor $ingestor) {}

    private function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.meta.graph_version').'/'.ltrim($path, '/');
    }

    /** Only redirect back to our own frontends. */
    private function isAllowedReturnUrl(?string $url): bool
    {
        $host = parse_url((string) $url, PHP_URL_HOST);

        return $host && preg_match('/(^|\.)(relink\.ge|relink\.app|localhost)$/', $host);
    }

    private function redirectBack(string $returnUrl, array $params)
    {
        $separator = str_contains($returnUrl, '?') ? '&' : '?';

        return redirect()->away($returnUrl.$separator.http_build_query($params));
    }

    // ── Connect flow ────────────────────────────────────────────────────────

    /** GET /meta/connect-url — returns the Facebook Login dialog URL for the current tenant. */
    public function connectUrl(Request $request)
    {
        $tenant = Tenant::current();
        $user = $request->user();

        $isAdmin = Membership::where('tenant_id', $tenant->id)->where('user_id', $user->id)
            ->whereIn('role', ['admin', 'owner'])->exists();
        if (! $isAdmin) {
            return response()->json(['error' => 'Only workspace admins can connect channels'], 403);
        }

        $returnUrl = $request->query('return_url');
        if (! $this->isAllowedReturnUrl($returnUrl)) {
            return response()->json(['error' => 'Invalid return_url'], 422);
        }

        if (! config('services.meta.app_id') || ! config('services.meta.login_config_id')) {
            return response()->json(['error' => 'Meta app is not configured'], 500);
        }

        $state = Crypt::encryptString(json_encode([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'return_url' => $returnUrl,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]));

        $url = 'https://www.facebook.com/'.config('services.meta.graph_version').'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => config('services.meta.redirect_uri'),
            'config_id' => config('services.meta.login_config_id'),
            'response_type' => 'code',
            'override_default_response_type' => 'true',
            'state' => $state,
        ]);

        return response()->json(['url' => $url]);
    }

    /** GET /auth/facebook/callback — Meta redirects here after login. */
    public function callback(Request $request)
    {
        try {
            $state = json_decode(Crypt::decryptString((string) $request->query('state')), true);
        } catch (\Throwable $e) {
            return response('Invalid state', 400);
        }

        $returnUrl = $state['return_url'] ?? null;
        if (! $this->isAllowedReturnUrl($returnUrl)) {
            return response('Invalid state', 400);
        }
        if (($state['expires_at'] ?? 0) < now()->timestamp) {
            return $this->redirectBack($returnUrl, ['meta' => 'error', 'meta_error' => 'Login session expired, please try again']);
        }
        if ($request->query('error') || ! $request->query('code')) {
            return $this->redirectBack($returnUrl, ['meta' => 'cancelled']);
        }

        $tenantId = $state['tenant_id'];

        try {
            // 1. Code -> short-lived user token
            $short = Http::get($this->graphUrl('oauth/access_token'), [
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'redirect_uri' => config('services.meta.redirect_uri'),
                'code' => $request->query('code'),
            ])->throw()->json();

            // 2. Short-lived -> long-lived user token (~60 days); page tokens derived from it don't expire
            $long = Http::get($this->graphUrl('oauth/access_token'), [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'fb_exchange_token' => $short['access_token'],
            ])->throw()->json();
            $userToken = $long['access_token'];

            $fbUserId = Http::get($this->graphUrl('me'), ['access_token' => $userToken])->throw()->json('id');

            // 3. Pages the user granted, with their linked Instagram business accounts
            $pages = Http::get($this->graphUrl('me/accounts'), [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
                'limit' => 100,
                'access_token' => $userToken,
            ])->throw()->json('data') ?? [];
        } catch (\Throwable $e) {
            Log::error('Meta OAuth callback failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);

            return $this->redirectBack($returnUrl, ['meta' => 'error', 'meta_error' => 'Could not complete Facebook login']);
        }

        if (empty($pages)) {
            return $this->redirectBack($returnUrl, ['meta' => 'error', 'meta_error' => 'No Facebook Pages were selected']);
        }

        $connected = 0;
        $skipped = [];
        $hasInstagram = false;

        foreach ($pages as $page) {
            $existing = MetaConnection::where('page_id', $page['id'])->first();
            if ($existing && $existing->tenant_id !== $tenantId) {
                $skipped[] = $page['name'];

                continue; // page already belongs to another workspace
            }

            $ig = $page['instagram_business_account'] ?? null;
            $hasInstagram = $hasInstagram || $ig;

            MetaConnection::updateOrCreate(
                ['page_id' => $page['id']],
                [
                    'tenant_id' => $tenantId,
                    'facebook_user_id' => $fbUserId,
                    'page_name' => $page['name'],
                    'page_access_token' => $page['access_token'],
                    'instagram_account_id' => $ig['id'] ?? null,
                    'instagram_username' => $ig['username'] ?? null,
                    'user_access_token' => $userToken,
                    'user_token_expires_at' => isset($long['expires_in']) ? now()->addSeconds($long['expires_in']) : null,
                ]
            );

            // 4. Subscribe the page to our app so its events hit the global webhook
            $subscribed = Http::asForm()->post($this->graphUrl("{$page['id']}/subscribed_apps"), [
                'subscribed_fields' => self::PAGE_WEBHOOK_FIELDS,
                'access_token' => $page['access_token'],
            ]);
            if (! $subscribed->successful()) {
                Log::warning('Meta page subscription failed', ['page_id' => $page['id'], 'response' => $subscribed->json()]);
            }

            $connected++;
        }

        if ($connected > 0) {
            $this->activateChannel($tenantId, 'messenger');
            if ($hasInstagram) {
                $this->activateChannel($tenantId, 'instagram');
            }
        }

        return $this->redirectBack($returnUrl, array_filter([
            'meta' => $connected > 0 ? 'connected' : 'error',
            'meta_pages' => $connected,
            'meta_error' => $skipped ? 'Already connected to another workspace: '.implode(', ', $skipped) : null,
        ]));
    }

    private function activateChannel(string $tenantId, string $channelType): void
    {
        $channel = Channel::firstOrCreate(
            ['tenant_id' => $tenantId, 'channel_type' => $channelType],
            ['name' => $channelType === 'instagram' ? 'Instagram' : 'Messenger', 'config' => [], 'is_active' => true]
        );
        $channel->update(['is_active' => true, 'config' => array_merge($channel->config ?? [], ['connection' => 'facebook_login'])]);
    }

    // ── Connections management ──────────────────────────────────────────────

    /** GET /meta/connections */
    public function index()
    {
        return response()->json(MetaConnection::orderBy('created_at')->get());
    }

    /** DELETE /meta/connections/{metaConnection} */
    public function destroy(MetaConnection $metaConnection)
    {
        try {
            Http::delete($this->graphUrl("{$metaConnection->page_id}/subscribed_apps").'?'.http_build_query([
                'access_token' => $metaConnection->page_access_token,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Meta page unsubscribe failed', ['page_id' => $metaConnection->page_id, 'error' => $e->getMessage()]);
        }

        $tenantId = $metaConnection->tenant_id;
        $metaConnection->delete();

        $remaining = MetaConnection::where('tenant_id', $tenantId);
        if (! (clone $remaining)->exists()) {
            Channel::where('tenant_id', $tenantId)->where('channel_type', 'messenger')->update(['is_active' => false]);
        }
        if (! (clone $remaining)->whereNotNull('instagram_account_id')->exists()) {
            Channel::where('tenant_id', $tenantId)->where('channel_type', 'instagram')->update(['is_active' => false]);
        }

        return response()->json(null, 204);
    }

    // ── Global webhook ──────────────────────────────────────────────────────

    /** GET|POST /webhooks/facebook — one webhook for every tenant. */
    public function webhook(Request $request)
    {
        if ($request->isMethod('GET')) {
            $verifyToken = (string) config('services.meta.verify_token');
            if ($request->query('hub_mode') === 'subscribe' && $verifyToken !== ''
                && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
                return response($request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        $appSecret = (string) config('services.meta.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);
        if ($appSecret === '' || ! hash_equals($expected, $signature)) {
            Log::warning('Meta webhook: invalid signature');

            return response('Unauthorized', 401);
        }

        $object = $request->input('object');
        if (! in_array($object, ['page', 'instagram'])) {
            return response()->json(['ok' => true]);
        }

        foreach ($request->input('entry', []) as $entry) {
            // Messenger entries are keyed by page id, Instagram entries by Instagram account id
            $connection = $object === 'page'
                ? MetaConnection::where('page_id', $entry['id'] ?? null)->first()
                : MetaConnection::where('instagram_account_id', $entry['id'] ?? null)->first();

            if (! $connection) {
                Log::info('Meta webhook: no connection for entry', ['object' => $object, 'id' => $entry['id'] ?? null]);

                continue;
            }

            $channelType = $object === 'page' ? 'messenger' : 'instagram';
            $channel = Channel::where('tenant_id', $connection->tenant_id)->where('channel_type', $channelType)->where('is_active', true)->first();
            if (! $channel) {
                continue;
            }

            foreach ($entry['messaging'] ?? [] as $event) {
                $message = $event['message'] ?? null;
                if (! $message || ! empty($message['is_echo'])) {
                    continue;
                }

                try {
                    $this->ingestor->ingest(
                        $connection->tenant_id,
                        $channel,
                        $event['sender']['id'],
                        $message['text'] ?? '[Media message]',
                        $message['mid'] ?? '',
                        ['page_id' => $connection->page_id],
                        $connection->page_access_token
                    );
                } catch (\Throwable $e) {
                    // Never fail the webhook: Meta retries and eventually disables failing endpoints
                    Log::error('Meta webhook: failed to ingest message', ['page_id' => $connection->page_id, 'error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
