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
    | Google OAuth (Gmail, Drive, Calendar, Meet Integration)
    |--------------------------------------------------------------------------
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft OAuth (Outlook/Office 365, OneDrive, Teams Integration)
    |--------------------------------------------------------------------------
    */
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        'redirect_uri' => env('MICROSOFT_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | QuickBooks Online Integration
    |--------------------------------------------------------------------------
    */
    'quickbooks' => [
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
        'sandbox' => env('QUICKBOOKS_SANDBOX', true),
        'webhook_verifier_token' => env('QUICKBOOKS_WEBHOOK_VERIFIER_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Xero Integration
    |--------------------------------------------------------------------------
    */
    'xero' => [
        'client_id' => env('XERO_CLIENT_ID'),
        'client_secret' => env('XERO_CLIENT_SECRET'),
        'redirect_uri' => env('XERO_REDIRECT_URI'),
        'webhook_key' => env('XERO_WEBHOOK_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Integration
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'prices' => [
            'starter_monthly' => env('STRIPE_PRICE_STARTER_MONTHLY'),
            'starter_yearly' => env('STRIPE_PRICE_STARTER_YEARLY'),
            'professional_monthly' => env('STRIPE_PRICE_PROFESSIONAL_MONTHLY'),
            'professional_yearly' => env('STRIPE_PRICE_PROFESSIONAL_YEARLY'),
            'business_monthly' => env('STRIPE_PRICE_BUSINESS_MONTHLY'),
            'business_yearly' => env('STRIPE_PRICE_BUSINESS_YEARLY'),
            'enterprise_monthly' => env('STRIPE_PRICE_ENTERPRISE_MONTHLY'),
            'enterprise_yearly' => env('STRIPE_PRICE_ENTERPRISE_YEARLY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal Integration
    |--------------------------------------------------------------------------
    */
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'sandbox' => env('PAYPAL_SANDBOX', true),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DocuSign Integration
    |--------------------------------------------------------------------------
    */
    'docusign' => [
        'integration_key' => env('DOCUSIGN_INTEGRATION_KEY'),
        'secret_key' => env('DOCUSIGN_SECRET_KEY'),
        'redirect_uri' => env('DOCUSIGN_REDIRECT_URI'),
        'sandbox' => env('DOCUSIGN_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | PandaDoc Integration
    |--------------------------------------------------------------------------
    */
    'pandadoc' => [
        'api_key' => env('PANDADOC_API_KEY'),
        'client_id' => env('PANDADOC_CLIENT_ID'),
        'client_secret' => env('PANDADOC_CLIENT_SECRET'),
        'redirect_uri' => env('PANDADOC_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Integration
    |--------------------------------------------------------------------------
    */
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        'redirect_uri' => env('SLACK_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zoom Integration
    |--------------------------------------------------------------------------
    */
    'zoom' => [
        'client_id' => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
        'redirect_uri' => env('ZOOM_REDIRECT_URI'),
        'webhook_secret_token' => env('ZOOM_WEBHOOK_SECRET_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendly Integration
    |--------------------------------------------------------------------------
    */
    'calendly' => [
        'client_id' => env('CALENDLY_CLIENT_ID'),
        'client_secret' => env('CALENDLY_CLIENT_SECRET'),
        'redirect_uri' => env('CALENDLY_REDIRECT_URI'),
        'webhook_signing_key' => env('CALENDLY_WEBHOOK_SIGNING_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dropbox Integration
    |--------------------------------------------------------------------------
    */
    'dropbox' => [
        'app_key' => env('DROPBOX_APP_KEY'),
        'app_secret' => env('DROPBOX_APP_SECRET'),
        'redirect_uri' => env('DROPBOX_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailchimp Integration
    |--------------------------------------------------------------------------
    */
    'mailchimp' => [
        'client_id' => env('MAILCHIMP_CLIENT_ID'),
        'client_secret' => env('MAILCHIMP_CLIENT_SECRET'),
        'redirect_uri' => env('MAILCHIMP_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HubSpot Integration
    |--------------------------------------------------------------------------
    */
    'hubspot' => [
        'client_id' => env('HUBSPOT_CLIENT_ID'),
        'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
        'redirect_uri' => env('HUBSPOT_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Salesforce Integration
    |--------------------------------------------------------------------------
    */
    'salesforce' => [
        'client_id' => env('SALESFORCE_CLIENT_ID'),
        'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
        'redirect_uri' => env('SALESFORCE_REDIRECT_URI'),
    ],

];
