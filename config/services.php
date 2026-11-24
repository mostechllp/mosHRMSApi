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

    /*
    |--------------------------------------------------------------------------
    | CRM Lead Ingestion & Social Media Integrations
    | (Facebook Lead Ads, Instagram Lead Ads, LinkedIn, WhatsApp)
    |--------------------------------------------------------------------------
    */
    'crm_leads' => [
        'webhook_secret' => env('CRM_LEAD_WEBHOOK_SECRET', 'crm_secret_lead_webhook_key'),
        'meta' => [
            'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', 'crm_leads_mostech_2026'),
            'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
            'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
        ],
        'default_salesperson_id' => env('CRM_DEFAULT_SALESPERSON_ID'),
    ],

];
