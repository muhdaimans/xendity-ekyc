<?php

declare(strict_types=1);

use XendityEkyc\XendityEkyc\Models\EkycTransaction;

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway Endpoint
    |--------------------------------------------------------------------------
    |
    | Base URL of the Xendity eKYC gateway. The UAT endpoint is used by
    | default; set the production URL provided by Xendity when going live.
    |
    */

    'base_url' => env('XENDITY_EKYC_BASE_URL', 'https://staging.ekyc.xendity.com/v1/gateway/'),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Provided by Xendity. The security key signs every request and the
    | cipher key (16 characters) is the AES-256-CBC initialization vector.
    |
    */

    'api_key' => env('XENDITY_EKYC_API_KEY'),

    'package_name' => env('XENDITY_EKYC_PACKAGE_NAME'),

    'security_key' => env('XENDITY_EKYC_SECURITY_KEY'),

    'cipher_key' => env('XENDITY_EKYC_CIPHER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Transaction Defaults
    |--------------------------------------------------------------------------
    |
    | Defaults applied to create-transaction requests. Callback mode:
    | 0 = none, 1 = summary (recommended), 2 = detail. Response mode:
    | 0 = no queries, 1 = append refId, status and result to response_url.
    |
    */

    'platform' => env('XENDITY_EKYC_PLATFORM', 'Web'),

    'callback_mode' => 1,

    'response_mode' => 1,

    'response_url' => env('XENDITY_EKYC_RESPONSE_URL'),

    'backend_url' => env('XENDITY_EKYC_BACKEND_URL'),

    'timezone' => env('XENDITY_EKYC_TIMEZONE', 'Asia/Kuala_Lumpur'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => 30,
        'retry_times' => 2,
        'retry_sleep' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | The route Xendity posts transaction updates to. When enabled and no
    | backend_url is configured, this route is sent as the backend_url.
    | Xendity only calls back on port 80 or 443.
    |
    */

    'webhook' => [
        'enabled' => true,
        'path' => 'xendity-ekyc/webhook',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    */

    'table' => 'xendity_ekyc_transactions',

    'model' => EkycTransaction::class,

];
