<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

enum BlockType: string
{
    case SECTION = 'section';
    case TAB = 'tab';
    case ACCORDION = 'accordion';
    case CARD = 'card';

    public function label(): string
    {
        return match ($this) {
            self::SECTION => 'Section',
            self::TAB => 'Tab',
            self::ACCORDION => 'Accordion',
            self::CARD => 'Card',
        };
    }
}
