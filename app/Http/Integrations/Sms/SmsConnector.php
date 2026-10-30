<?php

namespace App\Http\Integrations\Sms;

use App\Services\Sms\SmsSettingsService;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class SmsConnector extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        $settings = SmsSettingsService::getInexPhoneSettings();
        assert($settings !== null);

        return $settings->apiUrl;
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        $settings = SmsSettingsService::getInexPhoneSettings();
        assert($settings !== null);

        return new TokenAuthenticator($settings->apiKey);
    }
}
