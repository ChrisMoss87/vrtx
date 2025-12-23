<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

final readonly class WidgetSettings
{
    private function __construct(
        private string $position,
        private string $greetingMessage,
        private string $offlineMessage,
        private bool $requireEmail,
        private bool $requireName,
        private bool $showAvatar,
        private bool $soundEnabled,
        private int $autoOpenDelay,
    ) {}

    public static function createDefault(): self
    {
        return new self(
            position: 'bottom-right',
            greetingMessage: 'Hi! How can we help you today?',
            offlineMessage: "We're currently offline. Leave a message and we'll get back to you.",
            requireEmail: true,
            requireName: true,
            showAvatar: true,
            soundEnabled: true,
            autoOpenDelay: 0,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            position: $data['position'] ?? 'bottom-right',
            greetingMessage: $data['greeting_message'] ?? 'Hi! How can we help you today?',
            offlineMessage: $data['offline_message'] ?? "We're currently offline. Leave a message and we'll get back to you.",
            requireEmail: $data['require_email'] ?? true,
            requireName: $data['require_name'] ?? true,
            showAvatar: $data['show_avatar'] ?? true,
            soundEnabled: $data['sound_enabled'] ?? true,
            autoOpenDelay: $data['auto_open_delay'] ?? 0,
        );
    }

    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'greeting_message' => $this->greetingMessage,
            'offline_message' => $this->offlineMessage,
            'require_email' => $this->requireEmail,
            'require_name' => $this->requireName,
            'show_avatar' => $this->showAvatar,
            'sound_enabled' => $this->soundEnabled,
            'auto_open_delay' => $this->autoOpenDelay,
        ];
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function getGreetingMessage(): string
    {
        return $this->greetingMessage;
    }

    public function getOfflineMessage(): string
    {
        return $this->offlineMessage;
    }

    public function requiresEmail(): bool
    {
        return $this->requireEmail;
    }

    public function requiresName(): bool
    {
        return $this->requireName;
    }

    public function shouldShowAvatar(): bool
    {
        return $this->showAvatar;
    }

    public function isSoundEnabled(): bool
    {
        return $this->soundEnabled;
    }

    public function getAutoOpenDelay(): int
    {
        return $this->autoOpenDelay;
    }
}
