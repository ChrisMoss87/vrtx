<?php

declare(strict_types=1);

namespace App\Domain\CMS\ValueObjects;

enum PageType: string
{
    case PAGE = 'page';
    case LANDING = 'landing';
    case BLOG = 'blog';
    case ARTICLE = 'article';

    public function label(): string
    {
        return match ($this) {
            self::PAGE => 'Page',
            self::LANDING => 'Landing Page',
            self::BLOG => 'Blog Post',
            self::ARTICLE => 'Article',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PAGE => 'file-text',
            self::LANDING => 'layout',
            self::BLOG => 'pen-tool',
            self::ARTICLE => 'newspaper',
        };
    }
}
