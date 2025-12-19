<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Wizard;

use App\Domain\Wizard\Repositories\WizardRepositoryInterface;
use App\Models\Wizard;
use App\Models\WizardStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentWizardRepository implements WizardRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $wizard = Wizard::find($id);

        return $wizard?->toArray();
    }

    public function findByIdWithSteps(int $id): ?array
    {
        $wizard = Wizard::with(['steps', 'module', 'creator'])->find($id);

        return $wizard?->toArray();
    }

    public function list(
        ?int $moduleId = null,
        ?string $type = null,
        bool $activeOnly = false
    ): Collection {
        $query = Wizard::with(['steps', 'module', 'creator']);

        if ($moduleId !== null) {
            $query->where('module_id', $moduleId);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('display_order')->get();
    }

    public function getForModule(int $moduleId, bool $activeOnly = true): Collection
    {
        $query = Wizard::with('steps')
            ->where('module_id', $moduleId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('display_order')->get();
    }

    public function create(array $data, array $steps): array
    {
        return DB::transaction(function () use ($data, $steps) {
            $wizard = Wizard::create($data);

            foreach ($steps as $index => $stepData) {
                WizardStep::create([
                    'wizard_id' => $wizard->id,
                    'title' => $stepData['title'],
                    'description' => $stepData['description'] ?? null,
                    'type' => $stepData['type'],
                    'fields' => $stepData['fields'] ?? [],
                    'can_skip' => $stepData['can_skip'] ?? false,
                    'display_order' => $index,
                    'conditional_logic' => $stepData['conditional_logic'] ?? null,
                    'validation_rules' => $stepData['validation_rules'] ?? null,
                ]);
            }

            return $wizard->load(['steps', 'module', 'creator'])->toArray();
        });
    }

    public function update(int $id, array $data, ?array $steps = null): array
    {
        return DB::transaction(function () use ($id, $data, $steps) {
            $wizard = Wizard::findOrFail($id);
            $wizard->update($data);

            if ($steps !== null) {
                $this->syncSteps($wizard, $steps);
            }

            return $wizard->fresh(['steps', 'module', 'creator'])->toArray();
        });
    }

    public function delete(int $id): bool
    {
        $wizard = Wizard::find($id);

        if (!$wizard) {
            return false;
        }

        return $wizard->delete() ?? false;
    }

    public function duplicate(int $id): array
    {
        $wizard = Wizard::with('steps')->findOrFail($id);
        $clone = $wizard->duplicate();

        return $clone->load(['steps', 'module', 'creator'])->toArray();
    }

    public function reorder(array $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order as $item) {
                Wizard::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
            }
        });
    }

    public function toggleActive(int $id): array
    {
        $wizard = Wizard::findOrFail($id);
        $wizard->update(['is_active' => !$wizard->is_active]);

        return $wizard->fresh(['steps', 'module', 'creator'])->toArray();
    }

    public function unsetDefaultsExcept(?int $moduleId, string $type, ?int $exceptId = null): void
    {
        $query = Wizard::where('module_id', $moduleId)
            ->where('type', $type);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    public function getMaxDisplayOrder(): int
    {
        return (int) Wizard::max('display_order');
    }

    private function syncSteps(Wizard $wizard, array $steps): void
    {
        $existingStepIds = [];

        foreach ($steps as $index => $stepData) {
            if (isset($stepData['id'])) {
                $step = WizardStep::find($stepData['id']);
                if ($step && $step->wizard_id === $wizard->id) {
                    $step->update([
                        'title' => $stepData['title'],
                        'description' => $stepData['description'] ?? null,
                        'type' => $stepData['type'],
                        'fields' => $stepData['fields'] ?? [],
                        'can_skip' => $stepData['can_skip'] ?? false,
                        'display_order' => $index,
                        'conditional_logic' => $stepData['conditional_logic'] ?? null,
                        'validation_rules' => $stepData['validation_rules'] ?? null,
                    ]);
                    $existingStepIds[] = $step->id;
                }
            } else {
                $step = WizardStep::create([
                    'wizard_id' => $wizard->id,
                    'title' => $stepData['title'],
                    'description' => $stepData['description'] ?? null,
                    'type' => $stepData['type'],
                    'fields' => $stepData['fields'] ?? [],
                    'can_skip' => $stepData['can_skip'] ?? false,
                    'display_order' => $index,
                    'conditional_logic' => $stepData['conditional_logic'] ?? null,
                    'validation_rules' => $stepData['validation_rules'] ?? null,
                ]);
                $existingStepIds[] = $step->id;
            }
        }

        WizardStep::where('wizard_id', $wizard->id)
            ->whereNotIn('id', $existingStepIds)
            ->delete();
    }
}
