<?php

declare(strict_types=1);

namespace App\Domain\CMS\ValueObjects;

enum MediaType: string
{
    case IMAGE = 'image';
    case DOCUMENT = 'document';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case OTHER = 'other';

    public static function fromMimeType(string $mimeType): self
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => self::IMAGE,
            str_starts_with($mimeType, 'video/') => self::VIDEO,
            str_starts_with($mimeType, 'audio/') => self::AUDIO,
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
            ]) => self::DOCUMENT,
            default => self::OTHER,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::DOCUMENT => 'Document',
            self::VIDEO => 'Video',
            self::AUDIO => 'Audio',
            self::OTHER => 'Other',
        };
    }
}
