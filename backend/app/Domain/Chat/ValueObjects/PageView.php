<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PageView
{
    private function __construct(
        private string $url,
        private ?string $title,
        private DateTimeImmutable $timestamp,
    ) {}

    public static function create(string $url, ?string $title = null): self
    {
        if (empty(trim($url))) {
            throw new InvalidArgumentException('URL cannot be empty');
        }

        return new self($url, $title, new DateTimeImmutable());
    }

    public static function reconstitute(string $url, ?string $title, DateTimeImmutable $timestamp): self
    {
        return new self($url, $title, $timestamp);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function equals(self $other): bool
    {
        return $this->url === $other->url
            && $this->timestamp == $other->timestamp;
    }
}
