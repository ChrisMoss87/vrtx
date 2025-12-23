<?php

declare(strict_types=1);

namespace App\Domain\Email\ValueObjects;

enum OAuthProvider: string
{
    case GMAIL = 'gmail';
    case MICROSOFT = 'microsoft';

    public function label(): string
    {
        return match ($this) {
            self::GMAIL => 'Gmail',
            self::MICROSOFT => 'Microsoft Outlook',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GMAIL => 'gmail',
            self::MICROSOFT => 'microsoft',
        };
    }

    public function getAuthorizationUrl(): string
    {
        return match ($this) {
            self::GMAIL => 'https://accounts.google.com/o/oauth2/v2/auth',
            self::MICROSOFT => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
        };
    }

    public function getTokenUrl(): string
    {
        return match ($this) {
            self::GMAIL => 'https://oauth2.googleapis.com/token',
            self::MICROSOFT => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        };
    }

    public function getUserInfoUrl(): string
    {
        return match ($this) {
            self::GMAIL => 'https://www.googleapis.com/oauth2/v2/userinfo',
            self::MICROSOFT => 'https://graph.microsoft.com/v1.0/me',
        };
    }

    public function getScopes(): array
    {
        return match ($this) {
            self::GMAIL => [
                'https://mail.google.com/',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
            ],
            self::MICROSOFT => [
                'https://outlook.office.com/IMAP.AccessAsUser.All',
                'https://outlook.office.com/SMTP.Send',
                'offline_access',
                'openid',
                'profile',
                'email',
            ],
        };
    }

    public function getScopeString(): string
    {
        return implode(' ', $this->getScopes());
    }

    public function getImapHost(): string
    {
        return match ($this) {
            self::GMAIL => 'imap.gmail.com',
            self::MICROSOFT => 'outlook.office365.com',
        };
    }

    public function getSmtpHost(): string
    {
        return match ($this) {
            self::GMAIL => 'smtp.gmail.com',
            self::MICROSOFT => 'smtp.office365.com',
        };
    }

    public function getImapPort(): int
    {
        return 993;
    }

    public function getSmtpPort(): int
    {
        return 587;
    }

    public function supportsOAuth(): bool
    {
        return true;
    }
}
