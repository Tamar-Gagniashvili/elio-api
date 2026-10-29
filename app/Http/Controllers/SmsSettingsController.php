<?php

namespace App\Http\Controllers;

use App\Enums\SmsProvider;
use App\Http\Controllers\Concerns\AuthorizesTenantAdmin;
use App\Models\SmsSettings;
use App\Services\Sms\SmsSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The current clinic's InexPhone settings. The API key is write-only: responses only say
 * whether one is set.
 */
class SmsSettingsController extends Controller
{
    use AuthorizesTenantAdmin;

    /** InexPhone's API, used unless a clinic enters another address */
    public const DEFAULT_API_URL = 'https://smsservice.inexphone.ge/api/v1';

    public function show(Request $request): JsonResponse
    {
        $this->authorizeTenantAdmin($request, 'Only workspace admins can manage SMS settings.');

        return response()->json($this->present(SmsSettings::first()));
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeTenantAdmin($request, 'Only workspace admins can manage SMS settings.');

        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_url' => ['nullable', 'url', 'max:255'],
            'default_subject' => ['required', 'string', 'max:20'],
            'submit_callback_url' => ['nullable', 'url', 'max:255'],
            'delivery_callback_url' => ['nullable', 'url', 'max:255'],
        ]);

        $settings = SmsSettings::firstOrNew();
        $current = $settings->inex_phone_settings ?? [];

        // A blank key keeps the saved one, so the form never needs to show it
        $apiKey = filled($validated['api_key'] ?? null) ? $validated['api_key'] : ($current['apiKey'] ?? null);

        if (! filled($apiKey)) {
            return response()->json(['message' => 'The API key is required.', 'errors' => ['api_key' => ['The API key is required.']]], 422);
        }

        $settings->fill([
            'sms_provider' => SmsProvider::InexPhone,
            'inex_phone_settings' => [
                'apiKey' => $apiKey,
                'apiUrl' => $validated['api_url'] ?? self::DEFAULT_API_URL,
                'defaultSubject' => $validated['default_subject'],
                'submitCallbackUrl' => $validated['submit_callback_url'] ?? null,
                'deliveryCallbackUrl' => $validated['delivery_callback_url'] ?? null,
            ],
        ])->save();

        SmsSettingsService::clearCache();

        return response()->json($this->present($settings));
    }

    /** @return array<string, mixed> */
    private function present(?SmsSettings $settings): array
    {
        $data = $settings?->inex_phone_settings ?? [];

        return [
            'sms_provider' => $settings?->sms_provider?->value,
            'is_configured' => filled($data['apiKey'] ?? null) && filled($data['defaultSubject'] ?? null),
            'has_api_key' => filled($data['apiKey'] ?? null),
            'api_url' => $data['apiUrl'] ?? self::DEFAULT_API_URL,
            'default_subject' => $data['defaultSubject'] ?? null,
            'submit_callback_url' => $data['submitCallbackUrl'] ?? null,
            'delivery_callback_url' => $data['deliveryCallbackUrl'] ?? null,
        ];
    }
}
