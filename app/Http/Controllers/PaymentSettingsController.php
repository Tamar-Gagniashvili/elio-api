<?php

namespace App\Http\Controllers;

use App\Actions\RequestAdvancePaymentAction;
use App\Data\Payment\BogCredentialsData;
use App\Data\Payment\FlittCredentialsData;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Concerns\AuthorizesTenantAdmin;
use App\Models\PaymentSettings;
use App\Services\Payment\PaymentSettingsService;
use App\Services\Sms\SmsSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The current clinic's payment provider and merchant details. Secrets are write-only:
 * responses only say whether one is saved, and a blank secret keeps the saved one.
 */
class PaymentSettingsController extends Controller
{
    use AuthorizesTenantAdmin;

    public function show(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json($this->present(PaymentSettings::first()));
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'bog_client_id' => ['nullable', 'string', 'max:255'],
            'bog_client_secret' => ['nullable', 'string', 'max:500'],
            'flitt_merchant_id' => ['nullable', 'integer', 'min:1'],
            'flitt_secret_key' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = PaymentSettings::firstOrNew();
        $bog = $settings->bog_settings ?? [];
        $flitt = $settings->flitt_settings ?? [];

        $bog = [
            'client_id' => $validated['bog_client_id'] ?? ($bog['client_id'] ?? null),
            'client_secret' => filled($validated['bog_client_secret'] ?? null) ? $validated['bog_client_secret'] : ($bog['client_secret'] ?? null),
        ];
        $flitt = [
            'merchant_id' => $validated['flitt_merchant_id'] ?? ($flitt['merchant_id'] ?? null),
            'secret_key' => filled($validated['flitt_secret_key'] ?? null) ? $validated['flitt_secret_key'] : ($flitt['secret_key'] ?? null),
        ];

        $provider = isset($validated['provider']) ? PaymentProvider::from($validated['provider']) : null;

        // The provider taking payments needs all of its details
        if ($provider === PaymentProvider::Bog && ! BogCredentialsData::from($bog)->isComplete()) {
            throw ValidationException::withMessages(['bog_client_secret' => 'Enter the BOG client ID and client secret.']);
        }
        if ($provider === PaymentProvider::Flitt && ! FlittCredentialsData::from($flitt)->isComplete()) {
            throw ValidationException::withMessages(['flitt_secret_key' => 'Enter the Flitt merchant ID and secret key.']);
        }

        $settings->fill([
            'provider' => $provider,
            'bog_settings' => $bog,
            'flitt_settings' => $flitt,
        ])->save();

        PaymentSettingsService::clearCache();

        return response()->json($this->present($settings));
    }

    /** Pay in advance: texting clients a payment link some hours before their session. */
    public function updateAdvancePayment(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'hours_before' => ['required', 'integer', 'min:1', 'max:336'],
            'message' => ['nullable', 'required_if:enabled,true', 'string', 'max:600'],
        ]);

        if (filled($validated['message'] ?? null) && ! str_contains($validated['message'], '{link}')) {
            throw ValidationException::withMessages(['message' => 'The message must include {link}, where the payment link goes.']);
        }

        if ($validated['enabled'] && ! PaymentSettingsService::isConfigured()) {
            throw ValidationException::withMessages(['enabled' => 'Set up a payment provider first.']);
        }

        $settings = PaymentSettings::firstOrNew();
        $settings->fill([
            'advance_payment_enabled' => $validated['enabled'],
            'advance_payment_hours_before' => $validated['hours_before'],
            'advance_payment_message' => $validated['message'] ?? null,
        ])->save();

        PaymentSettingsService::clearCache();

        return response()->json($this->present($settings));
    }

    /** @return array<string, mixed> */
    private function present(?PaymentSettings $settings): array
    {
        $bog = BogCredentialsData::from($settings?->bog_settings);
        $flitt = FlittCredentialsData::from($settings?->flitt_settings);

        return [
            'provider' => $settings?->provider?->value,
            'is_configured' => match ($settings?->provider) {
                PaymentProvider::Bog => $bog->isComplete(),
                PaymentProvider::Flitt => $flitt->isComplete(),
                null => false,
            },
            'bog' => [
                'client_id' => $bog->clientId ?: null,
                'has_client_secret' => $bog->clientSecret !== '',
                'callback_url' => route('payments.callbacks.bog'),
            ],
            'flitt' => [
                'merchant_id' => $flitt->merchantId ?: null,
                'has_secret_key' => $flitt->secretKey !== '',
                'callback_url' => route('payments.callbacks.flitt'),
            ],
            'advance_payment' => [
                'enabled' => (bool) $settings?->advance_payment_enabled,
                'hours_before' => $settings?->advance_payment_hours_before ?? 24,
                'message' => $settings?->advance_payment_message,
                'default_message' => RequestAdvancePaymentAction::DEFAULT_MESSAGE,
            ],
            // Links go out by SMS, so the clinic needs its InexPhone account set up too
            'sms_configured' => SmsSettingsService::getInexPhoneSettings() !== null,
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        $this->authorizeTenantAdmin($request, 'Only workspace admins can manage payment settings.');
    }
}
