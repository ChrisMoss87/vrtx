<?php

declare(strict_types=1);

namespace App\Domain\Integration\ValueObjects;

enum AuthType: string
{
    case OAUTH2 = 'oauth2';
    case API_KEY = 'api_key';
    case BASIC_AUTH = 'basic_auth';
    case WEBHOOK_ONLY = 'webhook_only';

    public function label(): string
    {
        return match ($this) {
            self::OAUTH2 => 'OAuth 2.0',
            self::API_KEY => 'API Key',
            self::BASIC_AUTH => 'Basic Authentication',
            self::WEBHOOK_ONLY => 'Webhook Only',
        };
    }

    public function requiresUserAuth(): bool
    {
        return match ($this) {
            self::OAUTH2 => true,
            self::API_KEY, self::BASIC_AUTH, self::WEBHOOK_ONLY => false,
        };
    }
}
