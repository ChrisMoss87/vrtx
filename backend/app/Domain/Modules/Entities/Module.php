<?php

declare(strict_types=1);

namespace App\Domain\Modules\Entities;

use App\Domain\Modules\ValueObjects\ModuleSettings;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

final class Module implements AggregateRoot
{
    use HasDomainEvents;

    /** @var array<Block> */
    private array $blocks = [];

    /** @var array<Field> */
    private array $fields = [];

    private function __construct(
        private ?int $id,
        private string $name,
        private string $singularName,
        private string $apiName,
        private ?string $icon,
        private ?string $description,
        private bool $isActive,
        private ModuleSettings $settings,
        private int $displayOrder,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt = null,
        private ?DateTimeImmutable $deletedAt = null,
    ) {}

    public static function create(
        string $name,
        string $singularName,
        ?string $icon = null,
        ?string $description = null,
        ?ModuleSettings $settings = null,
        int $displayOrder = 0,
        ?string $apiName = null
    ): self {
        $apiName = $apiName ?? self::toSnakeCase(self::pluralize($name));

        return new self(
            id: null,
            name: $name,
            singularName: $singularName,
            apiName: $apiName,
            icon: $icon,
            description: $description,
            isActive: true,
            settings: $settings ?? ModuleSettings::default(),
            displayOrder: $displayOrder,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Reconstitute a Module from persistence.
     *
     * @param array<Block> $blocks
     * @param array<Field> $fields
     */
    public static function reconstitute(
        int $id,
        string $name,
        string $singularName,
        string $apiName,
        ?string $icon,
        ?string $description,
        bool $isActive,
        ModuleSettings $settings,
        int $displayOrder,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
        array $blocks = [],
        array $fields = [],
    ): self {
        $module = new self(
            id: $id,
            name: $name,
            singularName: $singularName,
            apiName: $apiName,
            icon: $icon,
            description: $description,
            isActive: $isActive,
            settings: $settings,
            displayOrder: $displayOrder,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
        $module->blocks = $blocks;
        $module->fields = $fields;

        return $module;
    }

    /**
     * Add a block to the module.
     *
     * @return self Returns a new instance with the block added
     */
    public function withBlock(Block $block): self
    {
        $module = new self(
            id: $this->id,
            name: $this->name,
            singularName: $this->singularName,
            apiName: $this->apiName,
            icon: $this->icon,
            description: $this->description,
            isActive: $this->isActive,
            settings: $this->settings,
            displayOrder: $this->displayOrder,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
        $module->blocks = [...$this->blocks, $block];
        $module->fields = $this->fields;

        return $module;
    }

    /**
     * Add a field to the module.
     *
     * @return self Returns a new instance with the field added
     */
    public function withField(Field $field): self
    {
        $module = new self(
            id: $this->id,
            name: $this->name,
            singularName: $this->singularName,
            apiName: $this->apiName,
            icon: $this->icon,
            description: $this->description,
            isActive: $this->isActive,
            settings: $this->settings,
            displayOrder: $this->displayOrder,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
        $module->blocks = $this->blocks;
        $module->fields = [...$this->fields, $field];

        return $module;
    }

    /**
     * Activate the module.
     *
     * @return self Returns a new instance with active state
     */
    public function activate(): self
    {
        if ($this->isActive) {
            return $this;
        }

        $module = new self(
            id: $this->id,
            name: $this->name,
            singularName: $this->singularName,
            apiName: $this->apiName,
            icon: $this->icon,
            description: $this->description,
            isActive: true,
            settings: $this->settings,
            displayOrder: $this->displayOrder,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
        $module->blocks = $this->blocks;
        $module->fields = $this->fields;

        return $module;
    }

    /**
     * Deactivate the module.
     *
     * @return self Returns a new instance with inactive state
     */
    public function deactivate(): self
    {
        if (!$this->isActive) {
            return $this;
        }

        $module = new self(
            id: $this->id,
            name: $this->name,
            singularName: $this->singularName,
            apiName: $this->apiName,
            icon: $this->icon,
            description: $this->description,
            isActive: false,
            settings: $this->settings,
            displayOrder: $this->displayOrder,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
        $module->blocks = $this->blocks;
        $module->fields = $this->fields;

        return $module;
    }

    /**
     * Update the module details.
     *
     * @return self Returns a new instance with updated details
     */
    public function updateDetails(
        string $name,
        string $singularName,
        ?string $icon,
        ?string $description
    ): self {
        $apiName = self::toSnakeCase(self::pluralize($name));

        $module = new self(
            id: $this->id,
            name: $name,
            singularName: $singularName,
            apiName: $apiName,
            icon: $icon,
            description: $description,
            isActive: $this->isActive,
            settings: $this->settings,
            displayOrder: $this->displayOrder,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
        $module->blocks = $this->blocks;
        $module->fields = $this->fields;

        return $module;
    }

    /**
     * Update the module settings.
     *
     * @return self Returns a new instance with updated settings
     */
    public function updateSettings(ModuleSettings $settings): self
    {
        $module = new self(
            id: $this->id,
            name: $this->name,
            singularName: $this->singularName,
            apiName: $this->apiName,
            icon: $this->icon,
            description: $this->description,
            isActive: $this->isActive,
            settings: $settings,
            displayOrder: $this->displayOrder,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
        $module->blocks = $this->blocks;
        $module->fields = $this->fields;

        return $module;
    }

    /**
     * Update the display order.
     *
     * @return self Returns a new instance with updated display order
     */
    public function updateDisplayOrder(int $displayOrder): self
    {
        $module = new self(
            id: $this->id,
            name: $this->name,
            singularName: $this->singularName,
            apiName: $this->apiName,
            icon: $this->icon,
            description: $this->description,
            isActive: $this->isActive,
            settings: $this->settings,
            displayOrder: $displayOrder,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
        $module->blocks = $this->blocks;
        $module->fields = $this->fields;

        return $module;
    }

    // -------------------------------------------------------------------------
    // Entity Interface Methods
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
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

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getName(): string
    {
        return $this->name;
    }

    public function getSingularName(): string
    {
        return $this->singularName;
    }

    public function getApiName(): string
    {
        return $this->apiName;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getSettings(): ModuleSettings
    {
        return $this->settings;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    /**
     * @return array<Block>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @return array<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Convert a string to snake_case.
     */
    private static function toSnakeCase(string $value): string
    {
        $value = preg_replace('/\s+/u', '', ucwords($value));
        return strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $value));
    }

    /**
     * Simple pluralization for common English words.
     */
    private static function pluralize(string $value): string
    {
        $value = trim($value);

        if (empty($value)) {
            return $value;
        }

        // Common irregular plurals
        $irregulars = [
            'person' => 'people',
            'child' => 'children',
            'man' => 'men',
            'woman' => 'women',
            'tooth' => 'teeth',
            'foot' => 'feet',
            'mouse' => 'mice',
            'goose' => 'geese',
        ];

        $lowerValue = strtolower($value);
        if (isset($irregulars[$lowerValue])) {
            return $irregulars[$lowerValue];
        }

        // Already plural or uncountable
        if (preg_match('/(s|x|z|ch|sh)$/i', $value)) {
            return $value . 'es';
        }

        if (preg_match('/[^aeiou]y$/i', $value)) {
            return substr($value, 0, -1) . 'ies';
        }

        if (preg_match('/f$/i', $value)) {
            return substr($value, 0, -1) . 'ves';
        }

        if (preg_match('/fe$/i', $value)) {
            return substr($value, 0, -2) . 'ves';
        }

        return $value . 's';
    }
}
