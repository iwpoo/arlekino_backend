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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'service_sid' => env('TWILIO_SERVICE_SID'),
        'verification_send_attempts_limit' => env('TWILIO_SEND_ATTEMPTS_LIMIT', 1),
        'verification_verify_attempts_limit' => env('TWILIO_VERIFY_ATTEMPTS_LIMIT', 5),
        'verification_verify_timeout_seconds' => env('TWILIO_VERIFY_TIMEOUT_SECONDS', 300),
    ],

    'elasticsearch' => [
        'host' => env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
        'index' => env('ELASTICSEARCH_INDEX', 'search_index'),
    ],

    'auth' => [
        'login_attempts_limit' => env('AUTH_LOGIN_ATTEMPTS_LIMIT', 5),
        'login_timeout_seconds' => env('AUTH_LOGIN_TIMEOUT_SECONDS', 600), // 10 minutes
        'phone_verification_attempts_limit' => env('AUTH_PHONE_VERIFICATION_ATTEMPTS_LIMIT', 3),
        'phone_verification_timeout_seconds' => env('AUTH_PHONE_VERIFICATION_TIMEOUT_SECONDS', 3600), // 1 hour
    ],

    'order' => [
        'delivery_cost' => env('DELIVERY_COST', 5.0),
    ],

    'price_calculator' => [
        'delivery_cost' => env('DELIVERY_COST', 5.0),
    ],

    'returns_processing' => [
        'logistics_free_reasons' => env('RETURNS_LOGISTICS_FREE_REASONS', 'does_not_match_description,defective_damaged'),
        'logistics_cost' => env('RETURNS_LOGISTICS_COST', 150.0),
        'return_period_days' => env('RETURNS_RETURN_PERIOD_DAYS', 14),
        'qr_code_expiry_hours' => env('RETURNS_QR_CODE_EXPIRY_HOURS', 24),
        'qr_code_length' => env('RETURNS_QR_CODE_LENGTH', 16),
    ],

];
