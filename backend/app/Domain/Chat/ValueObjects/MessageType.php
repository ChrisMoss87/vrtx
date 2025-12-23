<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

enum MessageType: string
{
    case TEXT = 'text';
    case HTML = 'html';
    case IMAGE = 'image';
    case FILE = 'file';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::HTML => 'HTML',
            self::IMAGE => 'Image',
            self::FILE => 'File',
        };
    }

    public function isAttachment(): bool
    {
        return $this === self::IMAGE || $this === self::FILE;
    }

    public function requiresValidation(): bool
    {
        return $this === self::IMAGE || $this === self::FILE;
    }
}
