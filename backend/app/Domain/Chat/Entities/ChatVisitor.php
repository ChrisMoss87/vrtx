<?php

declare(strict_types=1);

namespace App\Domain\Chat\Entities;

use App\Domain\Chat\ValueObjects\PageView;
use App\Domain\Chat\ValueObjects\VisitorLocation;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class ChatVisitor implements Entity
{
    private const MAX_PAGE_VIEWS = 50;

    private function __construct(
        private ?int $id,
        private int $widgetId,
        private ?int $contactId,
        private string $fingerprint,
        private ?string $ipAddress,
        private ?string $userAgent,
        private VisitorLocation $location,
        private ?string $name,
        private ?string $email,
        private ?array $customData,
        private array $pagesViewed,
        private ?string $currentPage,
        private ?string $referrer,
        private ?DateTimeImmutable $firstSeenAt,
        private ?DateTimeImmutable $lastSeenAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $widgetId,
        string $fingerprint,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrer = null,
    ): self {
        if ($widgetId <= 0) {
            throw new InvalidArgumentException('Widget ID must be positive');
        }

        if (empty(trim($fingerprint))) {
            throw new InvalidArgumentException('Fingerprint cannot be empty');
        }

        $now = new DateTimeImmutable();

        return new self(
            id: null,
            widgetId: $widgetId,
            contactId: null,
            fingerprint: $fingerprint,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            location: VisitorLocation::fromComponents(null, null),
            name: null,
            email: null,
            customData: null,
            pagesViewed: [],
            currentPage: null,
            referrer: $referrer,
            firstSeenAt: $now,
            lastSeenAt: $now,
            createdAt: $now,
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $widgetId,
        ?int $contactId,
        string $fingerprint,
        ?string $ipAddress,
        ?string $userAgent,
        VisitorLocation $location,
        ?string $name,
        ?string $email,
        ?array $customData,
        array $pagesViewed,
        ?string $currentPage,
        ?string $referrer,
        ?DateTimeImmutable $firstSeenAt,
        ?DateTimeImmutable $lastSeenAt,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            widgetId: $widgetId,
            contactId: $contactId,
            fingerprint: $fingerprint,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            location: $location,
            name: $name,
            email: $email,
            customData: $customData,
            pagesViewed: $pagesViewed,
            currentPage: $currentPage,
            referrer: $referrer,
            firstSeenAt: $firstSeenAt,
            lastSeenAt: $lastSeenAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function recordPageView(PageView $pageView): self
    {
        $pages = [...$this->pagesViewed, $pageView];

        // Keep last N page views
        if (count($pages) > self::MAX_PAGE_VIEWS) {
            $pages = array_slice($pages, -self::MAX_PAGE_VIEWS);
        }

        $new = clone $this;
        $new->pagesViewed = $pages;
        $new->currentPage = $pageView->getUrl();
        $new->lastSeenAt = new DateTimeImmutable();
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function identify(string $email, ?string $name = null): self
    {
        if (empty(trim($email))) {
            throw new InvalidArgumentException('Email cannot be empty');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        $new = clone $this;
        $new->email = $email;
        $new->name = $name ?? $this->name;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function linkContact(int $contactId): self
    {
        if ($contactId <= 0) {
            throw new InvalidArgumentException('Contact ID must be positive');
        }

        if ($this->contactId === $contactId) {
            return $this;
        }

        $new = clone $this;
        $new->contactId = $contactId;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateLocation(VisitorLocation $location): self
    {
        if ($this->location->equals($location)) {
            return $this;
        }

        $new = clone $this;
        $new->location = $location;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateCustomData(array $data): self
    {
        $new = clone $this;
        $new->customData = array_merge($this->customData ?? [], $data);
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateActivity(): self
    {
        $new = clone $this;
        $new->lastSeenAt = new DateTimeImmutable();
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function getDisplayName(): string
    {
        return $this->name ?? $this->email ?? "Visitor #{$this->id}";
    }

    public function isIdentified(): bool
    {
        return $this->email !== null;
    }

    public function hasContact(): bool
    {
        return $this->contactId !== null;
    }

    public function hasViewedPages(): bool
    {
        return !empty($this->pagesViewed);
    }

    public function getPageViewCount(): int
    {
        return count($this->pagesViewed);
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWidgetId(): int
    {
        return $this->widgetId;
    }

    public function getContactId(): ?int
    {
        return $this->contactId;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getLocation(): VisitorLocation
    {
        return $this->location;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getCustomData(): ?array
    {
        return $this->customData;
    }

    public function getPagesViewed(): array
    {
        return $this->pagesViewed;
    }

    public function getCurrentPage(): ?string
    {
        return $this->currentPage;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getFirstSeenAt(): ?DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): ?DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->id === null || $other->getId() === null) {
            return false;
        }

        return $this->id === $other->getId();
    }
}
