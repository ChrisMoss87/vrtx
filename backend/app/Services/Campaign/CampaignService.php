<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Models\Campaign;
use App\Models\CampaignAudience;
use App\Models\CampaignAsset;
use App\Models\CampaignSend;
use App\Models\CampaignMetric;
use App\Models\CampaignUnsubscribe;
use App\Models\ModuleRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    /**
     * Get paginated campaigns with filters
     */
    public function getCampaigns(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Campaign::with(['module', 'owner', 'creator'])
            ->withCount(['audiences', 'sends']);

        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'ILIKE', "%{$filters['search']}%");
        }

        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new campaign
     */
    public function createCampaign(array $data, int $userId): Campaign
    {
        return DB::transaction(function () use ($data, $userId) {
            $campaign = Campaign::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'status' => Campaign::STATUS_DRAFT,
                'module_id' => $data['module_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'budget' => $data['budget'] ?? null,
                'settings' => $data['settings'] ?? [],
                'goals' => $data['goals'] ?? [],
                'created_by' => $userId,
                'owner_id' => $data['owner_id'] ?? $userId,
            ]);

            return $campaign;
        });
    }

    /**
     * Update a campaign
     */
    public function updateCampaign(Campaign $campaign, array $data): Campaign
    {
        $campaign->update([
            'name' => $data['name'] ?? $campaign->name,
            'description' => $data['description'] ?? $campaign->description,
            'type' => $data['type'] ?? $campaign->type,
            'module_id' => $data['module_id'] ?? $campaign->module_id,
            'start_date' => $data['start_date'] ?? $campaign->start_date,
            'end_date' => $data['end_date'] ?? $campaign->end_date,
            'budget' => $data['budget'] ?? $campaign->budget,
            'settings' => $data['settings'] ?? $campaign->settings,
            'goals' => $data['goals'] ?? $campaign->goals,
            'owner_id' => $data['owner_id'] ?? $campaign->owner_id,
        ]);

        return $campaign->fresh();
    }

    /**
     * Duplicate a campaign
     */
    public function duplicateCampaign(Campaign $campaign, int $userId): Campaign
    {
        return DB::transaction(function () use ($campaign, $userId) {
            $newCampaign = $campaign->replicate(['id', 'created_at', 'updated_at']);
            $newCampaign->name = $campaign->name . ' (Copy)';
            $newCampaign->status = Campaign::STATUS_DRAFT;
            $newCampaign->created_by = $userId;
            $newCampaign->spent = 0;
            $newCampaign->save();

            // Duplicate audiences
            foreach ($campaign->audiences as $audience) {
                $newAudience = $audience->replicate(['id', 'created_at', 'updated_at']);
                $newAudience->campaign_id = $newCampaign->id;
                $newAudience->save();
            }

            // Duplicate assets
            foreach ($campaign->assets as $asset) {
                $newAsset = $asset->replicate(['id', 'created_at', 'updated_at']);
                $newAsset->campaign_id = $newCampaign->id;
                $newAsset->save();
            }

            return $newCampaign->load(['audiences', 'assets']);
        });
    }

    /**
     * Add an audience to a campaign
     */
    public function addAudience(Campaign $campaign, array $data): CampaignAudience
    {
        $audience = CampaignAudience::create([
            'campaign_id' => $campaign->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'module_id' => $data['module_id'],
            'segment_rules' => $data['segment_rules'] ?? [],
            'is_dynamic' => $data['is_dynamic'] ?? true,
        ]);

        $audience->refreshCount();

        return $audience;
    }

    /**
     * Update an audience
     */
    public function updateAudience(CampaignAudience $audience, array $data): CampaignAudience
    {
        $audience->update([
            'name' => $data['name'] ?? $audience->name,
            'description' => $data['description'] ?? $audience->description,
            'segment_rules' => $data['segment_rules'] ?? $audience->segment_rules,
            'is_dynamic' => $data['is_dynamic'] ?? $audience->is_dynamic,
        ]);

        $audience->refreshCount();

        return $audience->fresh();
    }

    /**
     * Preview audience - get sample records
     */
    public function previewAudience(CampaignAudience $audience, int $limit = 10): Collection
    {
        return $audience->getMatchingRecords()->limit($limit)->get();
    }

    /**
     * Add an asset to a campaign
     */
    public function addAsset(Campaign $campaign, array $data): CampaignAsset
    {
        return CampaignAsset::create([
            'campaign_id' => $campaign->id,
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Update an asset
     */
    public function updateAsset(CampaignAsset $asset, array $data): CampaignAsset
    {
        // Increment version if content changed
        $version = $asset->version;
        if (isset($data['content']) && $data['content'] !== $asset->content) {
            $version++;
        }

        $asset->update([
            'name' => $data['name'] ?? $asset->name,
            'description' => $data['description'] ?? $asset->description,
            'subject' => $data['subject'] ?? $asset->subject,
            'content' => $data['content'] ?? $asset->content,
            'metadata' => $data['metadata'] ?? $asset->metadata,
            'version' => $version,
        ]);

        return $asset->fresh();
    }

    /**
     * Start a campaign
     */
    public function startCampaign(Campaign $campaign): Campaign
    {
        if (!$campaign->canBeStarted()) {
            throw new \Exception('Campaign cannot be started in its current state.');
        }

        if ($campaign->audiences()->count() === 0) {
            throw new \Exception('Campaign must have at least one audience.');
        }

        if ($campaign->assets()->where('type', 'email')->count() === 0) {
            throw new \Exception('Campaign must have at least one email asset.');
        }

        $campaign->update([
            'status' => Campaign::STATUS_ACTIVE,
            'start_date' => $campaign->start_date ?? now(),
        ]);

        // Queue sends for all audience members
        $this->queueCampaignSends($campaign);

        return $campaign->fresh();
    }

    /**
     * Pause a campaign
     */
    public function pauseCampaign(Campaign $campaign): Campaign
    {
        if (!$campaign->canBePaused()) {
            throw new \Exception('Campaign cannot be paused in its current state.');
        }

        $campaign->update(['status' => Campaign::STATUS_PAUSED]);

        return $campaign->fresh();
    }

    /**
     * Complete a campaign
     */
    public function completeCampaign(Campaign $campaign): Campaign
    {
        $campaign->update([
            'status' => Campaign::STATUS_COMPLETED,
            'end_date' => now(),
        ]);

        return $campaign->fresh();
    }

    /**
     * Cancel a campaign
     */
    public function cancelCampaign(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => Campaign::STATUS_CANCELLED]);

        // Cancel pending sends
        $campaign->sends()->pending()->update(['status' => CampaignSend::STATUS_FAILED, 'error_message' => 'Campaign cancelled']);

        return $campaign->fresh();
    }

    /**
     * Queue sends for a campaign
     */
    protected function queueCampaignSends(Campaign $campaign): void
    {
        $emailAsset = $campaign->assets()->where('type', 'email')->where('is_active', true)->first();

        if (!$emailAsset) {
            return;
        }

        foreach ($campaign->audiences as $audience) {
            $records = $audience->getMatchingRecords()->get();

            foreach ($records as $record) {
                // Get email field value
                $email = $record->data['email'] ?? null;

                if (!$email || CampaignUnsubscribe::isUnsubscribed($email)) {
                    continue;
                }

                // Check if already queued
                $existingSend = CampaignSend::where('campaign_id', $campaign->id)
                    ->where('record_id', $record->id)
                    ->first();

                if ($existingSend) {
                    continue;
                }

                CampaignSend::create([
                    'campaign_id' => $campaign->id,
                    'campaign_asset_id' => $emailAsset->id,
                    'record_id' => $record->id,
                    'channel' => CampaignSend::CHANNEL_EMAIL,
                    'recipient' => $email,
                    'status' => CampaignSend::STATUS_PENDING,
                    'scheduled_at' => now(),
                ]);
            }
        }
    }

    /**
     * Get campaign analytics
     */
    public function getCampaignAnalytics(Campaign $campaign): array
    {
        $sends = $campaign->sends();

        $totalSends = $sends->count();
        $delivered = $sends->where('status', '!=', CampaignSend::STATUS_BOUNCED)
            ->where('status', '!=', CampaignSend::STATUS_FAILED)
            ->whereNotNull('sent_at')
            ->count();
        $opened = $sends->whereNotNull('opened_at')->count();
        $clicked = $sends->whereNotNull('clicked_at')->count();
        $bounced = $sends->where('status', CampaignSend::STATUS_BOUNCED)->count();

        $conversions = $campaign->conversions()->count();
        $revenue = $campaign->conversions()->sum('value');

        return [
            'total_sends' => $totalSends,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'conversions' => $conversions,
            'revenue' => (float) $revenue,
            'open_rate' => $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0,
            'click_rate' => $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0,
            'bounce_rate' => $totalSends > 0 ? round(($bounced / $totalSends) * 100, 2) : 0,
            'conversion_rate' => $clicked > 0 ? round(($conversions / $clicked) * 100, 2) : 0,
        ];
    }

    /**
     * Get campaign metrics over time
     */
    public function getCampaignMetricsOverTime(Campaign $campaign, string $startDate = null, string $endDate = null): Collection
    {
        $query = CampaignMetric::where('campaign_id', $campaign->id)
            ->orderBy('date');

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Record an open event
     */
    public function recordOpen(CampaignSend $send): void
    {
        $send->markOpened();

        $metric = CampaignMetric::getOrCreateForDate($send->campaign_id, now()->toDateString());
        $metric->incrementMetric('opens');

        // Check if unique open
        $previousOpens = CampaignSend::where('campaign_id', $send->campaign_id)
            ->where('record_id', $send->record_id)
            ->whereNotNull('opened_at')
            ->where('id', '!=', $send->id)
            ->count();

        if ($previousOpens === 0) {
            $metric->incrementMetric('unique_opens');
        }
    }

    /**
     * Record a click event
     */
    public function recordClick(CampaignSend $send, string $url, ?string $linkName = null): void
    {
        $send->markClicked();

        $send->clicks()->create([
            'url' => $url,
            'link_name' => $linkName,
            'clicked_at' => now(),
        ]);

        $metric = CampaignMetric::getOrCreateForDate($send->campaign_id, now()->toDateString());
        $metric->incrementMetric('clicks');

        // Check if unique click
        $previousClicks = CampaignSend::where('campaign_id', $send->campaign_id)
            ->where('record_id', $send->record_id)
            ->whereNotNull('clicked_at')
            ->where('id', '!=', $send->id)
            ->count();

        if ($previousClicks === 0) {
            $metric->incrementMetric('unique_clicks');
        }
    }

    /**
     * Get top performing links for a campaign
     */
    public function getTopLinks(Campaign $campaign, int $limit = 10): Collection
    {
        return DB::table('campaign_clicks')
            ->join('campaign_sends', 'campaign_clicks.campaign_send_id', '=', 'campaign_sends.id')
            ->where('campaign_sends.campaign_id', $campaign->id)
            ->select('campaign_clicks.url', 'campaign_clicks.link_name', DB::raw('COUNT(*) as click_count'))
            ->groupBy('campaign_clicks.url', 'campaign_clicks.link_name')
            ->orderByDesc('click_count')
            ->limit($limit)
            ->get();
    }
}
