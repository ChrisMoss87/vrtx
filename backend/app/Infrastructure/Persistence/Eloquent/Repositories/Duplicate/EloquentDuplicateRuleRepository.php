<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Duplicate;

use App\Domain\Duplicate\Repositories\DuplicateRuleRepositoryInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentDuplicateRuleRepository implements DuplicateRuleRepositoryInterface
{
    private const TABLE = 'duplicate_rules';
    private const TABLE_MODULES = 'modules';
    private const TABLE_USERS = 'users';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->rowToArrayWithRelations($row);
    }

    public function listRules(array $filters = []): array
    {
        $query = DB::table(self::TABLE);

        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $rows = $query->orderBy('priority')->orderBy('name')->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->toArray();
    }

    public function getActiveRulesForModule(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->rowToArray($row))->toArray();
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($row);
    }

    public function update(int $id, array $data): ?array
    {
        $exists = DB::table(self::TABLE)->where('id', $id)->exists();

        if (!$exists) {
            return null;
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($row);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function toggleActive(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'is_active' => !$row->is_active,
                'updated_at' => now(),
            ]);

        $updated = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($updated);
    }

    /**
     * Convert a database row to array.
     */
    private function rowToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'module_id' => $row->module_id,
            'name' => $row->name,
            'description' => $row->description ?? null,
            'match_fields' => $row->match_fields ? (is_string($row->match_fields) ? json_decode($row->match_fields, true) : $row->match_fields) : [],
            'match_type' => $row->match_type ?? 'exact',
            'threshold' => $row->threshold ?? 0.8,
            'is_active' => (bool) $row->is_active,
            'priority' => $row->priority ?? 0,
            'created_by' => $row->created_by ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Convert a database row to array with relations.
     */
    private function rowToArrayWithRelations(stdClass $row): array
    {
        $data = $this->rowToArray($row);

        // Load module relation
        if ($row->module_id) {
            $module = DB::table(self::TABLE_MODULES)
                ->select('id', 'name', 'api_name')
                ->where('id', $row->module_id)
                ->first();
            $data['module'] = $module ? (array) $module : null;
        }

        // Load creator relation
        if (!empty($row->created_by)) {
            $creator = DB::table(self::TABLE_USERS)
                ->select('id', 'name')
                ->where('id', $row->created_by)
                ->first();
            $data['creator'] = $creator ? (array) $creator : null;
        }

        return $data;
    }
}
