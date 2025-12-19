<?php

declare(strict_types=1);

namespace App\Domain\Approval\ValueObjects;

enum EntityType: string
{
    case QUOTE = 'quote';
    case PROPOSAL = 'proposal';
    case DISCOUNT = 'discount';
    case CONTRACT = 'contract';
    case EXPENSE = 'expense';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::QUOTE => 'Quote',
            self::PROPOSAL => 'Proposal',
            self::DISCOUNT => 'Discount',
            self::CONTRACT => 'Contract',
            self::EXPENSE => 'Expense',
            self::CUSTOM => 'Custom',
        };
    }
}
