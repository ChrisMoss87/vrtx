<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting;

use App\Domain\Forecasting\Entities\ForecastScenario;
use App\Domain\Forecasting\Repositories\ForecastScenarioRepositoryInterface;
use App\Domain\Forecasting\ValueObjects\ScenarioType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the ForecastScenarioRepository.
 */
class EloquentForecastScenarioRepository implements ForecastScenarioRepositoryInterface
{
    private const TABLE = 'forecast_scenarios';

    public function findById(int $id): ?ForecastScenario
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toArray($row);
    }

    public function findByUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByUserAsArrays(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toArray($row))->all();
    }

    public function findByUserAndModule(int $userId, int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByUserAndModuleAsArrays(int $userId, int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toArray($row))->all();
    }

    public function findByPeriod(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): array {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('period_start', '<=', $periodEnd->format('Y-m-d'))
            ->where('period_end', '>=', $periodStart->format('Y-m-d'));

        if ($userId !== null) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('is_shared', true);
            });
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByPeriodAsArrays(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): array {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('period_start', '<=', $periodEnd->format('Y-m-d'))
            ->where('period_end', '>=', $periodStart->format('Y-m-d'));

        if ($userId !== null) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('is_shared', true);
            });
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->toArray($row))->all();
    }

    public function findBaseline(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): ?ForecastScenario {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_baseline', true)
            ->where('period_start', '<=', $periodEnd->format('Y-m-d'))
            ->where('period_end', '>=', $periodStart->format('Y-m-d'));

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $row = $query->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findBaselineAsArray(
        int $moduleId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        ?int $userId = null
    ): ?array {
        $query = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_baseline', true)
            ->where('period_start', '<=', $periodEnd->format('Y-m-d'))
            ->where('period_end', '>=', $periodStart->format('Y-m-d'));

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $row = $query->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findShared(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_shared', true)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findSharedAsArrays(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->where('is_shared', true)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toArray($row))->all();
    }

    public function findWithFilters(array $filters, int $perPage = 15): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (isset($filters['is_baseline'])) {
            $query->where('is_baseline', $filters['is_baseline']);
        }

        if (isset($filters['is_shared'])) {
            $query->where('is_shared', $filters['is_shared']);
        }

        if (isset($filters['scenario_type'])) {
            $query->where('scenario_type', $filters['scenario_type']);
        }

        if (isset($filters['period_start'])) {
            $query->where('period_start', '>=', $filters['period_start']);
        }

        if (isset($filters['period_end'])) {
            $query->where('period_end', '<=', $filters['period_end']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Get current page from filters
        $currentPage = $filters['page'] ?? 1;

        // Get total count
        $total = $query->count();

        // Get paginated rows
        $rows = $query
            ->offset(($currentPage - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Convert to arrays
        $arrayItems = $rows->map(fn($row) => $this->toArray($row))->all();

        return PaginatedResult::create(
            items: $arrayItems,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage
        );
    }

    public function save(ForecastScenario $scenario): ForecastScenario
    {
        $data = $this->toRowData($scenario);

        if ($scenario->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $scenario->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $scenario->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row): ForecastScenario
    {
        return ForecastScenario::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            description: $row->description,
            userId: UserId::fromInt((int) $row->user_id),
            moduleId: (int) $row->module_id,
            periodStart: new DateTimeImmutable($row->period_start),
            periodEnd: new DateTimeImmutable($row->period_end),
            scenarioType: ScenarioType::from($row->scenario_type),
            isBaseline: (bool) $row->is_baseline,
            isShared: (bool) $row->is_shared,
            totalWeighted: (float) $row->total_weighted,
            totalUnweighted: (float) $row->total_unweighted,
            targetAmount: $row->target_amount ? (float) $row->target_amount : null,
            dealCount: (int) ($row->deal_count ?? 0),
            settings: $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(ForecastScenario $scenario): array
    {
        return [
            'name' => $scenario->name(),
            'description' => $scenario->description(),
            'user_id' => $scenario->userId()->value(),
            'module_id' => $scenario->moduleId(),
            'period_start' => $scenario->periodStart()->format('Y-m-d'),
            'period_end' => $scenario->periodEnd()->format('Y-m-d'),
            'scenario_type' => $scenario->scenarioType()->value,
            'is_baseline' => $scenario->isBaseline(),
            'is_shared' => $scenario->isShared(),
            'total_weighted' => $scenario->totalWeighted(),
            'total_unweighted' => $scenario->totalUnweighted(),
            'target_amount' => $scenario->targetAmount(),
            'deal_count' => $scenario->dealCount(),
            'settings' => json_encode($scenario->settings()),
        ];
    }

    /**
     * Convert a database row to an array.
     *
     * @return array<string, mixed>
     */
    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'user_id' => $row->user_id,
            'module_id' => $row->module_id,
            'period_start' => $row->period_start,
            'period_end' => $row->period_end,
            'scenario_type' => $row->scenario_type,
            'is_baseline' => (bool) $row->is_baseline,
            'is_shared' => (bool) $row->is_shared,
            'total_weighted' => (float) $row->total_weighted,
            'total_unweighted' => (float) $row->total_unweighted,
            'target_amount' => $row->target_amount ? (float) $row->target_amount : null,
            'deal_count' => (int) ($row->deal_count ?? 0),
            'settings' => $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
