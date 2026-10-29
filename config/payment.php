<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active Payment Provider
    |--------------------------------------------------------------------------
    | Supported: "bog", "flitt"
    */
    'provider' => env('PAYMENT_PROVIDER', 'bog'),

    /*
    |--------------------------------------------------------------------------
    | Redirect URL after payment
    |--------------------------------------------------------------------------
    | A full URL to send clients to after paying. Leave empty to use Elio's
    | own payment result page.
    */
    'redirect_url' => env('PAYMENT_REDIRECT_URL'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    */
    'bog' => [
        'client_id' => env('BOG_CLIENT_ID'),
        'client_secret' => env('BOG_CLIENT_SECRET'),
        'api_url' => env('BOG_API_URL', 'https://api.bog.ge'),
        'oauth_url' => env('BOG_OAUTH_URL', 'https://oauth2.bog.ge'),
        'currency' => env('BOG_CURRENCY', 'GEL'),
    ],

    'flitt' => [
        'merchant_id' => env('FLITT_MERCHANT_ID'),
        'secret_key' => env('FLITT_SECRET_KEY'),
        'api_url' => env('FLITT_API_URL', 'https://api.fondy.eu'),
        'currency' => env('FLITT_CURRENCY', 'GEL'),
    ],
];
