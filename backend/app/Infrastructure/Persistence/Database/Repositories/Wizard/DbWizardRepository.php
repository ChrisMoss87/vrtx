<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Wizard;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Wizard\Entities\Wizard as WizardEntity;
use App\Domain\Wizard\Repositories\WizardRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class DbWizardRepository implements WizardRepositoryInterface
{
    private const TABLE = 'wizards';
    private const TABLE_STEPS = 'wizard_steps';
    private const TABLE_MODULES = 'modules';
    private const TABLE_USERS = 'users';
    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    public function findById(int $id): ?WizardEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByIdAsArray(int $id): ?array
    {
        $wizard = DB::table(self::TABLE)->where('id', $id)->first();

        return $wizard ? $this->toArray($wizard) : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $wizard = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$wizard) {
            return null;
        }

        $result = $this->toArray($wizard);
        $result['steps'] = $this->findSteps($id);
        $result['module'] = $wizard->module_id ? $this->getModuleById($wizard->module_id) : null;
        $result['creator'] = $wizard->created_by ? $this->getUserById($wizard->created_by) : null;

        return $result;
    }

    public function findAll(array $filters = []): array
    {
        $query = DB::table(self::TABLE);

        $this->applyFilters($query, $filters);

        $wizards = $query->orderBy('display_order')->get();

        return array_map(function ($wizard) {
            $result = $this->toArray($wizard);
            $result['steps'] = $this->findSteps($wizard->id);
            $result['module'] = $wizard->module_id ? $this->getModuleById($wizard->module_id) : null;
            $result['creator'] = $wizard->created_by ? $this->getUserById($wizard->created_by) : null;
            return $result;
        }, $wizards->all());
    }

    public function findWithFilters(array $filters = [], int $perPage = 15): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        $this->applyFilters($query, $filters);

        $total = $query->count();
        $page = $filters['page'] ?? 1;
        $offset = ($page - 1) * $perPage;

        $wizards = $query->orderBy('display_order')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = array_map(function ($wizard) {
            $result = $this->toArray($wizard);
            $result['steps'] = $this->findSteps($wizard->id);
            $result['module'] = $wizard->module_id ? $this->getModuleById($wizard->module_id) : null;
            $result['creator'] = $wizard->created_by ? $this->getUserById($wizard->created_by) : null;
            return $result;
        }, $wizards->all());

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findForModule(int $moduleId, bool $activeOnly = true): array
    {
        $query = DB::table(self::TABLE)->where('module_id', $moduleId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $wizards = $query->orderBy('display_order')->get();

        return array_map(function ($wizard) {
            $result = $this->toArray($wizard);
            $result['steps'] = $this->findSteps($wizard->id);
            $result['module'] = $wizard->module_id ? $this->getModuleById($wizard->module_id) : null;
            $result['creator'] = $wizard->created_by ? $this->getUserById($wizard->created_by) : null;
            return $result;
        }, $wizards->all());
    }

    public function findByType(string $type, bool $activeOnly = true): array
    {
        $query = DB::table(self::TABLE)->where('type', $type);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $wizards = $query->orderBy('display_order')->get();

        return array_map(function ($wizard) {
            $result = $this->toArray($wizard);
            $result['steps'] = $this->findSteps($wizard->id);
            $result['module'] = $wizard->module_id ? $this->getModuleById($wizard->module_id) : null;
            $result['creator'] = $wizard->created_by ? $this->getUserById($wizard->created_by) : null;
            return $result;
        }, $wizards->all());
    }

    public function findDefaultForModuleAndType(?int $moduleId, string $type): ?array
    {
        $wizard = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('type', $type)
            ->where('is_default', true)
            ->first();

        if (!$wizard) {
            return null;
        }

        $result = $this->toArray($wizard);
        $result['steps'] = $this->findSteps($wizard->id);
        $result['module'] = $wizard->module_id ? $this->getModuleById($wizard->module_id) : null;
        $result['creator'] = $wizard->created_by ? $this->getUserById($wizard->created_by) : null;

        return $result;
    }

    public function exists(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->exists();
    }

    // =========================================================================
    // COMMAND METHODS
    // =========================================================================

    public function create(array $data, array $steps = []): array
    {
        return DB::transaction(function () use ($data, $steps) {
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $wizardId = DB::table(self::TABLE)->insertGetId($data);

            foreach ($steps as $index => $stepData) {
                DB::table(self::TABLE_STEPS)->insert([
                    'wizard_id' => $wizardId,
                    'title' => $stepData['title'],
                    'description' => $stepData['description'] ?? null,
                    'type' => $stepData['type'],
                    'fields' => json_encode($stepData['fields'] ?? []),
                    'can_skip' => $stepData['can_skip'] ?? false,
                    'display_order' => $stepData['display_order'] ?? $index,
                    'conditional_logic' => isset($stepData['conditional_logic']) ? json_encode($stepData['conditional_logic']) : null,
                    'validation_rules' => isset($stepData['validation_rules']) ? json_encode($stepData['validation_rules']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->findByIdWithRelations($wizardId);
        });
    }

    public function update(int $id, array $data, ?array $steps = null): array
    {
        return DB::transaction(function () use ($id, $data, $steps) {
            // Filter out null values
            $filteredData = array_filter($data, fn ($value) => $value !== null);
            $filteredData['updated_at'] = now();

            DB::table(self::TABLE)->where('id', $id)->update($filteredData);

            if ($steps !== null) {
                $this->syncStepsById($id, $steps);
            }

            return $this->findByIdWithRelations($id);
        });
    }

    public function delete(int $id): bool
    {
        $wizard = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$wizard) {
            return false;
        }

        DB::table(self::TABLE_STEPS)->where('wizard_id', $id)->delete();
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function duplicate(int $id, ?string $newName = null, ?int $createdBy = null): array
    {
        return DB::transaction(function () use ($id, $newName, $createdBy) {
            $wizard = DB::table(self::TABLE)->where('id', $id)->first();

            if (!$wizard) {
                throw new \RuntimeException("Wizard with ID {$id} not found");
            }

            $cloneData = (array) $wizard;
            unset($cloneData['id'], $cloneData['created_at'], $cloneData['updated_at']);

            $cloneData['name'] = $newName ?? $cloneData['name'] . ' (Copy)';
            $cloneData['api_name'] = Str::snake($cloneData['name']);
            $cloneData['is_default'] = false;
            $cloneData['display_order'] = $this->getMaxDisplayOrder() + 1;
            $cloneData['created_at'] = now();
            $cloneData['updated_at'] = now();

            if ($createdBy !== null) {
                $cloneData['created_by'] = $createdBy;
            }

            $cloneId = DB::table(self::TABLE)->insertGetId($cloneData);

            $steps = DB::table(self::TABLE_STEPS)->where('wizard_id', $id)->get();

            foreach ($steps as $step) {
                $stepData = (array) $step;
                unset($stepData['id'], $stepData['created_at'], $stepData['updated_at']);
                $stepData['wizard_id'] = $cloneId;
                $stepData['created_at'] = now();
                $stepData['updated_at'] = now();

                DB::table(self::TABLE_STEPS)->insert($stepData);
            }

            return $this->findByIdWithRelations($cloneId);
        });
    }

    public function reorder(array $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order as $item) {
                DB::table(self::TABLE)
                    ->where('id', $item['id'])
                    ->update(['display_order' => $item['display_order']]);
            }
        });
    }

    public function toggleActive(int $id): array
    {
        $wizard = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$wizard) {
            throw new \RuntimeException("Wizard with ID {$id} not found");
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'is_active' => !$wizard->is_active,
            'updated_at' => now(),
        ]);

        return $this->findByIdWithRelations($id);
    }

    public function activate(int $id): array
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);

        return $this->findByIdWithRelations($id);
    }

    public function deactivate(int $id): array
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return $this->findByIdWithRelations($id);
    }

    public function setAsDefault(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $wizard = DB::table(self::TABLE)->where('id', $id)->first();

            if (!$wizard) {
                throw new \RuntimeException("Wizard with ID {$id} not found");
            }

            // Unset other defaults
            $this->unsetDefaultsExcept($wizard->module_id, $wizard->type, $id);

            // Set this as default
            DB::table(self::TABLE)->where('id', $id)->update([
                'is_default' => true,
                'updated_at' => now(),
            ]);

            return $this->findByIdWithRelations($id);
        });
    }

    public function unsetDefaultsExcept(?int $moduleId, string $type, ?int $exceptId = null): void
    {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('type', $type);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    // =========================================================================
    // WIZARD STEP METHODS
    // =========================================================================

    public function findSteps(int $wizardId): array
    {
        $steps = DB::table(self::TABLE_STEPS)
            ->where('wizard_id', $wizardId)
            ->orderBy('display_order')
            ->get();

        return array_map(fn ($step) => $this->stepToArray($step), $steps->all());
    }

    public function findStepById(int $stepId): ?array
    {
        $step = DB::table(self::TABLE_STEPS)->where('id', $stepId)->first();

        return $step ? $this->stepToArray($step) : null;
    }

    public function createStep(int $wizardId, array $data): array
    {
        $stepId = DB::table(self::TABLE_STEPS)->insertGetId([
            'wizard_id' => $wizardId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'fields' => json_encode($data['fields'] ?? []),
            'can_skip' => $data['can_skip'] ?? false,
            'display_order' => $data['display_order'] ?? $this->getMaxStepDisplayOrder($wizardId) + 1,
            'conditional_logic' => isset($data['conditional_logic']) ? json_encode($data['conditional_logic']) : null,
            'validation_rules' => isset($data['validation_rules']) ? json_encode($data['validation_rules']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $step = DB::table(self::TABLE_STEPS)->where('id', $stepId)->first();
        return $this->stepToArray($step);
    }

    public function updateStep(int $stepId, array $data): array
    {
        // Filter out null values
        $filteredData = array_filter($data, fn ($value) => $value !== null);

        // Handle JSON fields
        if (isset($filteredData['fields'])) {
            $filteredData['fields'] = json_encode($filteredData['fields']);
        }
        if (isset($filteredData['conditional_logic'])) {
            $filteredData['conditional_logic'] = json_encode($filteredData['conditional_logic']);
        }
        if (isset($filteredData['validation_rules'])) {
            $filteredData['validation_rules'] = json_encode($filteredData['validation_rules']);
        }

        $filteredData['updated_at'] = now();

        DB::table(self::TABLE_STEPS)->where('id', $stepId)->update($filteredData);

        $step = DB::table(self::TABLE_STEPS)->where('id', $stepId)->first();
        return $this->stepToArray($step);
    }

    public function deleteStep(int $stepId): bool
    {
        $step = DB::table(self::TABLE_STEPS)->where('id', $stepId)->first();

        if (!$step) {
            return false;
        }

        return DB::table(self::TABLE_STEPS)->where('id', $stepId)->delete() > 0;
    }

    public function reorderSteps(int $wizardId, array $order): void
    {
        DB::transaction(function () use ($wizardId, $order) {
            foreach ($order as $item) {
                DB::table(self::TABLE_STEPS)
                    ->where('id', $item['id'])
                    ->where('wizard_id', $wizardId)
                    ->update(['display_order' => $item['display_order']]);
            }
        });
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    public function getMaxDisplayOrder(): int
    {
        return (int) DB::table(self::TABLE)->max('display_order');
    }

    public function getMaxStepDisplayOrder(int $wizardId): int
    {
        return (int) DB::table(self::TABLE_STEPS)->where('wizard_id', $wizardId)->max('display_order');
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_default'])) {
            $query->where('is_default', $filters['is_default']);
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->where('is_active', true);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('api_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
    }

    private function syncStepsById(int $wizardId, array $steps): void
    {
        $existingStepIds = [];

        foreach ($steps as $index => $stepData) {
            if (isset($stepData['id'])) {
                $step = DB::table(self::TABLE_STEPS)->where('id', $stepData['id'])->first();
                if ($step && $step->wizard_id === $wizardId) {
                    DB::table(self::TABLE_STEPS)->where('id', $stepData['id'])->update([
                        'title' => $stepData['title'],
                        'description' => $stepData['description'] ?? null,
                        'type' => $stepData['type'],
                        'fields' => json_encode($stepData['fields'] ?? []),
                        'can_skip' => $stepData['can_skip'] ?? false,
                        'display_order' => $stepData['display_order'] ?? $index,
                        'conditional_logic' => isset($stepData['conditional_logic']) ? json_encode($stepData['conditional_logic']) : null,
                        'validation_rules' => isset($stepData['validation_rules']) ? json_encode($stepData['validation_rules']) : null,
                        'updated_at' => now(),
                    ]);
                    $existingStepIds[] = $step->id;
                }
            } else {
                $newStepId = DB::table(self::TABLE_STEPS)->insertGetId([
                    'wizard_id' => $wizardId,
                    'title' => $stepData['title'],
                    'description' => $stepData['description'] ?? null,
                    'type' => $stepData['type'],
                    'fields' => json_encode($stepData['fields'] ?? []),
                    'can_skip' => $stepData['can_skip'] ?? false,
                    'display_order' => $stepData['display_order'] ?? $index,
                    'conditional_logic' => isset($stepData['conditional_logic']) ? json_encode($stepData['conditional_logic']) : null,
                    'validation_rules' => isset($stepData['validation_rules']) ? json_encode($stepData['validation_rules']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $existingStepIds[] = $newStepId;
            }
        }

        DB::table(self::TABLE_STEPS)
            ->where('wizard_id', $wizardId)
            ->whereNotIn('id', $existingStepIds)
            ->delete();
    }

    private function getModuleById(int $moduleId): ?array
    {
        $module = DB::table(self::TABLE_MODULES)->where('id', $moduleId)->first();
        return $module ? (array) $module : null;
    }

    private function getUserById(int $userId): ?array
    {
        $user = DB::table(self::TABLE_USERS)
            ->select('id', 'name', 'email')
            ->where('id', $userId)
            ->first();
        return $user ? (array) $user : null;
    }

    // =========================================================================
    // DDD METHODS
    // =========================================================================

    /**
     * Save a wizard entity.
     */
    public function save(WizardEntity $entity): WizardEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId()) {
            $data['updated_at'] = now();
            DB::table(self::TABLE)->where('id', $entity->getId())->update($data);
            $row = DB::table(self::TABLE)->where('id', $entity->getId())->first();
        } else {
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $id = DB::table(self::TABLE)->insertGetId($data);
            $row = DB::table(self::TABLE)->where('id', $id)->first();
        }

        return $this->toDomainEntity($row);
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    /**
     * Map database row to domain entity.
     */
    private function toDomainEntity(stdClass $row): WizardEntity
    {
        return WizardEntity::reconstitute(
            id: $row->id,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Map domain entity to model data.
     */
    private function toModelData(WizardEntity $entity): array
    {
        $data = [];

        if ($entity->getCreatedAt()) {
            $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');
        }

        if ($entity->getUpdatedAt()) {
            $data['updated_at'] = $entity->getUpdatedAt()->format('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * Convert database row to array.
     */
    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
            'api_name' => $row->api_name ?? null,
            'description' => $row->description ?? null,
            'type' => $row->type ?? null,
            'module_id' => $row->module_id ?? null,
            'created_by' => $row->created_by ?? null,
            'is_active' => (bool) ($row->is_active ?? false),
            'is_default' => (bool) ($row->is_default ?? false),
            'display_order' => $row->display_order ?? 0,
            'settings' => isset($row->settings) ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Convert step row to array.
     */
    private function stepToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'wizard_id' => $row->wizard_id,
            'title' => $row->title,
            'description' => $row->description ?? null,
            'type' => $row->type,
            'fields' => isset($row->fields) ? (is_string($row->fields) ? json_decode($row->fields, true) : $row->fields) : [],
            'can_skip' => (bool) ($row->can_skip ?? false),
            'display_order' => $row->display_order ?? 0,
            'conditional_logic' => isset($row->conditional_logic) ? (is_string($row->conditional_logic) ? json_decode($row->conditional_logic, true) : $row->conditional_logic) : null,
            'validation_rules' => isset($row->validation_rules) ? (is_string($row->validation_rules) ? json_decode($row->validation_rules, true) : $row->validation_rules) : null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
