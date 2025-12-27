<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\ValueObjects;

enum DocumentType: string
{
    case WORD = 'word';
    case SPREADSHEET = 'spreadsheet';
    case PRESENTATION = 'presentation';

    public function label(): string
    {
        return match ($this) {
            self::WORD => 'Document',
            self::SPREADSHEET => 'Spreadsheet',
            self::PRESENTATION => 'Presentation',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::WORD => 'file-text',
            self::SPREADSHEET => 'table',
            self::PRESENTATION => 'presentation',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::WORD => 'application/vnd.vrtx.document',
            self::SPREADSHEET => 'application/vnd.vrtx.spreadsheet',
            self::PRESENTATION => 'application/vnd.vrtx.presentation',
        };
    }

    public function fileExtension(): string
    {
        return match ($this) {
            self::WORD => 'vdoc',
            self::SPREADSHEET => 'vsheet',
            self::PRESENTATION => 'vpres',
        };
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }
}
