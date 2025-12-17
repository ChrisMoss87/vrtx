<?php

declare(strict_types=1);

namespace App\Application\Services\Campaign;

use App\Domain\Campaign\Repositories\CampaignRepositoryInterface;
use App\Models\Campaign;
use App\Models\CampaignAsset;
use App\Models\CampaignAudience;
use App\Models\CampaignAudienceMember;
use App\Models\CampaignClick;
use App\Models\CampaignConversion;
use App\Models\CampaignMetric;
use App\Models\CampaignSend;
use App\Models\CampaignUnsubscribe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CampaignApplicationService
{
    public function __construct(
        private CampaignRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - CAMPAIGNS
    // =========================================================================

    /**
     * List campaigns with filtering and pagination.
     */
    public function listCampaigns(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Campaign::query()
            ->with(['owner:id,name,email', 'module:id,name']);

        // Filter by type
        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
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
            $query->active();
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

        return $query->paginate($perPage);
    }

    /**
     * Get a single campaign by ID.
     */
    public function getCampaign(int $id): ?Campaign
    {
        return Campaign::with(['owner:id,name,email', 'creator:id,name', 'module', 'audiences', 'assets'])->find($id);
    }

    /**
     * Get campaign with full details including metrics.
     */
    public function getCampaignWithMetrics(int $id): ?Campaign
    {
        $campaign = $this->getCampaign($id);

        if (!$campaign) {
            return null;
        }

        // Load aggregate metrics
        $campaign->load(['metrics' => function ($q) {
            $q->orderBy('date', 'desc')->limit(30);
        }]);

        return $campaign;
    }

    /**
     * Get active campaigns.
     */
    public function getActiveCampaigns(): Collection
    {
        return Campaign::active()
            ->with(['owner:id,name,email'])
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Get campaign performance summary.
     */
    public function getCampaignPerformance(int $campaignId): array
    {
        $campaign = Campaign::findOrFail($campaignId);

        $sends = $campaign->sends();
        $totalSends = $sends->count();
        $delivered = (clone $sends)->where('status', CampaignSend::STATUS_DELIVERED)->count();
        $opened = (clone $sends)->whereNotNull('opened_at')->count();
        $clicked = (clone $sends)->whereNotNull('clicked_at')->count();
        $bounced = (clone $sends)->where('status', CampaignSend::STATUS_BOUNCED)->count();

        $conversions = $campaign->conversions()->count();
        $totalRevenue = $campaign->conversions()->sum('revenue') ?? 0;

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
    // COMMAND USE CASES - CAMPAIGNS
    // =========================================================================

    /**
     * Create a new campaign.
     */
    public function createCampaign(array $data): Campaign
    {
        return Campaign::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? Campaign::TYPE_EMAIL,
            'status' => Campaign::STATUS_DRAFT,
            'module_id' => $data['module_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'budget' => $data['budget'] ?? null,
            'settings' => $data['settings'] ?? [],
            'goals' => $data['goals'] ?? [],
            'created_by' => Auth::id(),
            'owner_id' => $data['owner_id'] ?? Auth::id(),
        ]);
    }

    /**
     * Update a campaign.
     */
    public function updateCampaign(int $id, array $data): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        $campaign->update([
            'name' => $data['name'] ?? $campaign->name,
            'description' => $data['description'] ?? $campaign->description,
            'type' => $data['type'] ?? $campaign->type,
            'module_id' => $data['module_id'] ?? $campaign->module_id,
            'start_date' => $data['start_date'] ?? $campaign->start_date,
            'end_date' => $data['end_date'] ?? $campaign->end_date,
            'budget' => $data['budget'] ?? $campaign->budget,
            'settings' => array_merge($campaign->settings ?? [], $data['settings'] ?? []),
            'goals' => $data['goals'] ?? $campaign->goals,
            'owner_id' => $data['owner_id'] ?? $campaign->owner_id,
        ]);

        return $campaign->fresh();
    }

    /**
     * Delete a campaign.
     */
    public function deleteCampaign(int $id): bool
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->isActive()) {
            throw new \InvalidArgumentException('Cannot delete an active campaign');
        }

        return $campaign->delete();
    }

    /**
     * Duplicate a campaign.
     */
    public function duplicateCampaign(int $id): Campaign
    {
        $original = Campaign::with(['audiences', 'assets'])->findOrFail($id);

        return DB::transaction(function () use ($original) {
            // Create the campaign copy
            $newCampaign = Campaign::create([
                'name' => $original->name . ' (Copy)',
                'description' => $original->description,
                'type' => $original->type,
                'status' => Campaign::STATUS_DRAFT,
                'module_id' => $original->module_id,
                'budget' => $original->budget,
                'settings' => $original->settings,
                'goals' => $original->goals,
                'created_by' => Auth::id(),
                'owner_id' => Auth::id(),
            ]);

            // Copy audiences
            foreach ($original->audiences as $audience) {
                CampaignAudience::create([
                    'campaign_id' => $newCampaign->id,
                    'name' => $audience->name,
                    'description' => $audience->description,
                    'module_id' => $audience->module_id,
                    'segment_rules' => $audience->segment_rules,
                    'is_dynamic' => $audience->is_dynamic,
                ]);
            }

            // Copy assets
            foreach ($original->assets as $asset) {
                CampaignAsset::create([
                    'campaign_id' => $newCampaign->id,
                    'name' => $asset->name,
                    'type' => $asset->type,
                    'content' => $asset->content,
                    'settings' => $asset->settings,
                ]);
            }

            return $newCampaign->load(['audiences', 'assets']);
        });
    }

    // =========================================================================
    // LIFECYCLE USE CASES
    // =========================================================================

    /**
     * Start/activate a campaign.
     */
    public function startCampaign(int $id): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        if (!$campaign->canBeStarted()) {
            throw new \InvalidArgumentException('Campaign cannot be started in its current state');
        }

        // Validate campaign has required components
        if ($campaign->audiences()->count() === 0) {
            throw new \InvalidArgumentException('Campaign must have at least one audience');
        }

        if ($campaign->assets()->count() === 0) {
            throw new \InvalidArgumentException('Campaign must have at least one asset');
        }

        $campaign->update([
            'status' => Campaign::STATUS_ACTIVE,
            'start_date' => $campaign->start_date ?? now(),
        ]);

        return $campaign->fresh();
    }

    /**
     * Pause a campaign.
     */
    public function pauseCampaign(int $id): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        if (!$campaign->canBePaused()) {
            throw new \InvalidArgumentException('Campaign cannot be paused in its current state');
        }

        $campaign->update(['status' => Campaign::STATUS_PAUSED]);

        return $campaign->fresh();
    }

    /**
     * Resume a paused campaign.
     */
    public function resumeCampaign(int $id): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status !== Campaign::STATUS_PAUSED) {
            throw new \InvalidArgumentException('Only paused campaigns can be resumed');
        }

        $campaign->update(['status' => Campaign::STATUS_ACTIVE]);

        return $campaign->fresh();
    }

    /**
     * Complete a campaign.
     */
    public function completeCampaign(int $id): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        $campaign->update([
            'status' => Campaign::STATUS_COMPLETED,
            'end_date' => now(),
        ]);

        return $campaign->fresh();
    }

    /**
     * Cancel a campaign.
     */
    public function cancelCampaign(int $id): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status === Campaign::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Cannot cancel a completed campaign');
        }

        $campaign->update([
            'status' => Campaign::STATUS_CANCELLED,
            'end_date' => now(),
        ]);

        return $campaign->fresh();
    }

    /**
     * Schedule a campaign.
     */
    public function scheduleCampaign(int $id, \DateTimeInterface $startDate, ?\DateTimeInterface $endDate = null): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        if (!$campaign->isDraft()) {
            throw new \InvalidArgumentException('Only draft campaigns can be scheduled');
        }

        $campaign->update([
            'status' => Campaign::STATUS_SCHEDULED,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $campaign->fresh();
    }

    // =========================================================================
    // AUDIENCE USE CASES
    // =========================================================================

    /**
     * List audiences for a campaign.
     */
    public function listAudiences(int $campaignId): Collection
    {
        return CampaignAudience::where('campaign_id', $campaignId)
            ->with('module:id,name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create an audience for a campaign.
     */
    public function createAudience(int $campaignId, array $data): CampaignAudience
    {
        $campaign = Campaign::findOrFail($campaignId);

        $audience = CampaignAudience::create([
            'campaign_id' => $campaignId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'module_id' => $data['module_id'],
            'segment_rules' => $data['segment_rules'] ?? [],
            'is_dynamic' => $data['is_dynamic'] ?? true,
        ]);

        // Refresh count
        $audience->refreshCount();

        return $audience->fresh();
    }

    /**
     * Update an audience.
     */
    public function updateAudience(int $audienceId, array $data): CampaignAudience
    {
        $audience = CampaignAudience::findOrFail($audienceId);

        $audience->update([
            'name' => $data['name'] ?? $audience->name,
            'description' => $data['description'] ?? $audience->description,
            'module_id' => $data['module_id'] ?? $audience->module_id,
            'segment_rules' => $data['segment_rules'] ?? $audience->segment_rules,
            'is_dynamic' => $data['is_dynamic'] ?? $audience->is_dynamic,
        ]);

        // Refresh count if rules changed
        if (isset($data['segment_rules'])) {
            $audience->refreshCount();
        }

        return $audience->fresh();
    }

    /**
     * Delete an audience.
     */
    public function deleteAudience(int $audienceId): bool
    {
        $audience = CampaignAudience::findOrFail($audienceId);
        return $audience->delete();
    }

    /**
     * Refresh audience count.
     */
    public function refreshAudienceCount(int $audienceId): CampaignAudience
    {
        $audience = CampaignAudience::findOrFail($audienceId);
        $audience->refreshCount();
        return $audience->fresh();
    }

    /**
     * Add members to a static audience.
     */
    public function addAudienceMembers(int $audienceId, array $recordIds): int
    {
        $audience = CampaignAudience::findOrFail($audienceId);

        if ($audience->is_dynamic) {
            throw new \InvalidArgumentException('Cannot manually add members to a dynamic audience');
        }

        $added = 0;
        foreach ($recordIds as $recordId) {
            $created = CampaignAudienceMember::firstOrCreate([
                'campaign_audience_id' => $audienceId,
                'record_id' => $recordId,
            ]);

            if ($created->wasRecentlyCreated) {
                $added++;
            }
        }

        $audience->refreshCount();

        return $added;
    }

    /**
     * Remove members from a static audience.
     */
    public function removeAudienceMembers(int $audienceId, array $recordIds): int
    {
        $audience = CampaignAudience::findOrFail($audienceId);

        if ($audience->is_dynamic) {
            throw new \InvalidArgumentException('Cannot manually remove members from a dynamic audience');
        }

        $removed = CampaignAudienceMember::where('campaign_audience_id', $audienceId)
            ->whereIn('record_id', $recordIds)
            ->delete();

        $audience->refreshCount();

        return $removed;
    }

    // =========================================================================
    // ASSET USE CASES
    // =========================================================================

    /**
     * List assets for a campaign.
     */
    public function listAssets(int $campaignId): Collection
    {
        return CampaignAsset::where('campaign_id', $campaignId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Create a campaign asset.
     */
    public function createAsset(int $campaignId, array $data): CampaignAsset
    {
        return CampaignAsset::create([
            'campaign_id' => $campaignId,
            'name' => $data['name'],
            'type' => $data['type'],
            'content' => $data['content'] ?? null,
            'settings' => $data['settings'] ?? [],
        ]);
    }

    /**
     * Update a campaign asset.
     */
    public function updateAsset(int $assetId, array $data): CampaignAsset
    {
        $asset = CampaignAsset::findOrFail($assetId);

        $asset->update([
            'name' => $data['name'] ?? $asset->name,
            'type' => $data['type'] ?? $asset->type,
            'content' => $data['content'] ?? $asset->content,
            'settings' => array_merge($asset->settings ?? [], $data['settings'] ?? []),
        ]);

        return $asset->fresh();
    }

    /**
     * Delete a campaign asset.
     */
    public function deleteAsset(int $assetId): bool
    {
        $asset = CampaignAsset::findOrFail($assetId);
        return $asset->delete();
    }

    // =========================================================================
    // SEND USE CASES
    // =========================================================================

    /**
     * Queue sends for a campaign.
     */
    public function queueSends(int $campaignId, ?\DateTimeInterface $scheduledAt = null): int
    {
        $campaign = Campaign::with('audiences')->findOrFail($campaignId);

        if (!$campaign->isActive()) {
            throw new \InvalidArgumentException('Campaign must be active to queue sends');
        }

        $queued = 0;

        foreach ($campaign->audiences as $audience) {
            $records = $audience->getMatchingRecords()->get();

            foreach ($records as $record) {
                // Check if not already sent
                $exists = CampaignSend::where('campaign_id', $campaignId)
                    ->where('record_id', $record->id)
                    ->exists();

                if (!$exists) {
                    CampaignSend::create([
                        'campaign_id' => $campaignId,
                        'record_id' => $record->id,
                        'channel' => CampaignSend::CHANNEL_EMAIL,
                        'recipient' => $record->data['email'] ?? null,
                        'status' => CampaignSend::STATUS_PENDING,
                        'scheduled_at' => $scheduledAt ?? now(),
                    ]);
                    $queued++;
                }
            }
        }

        return $queued;
    }

    /**
     * Get pending sends ready for dispatch.
     */
    public function getPendingSends(int $limit = 100): Collection
    {
        return CampaignSend::pending()
            ->scheduledBefore(now())
            ->with(['campaign', 'record', 'asset'])
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark a send as sent.
     */
    public function markSent(int $sendId): CampaignSend
    {
        $send = CampaignSend::findOrFail($sendId);
        $send->markSent();

        // Update daily metrics
        $this->incrementDailyMetric($send->campaign_id, 'sends');

        return $send->fresh();
    }

    /**
     * Mark a send as delivered.
     */
    public function markDelivered(int $sendId): CampaignSend
    {
        $send = CampaignSend::findOrFail($sendId);
        $send->markDelivered();

        $this->incrementDailyMetric($send->campaign_id, 'delivered');

        return $send->fresh();
    }

    /**
     * Track an email open.
     */
    public function trackOpen(int $sendId): CampaignSend
    {
        $send = CampaignSend::findOrFail($sendId);
        $wasFirstOpen = $send->opened_at === null;

        $send->markOpened();

        $this->incrementDailyMetric($send->campaign_id, 'opens');
        if ($wasFirstOpen) {
            $this->incrementDailyMetric($send->campaign_id, 'unique_opens');
        }

        return $send->fresh();
    }

    /**
     * Track a click.
     */
    public function trackClick(int $sendId, string $url): CampaignSend
    {
        $send = CampaignSend::findOrFail($sendId);
        $wasFirstClick = $send->clicked_at === null;

        $send->markClicked();

        // Log the click
        CampaignClick::create([
            'campaign_send_id' => $sendId,
            'url' => $url,
            'clicked_at' => now(),
        ]);

        $this->incrementDailyMetric($send->campaign_id, 'clicks');
        if ($wasFirstClick) {
            $this->incrementDailyMetric($send->campaign_id, 'unique_clicks');
        }

        return $send->fresh();
    }

    /**
     * Mark a send as bounced.
     */
    public function markBounced(int $sendId, string $reason = null): CampaignSend
    {
        $send = CampaignSend::findOrFail($sendId);
        $send->markBounced($reason);

        $this->incrementDailyMetric($send->campaign_id, 'bounces');

        return $send->fresh();
    }

    /**
     * Track an unsubscribe.
     */
    public function trackUnsubscribe(int $sendId, ?string $reason = null): void
    {
        $send = CampaignSend::findOrFail($sendId);

        CampaignUnsubscribe::create([
            'campaign_id' => $send->campaign_id,
            'record_id' => $send->record_id,
            'email' => $send->recipient,
            'reason' => $reason,
        ]);

        $this->incrementDailyMetric($send->campaign_id, 'unsubscribes');
    }

    /**
     * Track a conversion.
     */
    public function trackConversion(int $campaignId, int $recordId, string $type, float $revenue = 0, ?array $metadata = null): CampaignConversion
    {
        $conversion = CampaignConversion::create([
            'campaign_id' => $campaignId,
            'record_id' => $recordId,
            'type' => $type,
            'revenue' => $revenue,
            'metadata' => $metadata,
        ]);

        $this->incrementDailyMetric($campaignId, 'conversions');

        if ($revenue > 0) {
            $metric = CampaignMetric::getOrCreateForDate($campaignId, now()->toDateString());
            $metric->addRevenue($revenue);

            // Update campaign spent
            Campaign::where('id', $campaignId)->increment('spent', $revenue);
        }

        return $conversion;
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get campaign analytics.
     */
    public function getAnalytics(int $campaignId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $campaign = Campaign::findOrFail($campaignId);

        $query = CampaignMetric::where('campaign_id', $campaignId);

        if ($fromDate) {
            $query->where('date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('date', '<=', $toDate);
        }

        $metrics = $query->orderBy('date')->get();

        $totals = [
            'sends' => $metrics->sum('sends'),
            'delivered' => $metrics->sum('delivered'),
            'opens' => $metrics->sum('opens'),
            'unique_opens' => $metrics->sum('unique_opens'),
            'clicks' => $metrics->sum('clicks'),
            'unique_clicks' => $metrics->sum('unique_clicks'),
            'bounces' => $metrics->sum('bounces'),
            'unsubscribes' => $metrics->sum('unsubscribes'),
            'conversions' => $metrics->sum('conversions'),
            'revenue' => $metrics->sum('revenue'),
        ];

        $totals['open_rate'] = $totals['delivered'] > 0 ? round(($totals['unique_opens'] / $totals['delivered']) * 100, 2) : 0;
        $totals['click_rate'] = $totals['delivered'] > 0 ? round(($totals['unique_clicks'] / $totals['delivered']) * 100, 2) : 0;
        $totals['bounce_rate'] = $totals['sends'] > 0 ? round(($totals['bounces'] / $totals['sends']) * 100, 2) : 0;
        $totals['conversion_rate'] = $totals['unique_clicks'] > 0 ? round(($totals['conversions'] / $totals['unique_clicks']) * 100, 2) : 0;

        return [
            'campaign' => $campaign,
            'totals' => $totals,
            'daily_metrics' => $metrics,
            'budget' => $campaign->budget,
            'spent' => $campaign->spent,
            'roi' => $campaign->spent > 0 ? round((($totals['revenue'] - $campaign->spent) / $campaign->spent) * 100, 2) : 0,
        ];
    }

    /**
     * Get aggregate analytics across all campaigns.
     */
    public function getAggregateAnalytics(array $filters = []): array
    {
        $query = Campaign::query();

        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('start_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('end_date', '<=', $filters['to_date']);
        }

        $campaignIds = $query->pluck('id');

        $metricsQuery = CampaignMetric::whereIn('campaign_id', $campaignIds);

        return [
            'total_campaigns' => $campaignIds->count(),
            'total_sends' => $metricsQuery->sum('sends'),
            'total_delivered' => (clone $metricsQuery)->sum('delivered'),
            'total_opens' => (clone $metricsQuery)->sum('unique_opens'),
            'total_clicks' => (clone $metricsQuery)->sum('unique_clicks'),
            'total_conversions' => (clone $metricsQuery)->sum('conversions'),
            'total_revenue' => (clone $metricsQuery)->sum('revenue'),
            'by_type' => $query->selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type'),
            'by_status' => Campaign::whereIn('id', $campaignIds)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Increment a daily metric.
     */
    private function incrementDailyMetric(int $campaignId, string $metric, int $amount = 1): void
    {
        $dailyMetric = CampaignMetric::getOrCreateForDate($campaignId, now()->toDateString());
        $dailyMetric->incrementMetric($metric, $amount);
    }
}
