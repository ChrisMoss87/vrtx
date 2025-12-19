<?php

declare(strict_types=1);

namespace App\Domain\CMS\ValueObjects;

enum TemplateType: string
{
    case PAGE = 'page';
    case EMAIL = 'email';
    case FORM = 'form';
    case LANDING = 'landing';
    case BLOG = 'blog';
    case PARTIAL = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::PAGE => 'Page Template',
            self::EMAIL => 'Email Template',
            self::FORM => 'Form Template',
            self::LANDING => 'Landing Page Template',
            self::BLOG => 'Blog Template',
            self::PARTIAL => 'Partial Template',
        };
    }
}
