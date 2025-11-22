<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

enum FieldType: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER = 'number';
    case DECIMAL = 'decimal';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case URL = 'url';
    case SELECT = 'select';
    case MULTISELECT = 'multiselect';
    case RADIO = 'radio';
    case CHECKBOX = 'checkbox';
    case TOGGLE = 'toggle';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIME = 'time';
    case CURRENCY = 'currency';
    case PERCENT = 'percent';
    case LOOKUP = 'lookup'; // Relationship to another module
    case FORMULA = 'formula'; // Calculated field
    case FILE = 'file';
    case IMAGE = 'image';
    case RICH_TEXT = 'rich_text';

    public function requiresOptions(): bool
    {
        return in_array($this, [
            self::SELECT,
            self::MULTISELECT,
            self::RADIO,
        ], true);
    }

    public function isNumeric(): bool
    {
        return in_array($this, [
            self::NUMBER,
            self::DECIMAL,
            self::CURRENCY,
            self::PERCENT,
        ], true);
    }

    public function isRelationship(): bool
    {
        return $this === self::LOOKUP;
    }

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::TEXTAREA => 'Text Area',
            self::NUMBER => 'Number',
            self::DECIMAL => 'Decimal',
            self::EMAIL => 'Email',
            self::PHONE => 'Phone',
            self::URL => 'URL',
            self::SELECT => 'Select',
            self::MULTISELECT => 'Multi Select',
            self::RADIO => 'Radio',
            self::CHECKBOX => 'Checkbox',
            self::TOGGLE => 'Toggle',
            self::DATE => 'Date',
            self::DATETIME => 'Date Time',
            self::TIME => 'Time',
            self::CURRENCY => 'Currency',
            self::PERCENT => 'Percent',
            self::LOOKUP => 'Lookup',
            self::FORMULA => 'Formula',
            self::FILE => 'File',
            self::IMAGE => 'Image',
            self::RICH_TEXT => 'Rich Text',
        };
    }
}
