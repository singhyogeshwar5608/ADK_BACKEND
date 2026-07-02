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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cloudinary' => [
        // Single line from Cloudinary Console → API Keys → "API environment variable" (avoids key/secret typos).
        'url' => trim((string) env('CLOUDINARY_URL', '')),
        'cloud_name' => trim((string) env('CLOUDINARY_CLOUD_NAME', '')),
        'api_key' => trim((string) env('CLOUDINARY_API_KEY', '')),
        'api_secret' => trim((string) env('CLOUDINARY_API_SECRET', '')),
        'upload_preset' => trim((string) env('CLOUDINARY_UPLOAD_PRESET', '')),
        'folder' => trim((string) env('CLOUDINARY_FOLDER', '')),
        // Optional; only sent if non-empty. Preset must allow this folder (Flutter does not send folder by default).
        'category_logo_folder' => trim((string) env('CLOUDINARY_CATEGORY_LOGO_FOLDER', '')),
    ],

    'shiprocket' => [
        'email' => env('SHIPROCKET_EMAIL'),
        'password' => env('SHIPROCKET_PASSWORD'),
        'base_url' => env('SHIPROCKET_BASE_URL', 'https://apiv2.shiprocket.in/v1/external'),
        'webhook_key' => env('SHIPROCKET_WEBHOOK_KEY', 'ASLI_DESI_KISAN_WEBHOOK_SECRET_2026'),
    ],

];
