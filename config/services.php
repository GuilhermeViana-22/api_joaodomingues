<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Tradução automática dos imóveis (app/Services/TradutorService.php).
    | driver: deepl | google | nenhum. Com DEEPL_API_KEY e sem driver, usa a DeepL.
    */
    'traducao' => [
        'driver' => env('TRADUCAO_DRIVER') ?: (env('DEEPL_API_KEY') ? 'deepl' : 'google'),
        'deepl_key' => (string) env('DEEPL_API_KEY', ''),
        'timeout' => (int) env('TRADUCAO_TIMEOUT', 8),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
