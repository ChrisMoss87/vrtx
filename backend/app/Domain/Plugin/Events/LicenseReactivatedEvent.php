<?php

declare(strict_types=1);

namespace App\Domain\Plugin\Events;

use App\Domain\Plugin\ValueObjects\PluginSlug;
use App\Domain\Shared\Contracts\DomainEvent;
use DateTimeImmutable;

final readonly class LicenseReactivatedEvent implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public PluginSlug $pluginSlug,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'plugin.license.reactivated';
    }

    public function toArray(): array
    {
        return [
            'plugin_slug' => $this->pluginSlug->value(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
