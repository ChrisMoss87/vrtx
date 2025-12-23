<?php

declare(strict_types=1);

namespace App\Domain\Integration\ValueObjects;

enum IntegrationProvider: string
{
    // Accounting
    case QUICKBOOKS = 'quickbooks';
    case XERO = 'xero';

    // Payments
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';

    // E-Signature
    case DOCUSIGN = 'docusign';
    case PANDADOC = 'pandadoc';
    case ADOBE_SIGN = 'adobe_sign';
    case HELLOSIGN = 'hellosign';
    case XODO_SIGN = 'xodo_sign';

    // Communication
    case SLACK = 'slack';
    case ZOOM = 'zoom';
    case TEAMS = 'teams';

    // Calendar
    case GOOGLE_CALENDAR = 'google_calendar';
    case MICROSOFT_CALENDAR = 'microsoft_calendar';
    case CALENDLY = 'calendly';

    // Marketing
    case MAILCHIMP = 'mailchimp';
    case ACTIVECAMPAIGN = 'activecampaign';

    // Telephony
    case TWILIO = 'twilio';

    // Storage
    case GOOGLE_DRIVE = 'google_drive';
    case DROPBOX = 'dropbox';
    case ONEDRIVE = 'onedrive';

    public function label(): string
    {
        return match ($this) {
            self::QUICKBOOKS => 'QuickBooks Online',
            self::XERO => 'Xero',
            self::STRIPE => 'Stripe',
            self::PAYPAL => 'PayPal',
            self::DOCUSIGN => 'DocuSign',
            self::PANDADOC => 'PandaDoc',
            self::ADOBE_SIGN => 'Adobe Sign',
            self::HELLOSIGN => 'Dropbox Sign',
            self::XODO_SIGN => 'Xodo Sign',
            self::SLACK => 'Slack',
            self::ZOOM => 'Zoom',
            self::TEAMS => 'Microsoft Teams',
            self::GOOGLE_CALENDAR => 'Google Calendar',
            self::MICROSOFT_CALENDAR => 'Microsoft Calendar',
            self::CALENDLY => 'Calendly',
            self::MAILCHIMP => 'Mailchimp',
            self::ACTIVECAMPAIGN => 'ActiveCampaign',
            self::TWILIO => 'Twilio',
            self::GOOGLE_DRIVE => 'Google Drive',
            self::DROPBOX => 'Dropbox',
            self::ONEDRIVE => 'OneDrive',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::QUICKBOOKS => 'calculator',
            self::XERO => 'file-spreadsheet',
            self::STRIPE => 'credit-card',
            self::PAYPAL => 'wallet',
            self::DOCUSIGN => 'file-signature',
            self::PANDADOC => 'file-text',
            self::ADOBE_SIGN => 'pen-tool',
            self::HELLOSIGN => 'edit-3',
            self::XODO_SIGN => 'edit',
            self::SLACK => 'hash',
            self::ZOOM => 'video',
            self::TEAMS => 'users',
            self::GOOGLE_CALENDAR => 'calendar',
            self::MICROSOFT_CALENDAR => 'calendar-days',
            self::CALENDLY => 'calendar-check',
            self::MAILCHIMP => 'mail',
            self::ACTIVECAMPAIGN => 'send',
            self::TWILIO => 'phone',
            self::GOOGLE_DRIVE => 'hard-drive',
            self::DROPBOX => 'box',
            self::ONEDRIVE => 'cloud',
        };
    }

    public function category(): IntegrationCategory
    {
        return match ($this) {
            self::QUICKBOOKS, self::XERO => IntegrationCategory::ACCOUNTING,
            self::STRIPE, self::PAYPAL => IntegrationCategory::PAYMENTS,
            self::DOCUSIGN, self::PANDADOC, self::ADOBE_SIGN, self::HELLOSIGN, self::XODO_SIGN => IntegrationCategory::ESIGNATURE,
            self::SLACK, self::ZOOM, self::TEAMS => IntegrationCategory::COMMUNICATION,
            self::GOOGLE_CALENDAR, self::MICROSOFT_CALENDAR, self::CALENDLY => IntegrationCategory::CALENDAR,
            self::MAILCHIMP, self::ACTIVECAMPAIGN => IntegrationCategory::MARKETING,
            self::TWILIO => IntegrationCategory::TELEPHONY,
            self::GOOGLE_DRIVE, self::DROPBOX, self::ONEDRIVE => IntegrationCategory::STORAGE,
        };
    }

    public function authType(): AuthType
    {
        return match ($this) {
            self::QUICKBOOKS, self::XERO, self::DOCUSIGN, self::PANDADOC, self::ADOBE_SIGN,
            self::HELLOSIGN, self::SLACK, self::ZOOM, self::GOOGLE_CALENDAR,
            self::MICROSOFT_CALENDAR, self::CALENDLY, self::MAILCHIMP,
            self::GOOGLE_DRIVE, self::DROPBOX, self::ONEDRIVE => AuthType::OAUTH2,
            self::STRIPE, self::PAYPAL, self::TWILIO, self::ACTIVECAMPAIGN, self::XODO_SIGN, self::TEAMS => AuthType::API_KEY,
        };
    }

    public function getAuthorizationUrl(): ?string
    {
        return match ($this) {
            self::QUICKBOOKS => 'https://appcenter.intuit.com/connect/oauth2',
            self::XERO => 'https://login.xero.com/identity/connect/authorize',
            self::DOCUSIGN => 'https://account.docusign.com/oauth/auth',
            self::PANDADOC => 'https://app.pandadoc.com/oauth2/authorize',
            self::SLACK => 'https://slack.com/oauth/v2/authorize',
            self::ZOOM => 'https://zoom.us/oauth/authorize',
            self::GOOGLE_CALENDAR => 'https://accounts.google.com/o/oauth2/v2/auth',
            self::MICROSOFT_CALENDAR => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            self::CALENDLY => 'https://auth.calendly.com/oauth/authorize',
            self::MAILCHIMP => 'https://login.mailchimp.com/oauth2/authorize',
            self::GOOGLE_DRIVE => 'https://accounts.google.com/o/oauth2/v2/auth',
            self::DROPBOX => 'https://www.dropbox.com/oauth2/authorize',
            self::ONEDRIVE => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            self::ADOBE_SIGN => 'https://secure.echosign.com/public/oauth/v2',
            self::HELLOSIGN => 'https://app.hellosign.com/oauth/authorize',
            default => null,
        };
    }

    public function getTokenUrl(): ?string
    {
        return match ($this) {
            self::QUICKBOOKS => 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer',
            self::XERO => 'https://identity.xero.com/connect/token',
            self::DOCUSIGN => 'https://account.docusign.com/oauth/token',
            self::PANDADOC => 'https://api.pandadoc.com/oauth2/access_token',
            self::SLACK => 'https://slack.com/api/oauth.v2.access',
            self::ZOOM => 'https://zoom.us/oauth/token',
            self::GOOGLE_CALENDAR, self::GOOGLE_DRIVE => 'https://oauth2.googleapis.com/token',
            self::MICROSOFT_CALENDAR, self::ONEDRIVE => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            self::CALENDLY => 'https://auth.calendly.com/oauth/token',
            self::MAILCHIMP => 'https://login.mailchimp.com/oauth2/token',
            self::DROPBOX => 'https://api.dropboxapi.com/oauth2/token',
            self::ADOBE_SIGN => 'https://secure.echosign.com/oauth/v2/token',
            self::HELLOSIGN => 'https://app.hellosign.com/oauth/token',
            default => null,
        };
    }

    public function getBaseUrl(): string
    {
        return match ($this) {
            self::QUICKBOOKS => 'https://quickbooks.api.intuit.com/v3',
            self::XERO => 'https://api.xero.com/api.xro/2.0',
            self::STRIPE => 'https://api.stripe.com/v1',
            self::PAYPAL => 'https://api-m.paypal.com/v2',
            self::DOCUSIGN => 'https://www.docusign.net/restapi/v2.1',
            self::PANDADOC => 'https://api.pandadoc.com/public/v1',
            self::SLACK => 'https://slack.com/api',
            self::ZOOM => 'https://api.zoom.us/v2',
            self::GOOGLE_CALENDAR => 'https://www.googleapis.com/calendar/v3',
            self::MICROSOFT_CALENDAR => 'https://graph.microsoft.com/v1.0',
            self::CALENDLY => 'https://api.calendly.com/v2',
            self::MAILCHIMP => 'https://api.mailchimp.com/3.0',
            self::ACTIVECAMPAIGN => '', // Requires datacenter prefix
            self::TWILIO => 'https://api.twilio.com/2010-04-01',
            self::GOOGLE_DRIVE => 'https://www.googleapis.com/drive/v3',
            self::DROPBOX => 'https://api.dropboxapi.com/2',
            self::ONEDRIVE => 'https://graph.microsoft.com/v1.0',
            self::ADOBE_SIGN => 'https://api.echosign.com/api/rest/v6',
            self::HELLOSIGN => 'https://api.hellosign.com/v3',
            self::XODO_SIGN => 'https://api.xodo.com/v1',
            self::TEAMS => 'https://graph.microsoft.com/v1.0',
        };
    }

    public function getScopes(): array
    {
        return match ($this) {
            self::QUICKBOOKS => ['com.intuit.quickbooks.accounting'],
            self::XERO => ['openid', 'profile', 'email', 'accounting.contacts', 'accounting.transactions', 'offline_access'],
            self::DOCUSIGN => ['signature', 'extended'],
            self::PANDADOC => ['read+write'],
            self::SLACK => ['chat:write', 'channels:read', 'incoming-webhook', 'users:read'],
            self::ZOOM => ['meeting:write', 'meeting:read', 'user:read'],
            self::GOOGLE_CALENDAR => [
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/calendar.events',
            ],
            self::MICROSOFT_CALENDAR => ['Calendars.ReadWrite', 'User.Read', 'offline_access'],
            self::CALENDLY => ['default'],
            self::MAILCHIMP => [],
            self::GOOGLE_DRIVE => [
                'https://www.googleapis.com/auth/drive.file',
                'https://www.googleapis.com/auth/drive.readonly',
            ],
            self::DROPBOX => ['files.content.read', 'files.content.write'],
            self::ONEDRIVE => ['Files.ReadWrite', 'User.Read', 'offline_access'],
            self::ADOBE_SIGN => ['agreement_read', 'agreement_write', 'agreement_send'],
            self::HELLOSIGN => ['signature_request:read', 'signature_request:write'],
            default => [],
        };
    }

    public function getScopeString(): string
    {
        return implode(' ', $this->getScopes());
    }

    public function getTokenExpirySeconds(): int
    {
        return match ($this) {
            self::QUICKBOOKS => 3600, // 1 hour
            self::XERO => 1800, // 30 minutes
            self::GOOGLE_CALENDAR, self::GOOGLE_DRIVE => 3600, // 1 hour
            self::MICROSOFT_CALENDAR, self::ONEDRIVE => 3600, // 1 hour
            self::ZOOM => 3600, // 1 hour
            self::DOCUSIGN => 3600, // 1 hour
            default => 3600,
        };
    }

    public function getRefreshTokenExpiryDays(): int
    {
        return match ($this) {
            self::QUICKBOOKS => 100,
            self::XERO => 60,
            self::GOOGLE_CALENDAR, self::GOOGLE_DRIVE => 180,
            self::MICROSOFT_CALENDAR, self::ONEDRIVE => 90,
            default => 90,
        };
    }

    public function supportsRefreshToken(): bool
    {
        return $this->authType() === AuthType::OAUTH2;
    }

    public function hasRotatingRefreshTokens(): bool
    {
        return match ($this) {
            self::XERO => true,
            default => false,
        };
    }

    public function getConfigKey(): string
    {
        return 'services.' . $this->value;
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom($slug);
    }

    public static function getByCategory(IntegrationCategory $category): array
    {
        return array_filter(
            self::cases(),
            fn(self $provider) => $provider->category() === $category
        );
    }
}
