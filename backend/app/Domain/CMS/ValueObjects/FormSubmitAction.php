<?php

declare(strict_types=1);

namespace App\Domain\CMS\ValueObjects;

enum FormSubmitAction: string
{
    case CREATE_LEAD = 'create_lead';
    case CREATE_CONTACT = 'create_contact';
    case UPDATE_CONTACT = 'update_contact';
    case WEBHOOK = 'webhook';
    case EMAIL = 'email';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::CREATE_LEAD => 'Create Lead',
            self::CREATE_CONTACT => 'Create Contact',
            self::UPDATE_CONTACT => 'Update Contact',
            self::WEBHOOK => 'Send to Webhook',
            self::EMAIL => 'Send Email',
            self::CUSTOM => 'Custom Action',
        };
    }

    public function requiresModule(): bool
    {
        return in_array($this, [self::CREATE_LEAD, self::CREATE_CONTACT, self::UPDATE_CONTACT]);
    }
}
