<?php

declare(strict_types=1);

namespace App\Domain\Chat\Entities;

use App\Domain\Chat\ValueObjects\WidgetSettings;
use App\Domain\Chat\ValueObjects\WidgetStyling;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

final class ChatWidget implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private string $widgetKey,
        private bool $isActive,
        private WidgetSettings $settings,
        private WidgetStyling $styling,
        private ?array $routingRules,
        private ?array $businessHours,
        private ?array $allowedDomains,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        string $widgetKey,
        ?WidgetSettings $settings = null,
        ?WidgetStyling $styling = null,
    ): self {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Widget name cannot be empty');
        }

        if (empty(trim($widgetKey))) {
            throw new InvalidArgumentException('Widget key cannot be empty');
        }

        return new self(
            id: null,
            name: $name,
            widgetKey: $widgetKey,
            isActive: true,
            settings: $settings ?? WidgetSettings::createDefault(),
            styling: $styling ?? WidgetStyling::createDefault(),
            routingRules: null,
            businessHours: null,
            allowedDomains: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $widgetKey,
        bool $isActive,
        WidgetSettings $settings,
        WidgetStyling $styling,
        ?array $routingRules,
        ?array $businessHours,
        ?array $allowedDomains,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            widgetKey: $widgetKey,
            isActive: $isActive,
            settings: $settings,
            styling: $styling,
            routingRules: $routingRules,
            businessHours: $businessHours,
            allowedDomains: $allowedDomains,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function activate(): self
    {
        if ($this->isActive) {
            return $this;
        }

        $new = clone $this;
        $new->isActive = true;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function deactivate(): self
    {
        if (!$this->isActive) {
            return $this;
        }

        $new = clone $this;
        $new->isActive = false;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function rename(string $name): self
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Widget name cannot be empty');
        }

        if ($this->name === $name) {
            return $this;
        }

        $new = clone $this;
        $new->name = $name;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateSettings(WidgetSettings $settings): self
    {
        $new = clone $this;
        $new->settings = $settings;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function updateStyling(WidgetStyling $styling): self
    {
        $new = clone $this;
        $new->styling = $styling;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function setRoutingRules(array $rules): self
    {
        $new = clone $this;
        $new->routingRules = $rules;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function setBusinessHours(array $hours): self
    {
        $new = clone $this;
        $new->businessHours = $hours;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function setAllowedDomains(array $domains): self
    {
        $new = clone $this;
        $new->allowedDomains = $domains;
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function addAllowedDomain(string $domain): self
    {
        $domain = trim($domain);
        if (empty($domain)) {
            throw new InvalidArgumentException('Domain cannot be empty');
        }

        $domains = $this->allowedDomains ?? [];
        if (in_array($domain, $domains, true)) {
            return $this;
        }

        $new = clone $this;
        $new->allowedDomains = [...$domains, $domain];
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function removeAllowedDomain(string $domain): self
    {
        if (empty($this->allowedDomains)) {
            return $this;
        }

        $domains = array_filter($this->allowedDomains, fn($d) => $d !== $domain);
        if (count($domains) === count($this->allowedDomains)) {
            return $this;
        }

        $new = clone $this;
        $new->allowedDomains = array_values($domains);
        $new->updatedAt = new DateTimeImmutable();

        return $new;
    }

    public function isDomainAllowed(?string $domain): bool
    {
        // If no domains specified, all are allowed
        if (empty($this->allowedDomains)) {
            return true;
        }

        if ($domain === null) {
            return false;
        }

        foreach ($this->allowedDomains as $allowed) {
            if ($allowed === '*') {
                return true;
            }
            if (str_ends_with($domain, $allowed)) {
                return true;
            }
        }

        return false;
    }

    public function isOnlineDuringBusinessHours(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if (empty($this->businessHours)) {
            return true;
        }

        $now = new DateTimeImmutable();
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        $hours = $this->businessHours[$dayOfWeek] ?? null;
        if ($hours === null || empty($hours['enabled'])) {
            return false;
        }

        return $currentTime >= $hours['start'] && $currentTime <= $hours['end'];
    }

    public function getEmbedCode(): string
    {
        $key = $this->widgetKey;
        return <<<HTML
<script>
(function(w,d,s,o,f,js,fjs){
w['VRTXChat']=o;w[o]=w[o]||function(){(w[o].q=w[o].q||[]).push(arguments)};
js=d.createElement(s),fjs=d.getElementsByTagName(s)[0];
js.id=o;js.src=f;js.async=1;fjs.parentNode.insertBefore(js,fjs);
}(window,document,'script','vrtxChat','/chat-widget.js'));
vrtxChat('init', '{$key}');
</script>
HTML;
    }

    public function hasBusinessHours(): bool
    {
        return !empty($this->businessHours);
    }

    public function hasRoutingRules(): bool
    {
        return !empty($this->routingRules);
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWidgetKey(): string
    {
        return $this->widgetKey;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getSettings(): WidgetSettings
    {
        return $this->settings;
    }

    public function getStyling(): WidgetStyling
    {
        return $this->styling;
    }

    public function getRoutingRules(): ?array
    {
        return $this->routingRules;
    }

    public function getBusinessHours(): ?array
    {
        return $this->businessHours;
    }

    public function getAllowedDomains(): ?array
    {
        return $this->allowedDomains;
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
