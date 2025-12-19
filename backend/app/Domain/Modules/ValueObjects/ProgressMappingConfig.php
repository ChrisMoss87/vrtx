<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

/**
 * Configuration for progress mapper fields (e.g., sales pipeline stages).
 * Maps status values to percentages for visual progress representation.
 */
final readonly class ProgressMappingConfig implements JsonSerializable
{
    /**
     * @param array<array{value: string, label: string, percentage: int, color?: string}> $stages
     * @param bool $showPercentage Whether to display the percentage value
     * @param bool $showLabel Whether to display the stage label
     * @param string $displayStyle How to display: 'bar', 'steps', 'funnel'
     * @param bool $allowBackward Whether to allow moving to previous stages
     * @param string|null $completedColor Color when 100% complete
     */
    public function __construct(
        public array $stages,
        public bool $showPercentage = true,
        public bool $showLabel = true,
        public string $displayStyle = 'bar',
        public bool $allowBackward = true,
        public ?string $completedColor = null,
    ) {}

    public static function default(): self
    {
        return new self(
            stages: [
                ['value' => 'new', 'label' => 'New', 'percentage' => 0, 'color' => '#94a3b8'],
                ['value' => 'qualified', 'label' => 'Qualified', 'percentage' => 25, 'color' => '#60a5fa'],
                ['value' => 'proposal', 'label' => 'Proposal', 'percentage' => 50, 'color' => '#fbbf24'],
                ['value' => 'negotiation', 'label' => 'Negotiation', 'percentage' => 75, 'color' => '#f97316'],
                ['value' => 'closed_won', 'label' => 'Closed Won', 'percentage' => 100, 'color' => '#22c55e'],
                ['value' => 'closed_lost', 'label' => 'Closed Lost', 'percentage' => 0, 'color' => '#ef4444'],
            ],
            showPercentage: true,
            showLabel: true,
            displayStyle: 'bar',
            allowBackward: true,
            completedColor: '#22c55e',
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            stages: $data['stages'] ?? [],
            showPercentage: $data['show_percentage'] ?? true,
            showLabel: $data['show_label'] ?? true,
            displayStyle: $data['display_style'] ?? 'bar',
            allowBackward: $data['allow_backward'] ?? true,
            completedColor: $data['completed_color'] ?? null,
        );
    }

    /**
     * Get the percentage for a given stage value.
     */
    public function getPercentageForValue(string $value): int
    {
        foreach ($this->stages as $stage) {
            if ($stage['value'] === $value) {
                return $stage['percentage'];
            }
        }
        return 0;
    }

    /**
     * Get stage info by value.
     * @return array{value: string, label: string, percentage: int, color?: string}|null
     */
    public function getStageByValue(string $value): ?array
    {
        foreach ($this->stages as $stage) {
            if ($stage['value'] === $value) {
                return $stage;
            }
        }
        return null;
    }

    /**
     * Check if the configuration is valid.
     */
    public function isValid(): bool
    {
        if (empty($this->stages)) {
            return false;
        }

        foreach ($this->stages as $stage) {
            if (!isset($stage['value'], $stage['label'], $stage['percentage'])) {
                return false;
            }
            if ($stage['percentage'] < 0 || $stage['percentage'] > 100) {
                return false;
            }
        }

        return true;
    }

    public function jsonSerialize(): array
    {
        return [
            'stages' => $this->stages,
            'show_percentage' => $this->showPercentage,
            'show_label' => $this->showLabel,
            'display_style' => $this->displayStyle,
            'allow_backward' => $this->allowBackward,
            'completed_color' => $this->completedColor,
        ];
    }
}
