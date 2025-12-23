<?php

declare(strict_types=1);

namespace App\Domain\Communication\ValueObjects;

enum ChannelType: string
{
    case EMAIL = 'email';
    case CHAT = 'chat';
    case WHATSAPP = 'whatsapp';
    case SMS = 'sms';
    case CALL = 'call';
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::CHAT => 'Live Chat',
            self::WHATSAPP => 'WhatsApp',
            self::SMS => 'SMS',
            self::CALL => 'Phone Call',
            self::VIDEO => 'Video Call',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EMAIL => 'mail',
            self::CHAT => 'message-circle',
            self::WHATSAPP => 'whatsapp',
            self::SMS => 'smartphone',
            self::CALL => 'phone',
            self::VIDEO => 'video',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EMAIL => 'blue',
            self::CHAT => 'purple',
            self::WHATSAPP => 'green',
            self::SMS => 'orange',
            self::CALL => 'cyan',
            self::VIDEO => 'indigo',
        };
    }

    public function isMessaging(): bool
    {
        return in_array($this, [self::EMAIL, self::CHAT, self::WHATSAPP, self::SMS]);
    }

    public function isRealtime(): bool
    {
        return in_array($this, [self::CHAT, self::CALL, self::VIDEO]);
    }

    public function supportsThreading(): bool
    {
        return in_array($this, [self::EMAIL, self::WHATSAPP, self::CHAT]);
    }

    public function supportsAttachments(): bool
    {
        return in_array($this, [self::EMAIL, self::WHATSAPP, self::CHAT, self::SMS]);
    }

    public function supportsTemplates(): bool
    {
        return in_array($this, [self::EMAIL, self::WHATSAPP, self::SMS]);
    }

    public function hasRecording(): bool
    {
        return in_array($this, [self::CALL, self::VIDEO]);
    }
}
