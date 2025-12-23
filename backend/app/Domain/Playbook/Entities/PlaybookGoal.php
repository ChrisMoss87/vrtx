<?php

declare(strict_types=1);

namespace App\Domain\Playbook\Entities;

use App\Domain\Playbook\ValueObjects\ComparisonOperator;
use App\Domain\Playbook\ValueObjects\MetricType;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class PlaybookGoal implements Entity
{
    private function __construct(
        private ?int $id,
        private int $playbookId,
        private string $name,
        private MetricType $metricType,
        private ?string $targetModule,
        private ?string $targetField,
        private ComparisonOperator $comparisonOperator,
        private float $targetValue,
        private ?int $targetDays,
        private ?string $description,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $playbookId,
        string $name,
        MetricType $metricType,
        ComparisonOperator $comparisonOperator,
        float $targetValue,
    ): self {
        return new self(
            id: null,
            playbookId: $playbookId,
            name: $name,
            metricType: $metricType,
            targetModule: null,
            targetField: null,
            comparisonOperator: $comparisonOperator,
            targetValue: $targetValue,
            targetDays: null,
            description: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $playbookId,
        string $name,
        MetricType $metricType,
        ?string $targetModule,
        ?string $targetField,
        ComparisonOperator $comparisonOperator,
        float $targetValue,
        ?int $targetDays,
        ?string $description,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            playbookId: $playbookId,
            name: $name,
            metricType: $metricType,
            targetModule: $targetModule,
            targetField: $targetField,
            comparisonOperator: $comparisonOperator,
            targetValue: $targetValue,
            targetDays: $targetDays,
            description: $description,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function update(
        string $name,
        ?string $description = null,
        ?int $targetDays = null,
    ): self {
        $clone = clone $this;
        $clone->name = $name;
        $clone->description = $description;
        $clone->targetDays = $targetDays;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateTarget(
        ComparisonOperator $comparisonOperator,
        float $targetValue,
    ): self {
        $clone = clone $this;
        $clone->comparisonOperator = $comparisonOperator;
        $clone->targetValue = $targetValue;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateMetricSource(
        ?string $targetModule,
        ?string $targetField,
    ): self {
        $clone = clone $this;
        $clone->targetModule = $targetModule;
        $clone->targetField = $targetField;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateMetricType(MetricType $metricType): self
    {
        $clone = clone $this;
        $clone->metricType = $metricType;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function evaluate(mixed $actualValue): bool
    {
        return $this->comparisonOperator->compare($actualValue, $this->targetValue);
    }

    public function hasMetricSource(): bool
    {
        return $this->targetModule !== null && $this->targetField !== null;
    }

    public function hasTargetDays(): bool
    {
        return $this->targetDays !== null && $this->targetDays > 0;
    }

    public function getAchievementPercentage(mixed $actualValue): float
    {
        if ($this->targetValue == 0) {
            return 0.0;
        }

        $percentage = ($actualValue / $this->targetValue) * 100;

        // For operators like "less than", invert the calculation
        if (in_array($this->comparisonOperator, [ComparisonOperator::LESS_THAN, ComparisonOperator::LESS_THAN_OR_EQUAL])) {
            $percentage = max(0, 200 - $percentage);
        }

        return round(min(100, max(0, $percentage)), 2);
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlaybookId(): int
    {
        return $this->playbookId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMetricType(): MetricType
    {
        return $this->metricType;
    }

    public function getTargetModule(): ?string
    {
        return $this->targetModule;
    }

    public function getTargetField(): ?string
    {
        return $this->targetField;
    }

    public function getComparisonOperator(): ComparisonOperator
    {
        return $this->comparisonOperator;
    }

    public function getTargetValue(): float
    {
        return $this->targetValue;
    }

    public function getTargetDays(): ?int
    {
        return $this->targetDays;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function equals(Entity $other): bool
    {
        return $other instanceof self
            && $this->id !== null
            && $this->id === $other->id;
    }
}
