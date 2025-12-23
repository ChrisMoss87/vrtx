<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

final readonly class WidgetStyling
{
    private function __construct(
        private string $primaryColor,
        private string $textColor,
        private string $backgroundColor,
        private string $launcherIcon,
        private string $headerText,
        private int $borderRadius,
    ) {}

    public static function createDefault(): self
    {
        return new self(
            primaryColor: '#3B82F6',
            textColor: '#FFFFFF',
            backgroundColor: '#FFFFFF',
            launcherIcon: 'chat',
            headerText: 'Chat with us',
            borderRadius: 12,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            primaryColor: $data['primary_color'] ?? '#3B82F6',
            textColor: $data['text_color'] ?? '#FFFFFF',
            backgroundColor: $data['background_color'] ?? '#FFFFFF',
            launcherIcon: $data['launcher_icon'] ?? 'chat',
            headerText: $data['header_text'] ?? 'Chat with us',
            borderRadius: $data['border_radius'] ?? 12,
        );
    }

    public function toArray(): array
    {
        return [
            'primary_color' => $this->primaryColor,
            'text_color' => $this->textColor,
            'background_color' => $this->backgroundColor,
            'launcher_icon' => $this->launcherIcon,
            'header_text' => $this->headerText,
            'border_radius' => $this->borderRadius,
        ];
    }

    public function getPrimaryColor(): string
    {
        return $this->primaryColor;
    }

    public function getTextColor(): string
    {
        return $this->textColor;
    }

    public function getBackgroundColor(): string
    {
        return $this->backgroundColor;
    }

    public function getLauncherIcon(): string
    {
        return $this->launcherIcon;
    }

    public function getHeaderText(): string
    {
        return $this->headerText;
    }

    public function getBorderRadius(): int
    {
        return $this->borderRadius;
    }
}
