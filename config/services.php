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

    'backend' => [
        'url' => env('BACKEND_API_BASE_URL', 'https://api.saborcentral.com'),
    ],

    'izipay' => [
        'test_mode' => (bool) env('IZIPAY_TEST_MODE', false),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'gcs' => [
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'bucket' => env('GCS_BUCKET_NAME', 'almacendelicias'),
        'key_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'upload_prefix' => env('GCS_UPLOAD_PREFIX', 'uploads'),
        'signed_url_ttl' => (int) env('GCS_SIGNED_URL_TTL', 60),
    ],

    'tickets' => [
        'token' => env('TICKETS_API_TOKEN'),
    ],

];
