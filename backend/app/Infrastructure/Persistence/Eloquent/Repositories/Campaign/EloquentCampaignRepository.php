<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Campaign;

use App\Domain\Campaign\Entities\Campaign as CampaignEntity;
use App\Domain\Campaign\Repositories\CampaignRepositoryInterface;
use App\Domain\Campaign\ValueObjects\CampaignStatus;
use App\Domain\Campaign\ValueObjects\CampaignType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentCampaignRepository implements CampaignRepositoryInterface
{
    private const TABLE = 'campaigns';
    private const TABLE_AUDIENCES = 'campaign_audiences';
    private const TABLE_AUDIENCE_MEMBERS = 'campaign_audience_members';
    private const TABLE_ASSETS = 'campaign_assets';
    private const TABLE_SENDS = 'campaign_sends';
    private const TABLE_CLICKS = 'campaign_clicks';
    private const TABLE_UNSUBSCRIBES = 'campaign_unsubscribes';
    private const TABLE_CONVERSIONS = 'campaign_conversions';
    private const TABLE_METRICS = 'campaign_metrics';

    private const STATUS_DELIVERED = 'delivered';
    private const STATUS_BOUNCED = 'bounced';
    private const STATUS_PENDING = 'pending';
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?CampaignEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(CampaignEntity $campaign): CampaignEntity
    {
        $data = $this->toRowData($campaign);

        if ($campaign->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $campaign->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $campaign->getId();
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

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $row = DB::table(self::TABLE)
            ->where(self::TABLE . '.id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Load owner relation
        if ($row->owner_id) {
            $owner = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('id', $row->owner_id)
                ->first();
            $result['owner'] = $owner ? (array) $owner : null;
        }

        // Load creator relation
        if ($row->created_by) {
            $creator = DB::table('users')
                ->select('id', 'name')
                ->where('id', $row->created_by)
                ->first();
            $result['creator'] = $creator ? (array) $creator : null;
        }

        // Load module relation
        if ($row->module_id) {
            $module = DB::table('modules')
                ->where('id', $row->module_id)
                ->first();
            $result['module'] = $module ? (array) $module : null;
        }

        // Load audiences
        $audiences = DB::table(self::TABLE_AUDIENCES)
            ->where('campaign_id', $id)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
        $result['audiences'] = $audiences;

        // Load assets
        $assets = DB::table(self::TABLE_ASSETS)
            ->where('campaign_id', $id)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
        $result['assets'] = $assets;

        return $result;
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE)->where('id', $id)->first();
    }

    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return (array) DB::table(self::TABLE)->where('id', $id)->first();
    }

    public function delete(int $id): bool
    {
        // Soft delete
        $affected = DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['deleted_at' => now()]);

        return $affected > 0;
    }

    // =========================================================================
    // QUERY METHODS - CAMPAIGNS
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at');

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by module
        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        // Filter by owner
        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        // Filter active only
        if (!empty($filters['active'])) {
            $query->where('status', CampaignStatus::Active->value);
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->where('start_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('end_date', '<=', $filters['to_date']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Get page
        $page = $filters['page'] ?? 1;
        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function ($row) {
                $result = (array) $row;

                // Load owner relation
                if ($row->owner_id) {
                    $owner = DB::table('users')
                        ->select('id', 'name', 'email')
                        ->where('id', $row->owner_id)
                        ->first();
                    $result['owner'] = $owner ? (array) $owner : null;
                }

                // Load module relation
                if ($row->module_id) {
                    $module = DB::table('modules')
                        ->select('id', 'name')
                        ->where('id', $row->module_id)
                        ->first();
                    $result['module'] = $module ? (array) $module : null;
                }

                return $result;
            })
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function findActive(): array
    {
        return DB::table(self::TABLE)
            ->where('status', CampaignStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderBy('start_date')
            ->get()
            ->map(function ($row) {
                $result = (array) $row;

                // Load owner relation
                if ($row->owner_id) {
                    $owner = DB::table('users')
                        ->select('id', 'name', 'email')
                        ->where('id', $row->owner_id)
                        ->first();
                    $result['owner'] = $owner ? (array) $owner : null;
                }

                return $result;
            })
            ->all();
    }

    public function findCampaignWithMetrics(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Load owner relation
        if ($row->owner_id) {
            $owner = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('id', $row->owner_id)
                ->first();
            $result['owner'] = $owner ? (array) $owner : null;
        }

        // Load creator relation
        if ($row->created_by) {
            $creator = DB::table('users')
                ->select('id', 'name')
                ->where('id', $row->created_by)
                ->first();
            $result['creator'] = $creator ? (array) $creator : null;
        }

        // Load module relation
        if ($row->module_id) {
            $module = DB::table('modules')
                ->where('id', $row->module_id)
                ->first();
            $result['module'] = $module ? (array) $module : null;
        }

        // Load audiences
        $audiences = DB::table(self::TABLE_AUDIENCES)
            ->where('campaign_id', $id)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
        $result['audiences'] = $audiences;

        // Load assets
        $assets = DB::table(self::TABLE_ASSETS)
            ->where('campaign_id', $id)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
        $result['assets'] = $assets;

        // Load metrics (last 30 entries)
        $metrics = DB::table(self::TABLE_METRICS)
            ->where('campaign_id', $id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
        $result['metrics'] = $metrics;

        return $result;
    }

    public function getCampaignPerformance(int $campaignId): array
    {
        $sendsQuery = fn() => DB::table(self::TABLE_SENDS)
            ->where('campaign_id', $campaignId);

        $totalSends = $sendsQuery()->count();
        $delivered = $sendsQuery()->where('status', self::STATUS_DELIVERED)->count();
        $opened = $sendsQuery()->whereNotNull('opened_at')->count();
        $clicked = $sendsQuery()->whereNotNull('clicked_at')->count();
        $bounced = $sendsQuery()->where('status', self::STATUS_BOUNCED)->count();

        $conversions = DB::table(self::TABLE_CONVERSIONS)
            ->where('campaign_id', $campaignId)
            ->count();
        $totalRevenue = DB::table(self::TABLE_CONVERSIONS)
            ->where('campaign_id', $campaignId)
            ->sum('revenue') ?? 0;

        return [
            'total_sends' => $totalSends,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'conversions' => $conversions,
            'total_revenue' => $totalRevenue,
            'open_rate' => $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0,
            'click_rate' => $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0,
            'bounce_rate' => $totalSends > 0 ? round(($bounced / $totalSends) * 100, 2) : 0,
            'conversion_rate' => $clicked > 0 ? round(($conversions / $clicked) * 100, 2) : 0,
        ];
    }

    // =========================================================================
    // AUDIENCE METHODS
    // =========================================================================

    public function findAudiences(int $campaignId): array
    {
        return DB::table(self::TABLE_AUDIENCES)
            ->where('campaign_id', $campaignId)
            ->orderBy('name')
            ->get()
            ->map(function ($row) {
                $result = (array) $row;

                // Load module relation
                if ($row->module_id) {
                    $module = DB::table('modules')
                        ->select('id', 'name')
                        ->where('id', $row->module_id)
                        ->first();
                    $result['module'] = $module ? (array) $module : null;
                }

                return $result;
            })
            ->all();
    }

    public function findAudienceById(int $audienceId): ?array
    {
        $row = DB::table(self::TABLE_AUDIENCES)->where('id', $audienceId)->first();
        return $row ? (array) $row : null;
    }

    public function createAudience(array $data): array
    {
        $id = DB::table(self::TABLE_AUDIENCES)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE_AUDIENCES)->where('id', $id)->first();
    }

    public function updateAudience(int $audienceId, array $data): array
    {
        DB::table(self::TABLE_AUDIENCES)
            ->where('id', $audienceId)
            ->update(array_merge($data, ['updated_at' => now()]));

        return (array) DB::table(self::TABLE_AUDIENCES)->where('id', $audienceId)->first();
    }

    public function deleteAudience(int $audienceId): bool
    {
        $affected = DB::table(self::TABLE_AUDIENCES)
            ->where('id', $audienceId)
            ->delete();

        return $affected > 0;
    }

    public function refreshAudienceCount(int $audienceId): array
    {
        // Count members
        $count = DB::table(self::TABLE_AUDIENCE_MEMBERS)
            ->where('campaign_audience_id', $audienceId)
            ->count();

        // Update count
        DB::table(self::TABLE_AUDIENCES)
            ->where('id', $audienceId)
            ->update([
                'member_count' => $count,
                'updated_at' => now()
            ]);

        return (array) DB::table(self::TABLE_AUDIENCES)->where('id', $audienceId)->first();
    }

    public function addAudienceMembers(int $audienceId, array $recordIds): int
    {
        $added = 0;
        foreach ($recordIds as $recordId) {
            $exists = DB::table(self::TABLE_AUDIENCE_MEMBERS)
                ->where('campaign_audience_id', $audienceId)
                ->where('record_id', $recordId)
                ->exists();

            if (!$exists) {
                DB::table(self::TABLE_AUDIENCE_MEMBERS)->insert([
                    'campaign_audience_id' => $audienceId,
                    'record_id' => $recordId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $added++;
            }
        }

        return $added;
    }

    public function removeAudienceMembers(int $audienceId, array $recordIds): int
    {
        return DB::table(self::TABLE_AUDIENCE_MEMBERS)
            ->where('campaign_audience_id', $audienceId)
            ->whereIn('record_id', $recordIds)
            ->delete();
    }

    // =========================================================================
    // ASSET METHODS
    // =========================================================================

    public function findAssets(int $campaignId): array
    {
        return DB::table(self::TABLE_ASSETS)
            ->where('campaign_id', $campaignId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();
    }

    public function findAssetById(int $assetId): ?array
    {
        $row = DB::table(self::TABLE_ASSETS)->where('id', $assetId)->first();
        return $row ? (array) $row : null;
    }

    public function createAsset(array $data): array
    {
        $id = DB::table(self::TABLE_ASSETS)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE_ASSETS)->where('id', $id)->first();
    }

    public function updateAsset(int $assetId, array $data): array
    {
        DB::table(self::TABLE_ASSETS)
            ->where('id', $assetId)
            ->update(array_merge($data, ['updated_at' => now()]));

        return (array) DB::table(self::TABLE_ASSETS)->where('id', $assetId)->first();
    }

    public function deleteAsset(int $assetId): bool
    {
        $affected = DB::table(self::TABLE_ASSETS)
            ->where('id', $assetId)
            ->delete();

        return $affected > 0;
    }

    // =========================================================================
    // SEND METHODS
    // =========================================================================

    public function createSend(array $data): array
    {
        $id = DB::table(self::TABLE_SENDS)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE_SENDS)->where('id', $id)->first();
    }

    public function findPendingSends(int $limit = 100): array
    {
        return DB::table(self::TABLE_SENDS)
            ->where('status', self::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $result = (array) $row;

                // Load campaign relation
                if ($row->campaign_id) {
                    $campaign = DB::table(self::TABLE)
                        ->where('id', $row->campaign_id)
                        ->first();
                    $result['campaign'] = $campaign ? (array) $campaign : null;
                }

                // Load asset relation
                if ($row->asset_id) {
                    $asset = DB::table(self::TABLE_ASSETS)
                        ->where('id', $row->asset_id)
                        ->first();
                    $result['asset'] = $asset ? (array) $asset : null;
                }

                return $result;
            })
            ->all();
    }

    public function updateSend(int $sendId, array $data): array
    {
        DB::table(self::TABLE_SENDS)
            ->where('id', $sendId)
            ->update(array_merge($data, ['updated_at' => now()]));

        return (array) DB::table(self::TABLE_SENDS)->where('id', $sendId)->first();
    }

    public function findSendById(int $sendId): ?array
    {
        $row = DB::table(self::TABLE_SENDS)->where('id', $sendId)->first();
        return $row ? (array) $row : null;
    }

    public function sendExists(int $campaignId, int $recordId): bool
    {
        return DB::table(self::TABLE_SENDS)
            ->where('campaign_id', $campaignId)
            ->where('record_id', $recordId)
            ->exists();
    }

    // =========================================================================
    // CLICK METHODS
    // =========================================================================

    public function createClick(array $data): array
    {
        $id = DB::table(self::TABLE_CLICKS)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE_CLICKS)->where('id', $id)->first();
    }

    // =========================================================================
    // UNSUBSCRIBE METHODS
    // =========================================================================

    public function createUnsubscribe(array $data): array
    {
        $id = DB::table(self::TABLE_UNSUBSCRIBES)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE_UNSUBSCRIBES)->where('id', $id)->first();
    }

    // =========================================================================
    // CONVERSION METHODS
    // =========================================================================

    public function createConversion(array $data): array
    {
        $id = DB::table(self::TABLE_CONVERSIONS)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE_CONVERSIONS)->where('id', $id)->first();
    }

    // =========================================================================
    // METRIC METHODS
    // =========================================================================

    public function getOrCreateMetricForDate(int $campaignId, string $date): array
    {
        $metric = DB::table(self::TABLE_METRICS)
            ->where('campaign_id', $campaignId)
            ->where('date', $date)
            ->first();

        if ($metric) {
            return (array) $metric;
        }

        // Create new metric
        $id = DB::table(self::TABLE_METRICS)->insertGetId([
            'campaign_id' => $campaignId,
            'date' => $date,
            'sends' => 0,
            'delivered' => 0,
            'opens' => 0,
            'unique_opens' => 0,
            'clicks' => 0,
            'unique_clicks' => 0,
            'bounces' => 0,
            'unsubscribes' => 0,
            'conversions' => 0,
            'revenue' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table(self::TABLE_METRICS)->where('id', $id)->first();
    }

    public function incrementMetric(int $metricId, string $field, int $amount = 1): void
    {
        DB::table(self::TABLE_METRICS)
            ->where('id', $metricId)
            ->increment($field, $amount, ['updated_at' => now()]);
    }

    public function addRevenue(int $metricId, float $revenue): void
    {
        DB::table(self::TABLE_METRICS)
            ->where('id', $metricId)
            ->increment('revenue', $revenue, ['updated_at' => now()]);
    }

    public function incrementCampaignSpent(int $campaignId, float $amount): void
    {
        DB::table(self::TABLE)
            ->where('id', $campaignId)
            ->increment('spent', $amount, ['updated_at' => now()]);
    }

    public function findMetrics(int $campaignId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table(self::TABLE_METRICS)
            ->where('campaign_id', $campaignId);

        if ($fromDate) {
            $query->where('date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('date', '<=', $toDate);
        }

        return $query->orderBy('date')
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();
    }

    public function getAggregateAnalytics(array $filters = []): array
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('start_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('end_date', '<=', $filters['to_date']);
        }

        $campaignIds = $query->pluck('id')->all();

        $metricsQuery = fn() => DB::table(self::TABLE_METRICS)
            ->whereIn('campaign_id', $campaignIds);

        $byType = DB::table(self::TABLE)
            ->selectRaw('type, COUNT(*) as count')
            ->whereIn('id', $campaignIds)
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $byStatus = DB::table(self::TABLE)
            ->selectRaw('status, COUNT(*) as count')
            ->whereIn('id', $campaignIds)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_campaigns' => count($campaignIds),
            'total_sends' => $metricsQuery()->sum('sends') ?? 0,
            'total_delivered' => $metricsQuery()->sum('delivered') ?? 0,
            'total_opens' => $metricsQuery()->sum('unique_opens') ?? 0,
            'total_clicks' => $metricsQuery()->sum('unique_clicks') ?? 0,
            'total_conversions' => $metricsQuery()->sum('conversions') ?? 0,
            'total_revenue' => $metricsQuery()->sum('revenue') ?? 0,
            'by_type' => $byType,
            'by_status' => $byStatus,
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public function countAudiences(int $campaignId): int
    {
        return DB::table(self::TABLE_AUDIENCES)
            ->where('campaign_id', $campaignId)
            ->count();
    }

    public function countAssets(int $campaignId): int
    {
        return DB::table(self::TABLE_ASSETS)
            ->where('campaign_id', $campaignId)
            ->count();
    }

    public function canBeStarted(int $campaignId): bool
    {
        $entity = $this->findById($campaignId);
        if (!$entity) {
            throw new \RuntimeException("Campaign not found: {$campaignId}");
        }
        return $entity->canBeStarted();
    }

    public function canBePaused(int $campaignId): bool
    {
        $entity = $this->findById($campaignId);
        if (!$entity) {
            throw new \RuntimeException("Campaign not found: {$campaignId}");
        }
        return $entity->canBePaused();
    }

    public function isDraft(int $campaignId): bool
    {
        $entity = $this->findById($campaignId);
        if (!$entity) {
            throw new \RuntimeException("Campaign not found: {$campaignId}");
        }
        return $entity->isDraft();
    }

    public function isActive(int $campaignId): bool
    {
        $entity = $this->findById($campaignId);
        if (!$entity) {
            throw new \RuntimeException("Campaign not found: {$campaignId}");
        }
        return $entity->isActive();
    }

    public function getMatchingRecords(int $audienceId): array
    {
        // This requires access to the audience's filter criteria and the module's records
        // For now, return members that were explicitly added
        return DB::table(self::TABLE_AUDIENCE_MEMBERS)
            ->where('campaign_audience_id', $audienceId)
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): CampaignEntity
    {
        return CampaignEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            description: $row->description,
            type: CampaignType::from($row->type),
            status: CampaignStatus::from($row->status),
            moduleId: $row->module_id ? (int) $row->module_id : null,
            startDate: $row->start_date ? new DateTimeImmutable($row->start_date) : null,
            endDate: $row->end_date ? new DateTimeImmutable($row->end_date) : null,
            budget: $row->budget,
            spent: $row->spent,
            settings: $row->settings ? json_decode($row->settings, true) : [],
            goals: $row->goals ? json_decode($row->goals, true) : [],
            createdBy: $row->created_by ? (int) $row->created_by : null,
            ownerId: $row->owner_id ? (int) $row->owner_id : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(CampaignEntity $campaign): array
    {
        return [
            'name' => $campaign->getName(),
            'description' => $campaign->getDescription(),
            'type' => $campaign->getType()->value,
            'status' => $campaign->getStatus()->value,
            'module_id' => $campaign->getModuleId(),
            'start_date' => $campaign->getStartDate()?->format('Y-m-d H:i:s'),
            'end_date' => $campaign->getEndDate()?->format('Y-m-d H:i:s'),
            'budget' => $campaign->getBudget(),
            'spent' => $campaign->getSpent(),
            'settings' => json_encode($campaign->getSettings()),
            'goals' => json_encode($campaign->getGoals()),
            'created_by' => $campaign->getCreatedBy(),
            'owner_id' => $campaign->getOwnerId(),
        ];
    }
}
