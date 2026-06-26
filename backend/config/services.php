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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'jwt' => [
        'secret' => env('JWT_SECRET'),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'https://delicias.saborcentral.com'),
    ],

    'password_reset' => [
        'ttl_minutes' => (int) env('PASSWORD_RESET_TTL_MINUTES', 30),
    ],

    'product_notifications' => [
        'email_enabled' => filter_var(
            env('PRODUCT_EMAIL_NOTIFICATIONS_ENABLED', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    'customer_lifecycle' => [
        'enabled' => filter_var(env('CUSTOMER_LIFECYCLE_EMAILS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'welcome_enabled' => filter_var(env('WELCOME_EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'welcome_offer' => env('WELCOME_OFFER_TEXT', ''),
        'welcome_retry_days' => (int) env('WELCOME_EMAIL_RETRY_DAYS', 7),
        'dormant_enabled' => filter_var(env('DORMANT_EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'dormant_days' => (int) env('DORMANT_CUSTOMER_DAYS', 30),
        'dormant_offer' => env('DORMANT_OFFER_TEXT', ''),
        'review_enabled' => filter_var(env('REVIEW_EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'review_delay_days' => (int) env('REVIEW_EMAIL_DELAY_DAYS', 1),
    ],

    'izipay' => [
        'enabled' => filter_var(env('IZIPAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'mode' => env('IZIPAY_MODE', 'test'),
        'api_base_url' => rtrim((string) env('IZIPAY_API_BASE_URL', env('IZIPAY_API_REST_URL', 'https://api.micuentaweb.pe')), '/'),
        'api_rest_url' => rtrim((string) env('IZIPAY_API_REST_URL', env('IZIPAY_API_BASE_URL', 'https://api.micuentaweb.pe')), '/'),
        'static_base_url' => rtrim((string) env('IZIPAY_STATIC_BASE_URL', 'https://static.micuentaweb.pe'), '/'),
        'js_url' => env('IZIPAY_JS_URL', 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js'),
        'username' => env('IZIPAY_USERNAME', env('IZIPAY_API_USER', '')),
        'api_user' => env('IZIPAY_API_USER', env('IZIPAY_USERNAME', '')),
        'password' => env('IZIPAY_PASSWORD', ''),
        'api_password_test' => env('IZIPAY_API_PASSWORD_TEST', env('IZIPAY_PASSWORD', '')),
        'api_password_production' => env('IZIPAY_API_PASSWORD_PRODUCTION', env('IZIPAY_PASSWORD', '')),
        'public_key' => env('IZIPAY_PUBLIC_KEY', ''),
        'public_key_test' => env('IZIPAY_PUBLIC_KEY_TEST', env('IZIPAY_PUBLIC_KEY', '')),
        'public_key_production' => env('IZIPAY_PUBLIC_KEY_PRODUCTION', env('IZIPAY_PUBLIC_KEY', '')),
        'hmac_key_test' => env('IZIPAY_HMAC_KEY_TEST', ''),
        'hmac_key_production' => env('IZIPAY_HMAC_KEY_PRODUCTION', ''),
        'currency' => env('IZIPAY_CURRENCY', 'PEN'),
        'return_url' => env('IZIPAY_RETURN_URL', env('APP_URL').'/api/pagos/izipay/retorno'),
        'cancel_url' => env('IZIPAY_CANCEL_URL', env('APP_URL').'/api/pagos/izipay/cancelado'),
    ],

    'documents' => [
        'provider' => env('DOCUMENT_PROVIDER', 'apiperu'),
        'validation_required' => filter_var(
            env('DOCUMENT_VALIDATION_REQUIRED', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'apiperu' => [
            'token' => env('APIPERU_TOKEN', env('APIPERU_API_TOKEN')),
            'base_url' => env('APIPERU_BASE_URL', 'https://dniruc.apisperu.com/api/v1'),
        ],
        'decolecta' => [
            'token' => env('DECOLECTA_TOKEN', env('DECOLECTA_API_TOKEN')),
            'base_url' => env('DECOLECTA_BASE_URL', 'https://api.decolecta.com/v1'),
            'reniec_token' => env('RENIEC_API_TOKEN', env('RENIEC_TOKEN')),
            'sunat_token' => env('SUNAT_API_TOKEN', env('SUNAT_TOKEN')),
        ],
        'ca_bundle' => env('CURL_CA_BUNDLE'),
    ],

];
