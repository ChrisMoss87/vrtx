<?php

declare(strict_types=1);

namespace App\Application\Services\Campaign;

use App\Domain\Campaign\Repositories\CampaignRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;

class CampaignApplicationService
{
    public function __construct(
        private CampaignRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - CAMPAIGNS
    // =========================================================================

    /**
     * List campaigns with filtering and pagination.
     */
    public function listCampaigns(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->findWithFilters($filters, $perPage);
    }

    /**
     * Get a single campaign by ID.
     */
    public function getCampaign(int $id): ?array
    {
        return $this->repository->findByIdWithRelations($id);
    }

    /**
     * Get campaign with full details including metrics.
     */
    public function getCampaignWithMetrics(int $id): ?array
    {
        return $this->repository->findCampaignWithMetrics($id);
    }

    /**
     * Get active campaigns.
     */
    public function getActiveCampaigns(): array
    {
        return $this->repository->findActive();
    }

    /**
     * Get campaign performance summary.
     */
    public function getCampaignPerformance(int $campaignId): array
    {
        return $this->repository->getCampaignPerformance($campaignId);
    }

    // =========================================================================
    // COMMAND USE CASES - CAMPAIGNS
    // =========================================================================

    /**
     * Create a new campaign.
     */
    public function createCampaign(array $data): array
    {
        return $this->repository->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'email',
            'status' => 'draft',
            'module_id' => $data['module_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'budget' => $data['budget'] ?? null,
            'settings' => $data['settings'] ?? [],
            'goals' => $data['goals'] ?? [],
            'created_by' => $this->authContext->userId(),
            'owner_id' => $data['owner_id'] ?? $this->authContext->userId(),
        ]);
    }

    /**
     * Update a campaign.
     */
    public function updateCampaign(int $id, array $data): array
    {
        $campaign = $this->repository->findById($id);

        if (!$campaign) {
            throw new \InvalidArgumentException('Campaign not found');
        }

        return $this->repository->update($id, [
            'name' => $data['name'] ?? $campaign['name'],
            'description' => $data['description'] ?? $campaign['description'],
            'type' => $data['type'] ?? $campaign['type'],
            'module_id' => $data['module_id'] ?? $campaign['module_id'],
            'start_date' => $data['start_date'] ?? $campaign['start_date'],
            'end_date' => $data['end_date'] ?? $campaign['end_date'],
            'budget' => $data['budget'] ?? $campaign['budget'],
            'settings' => array_merge($campaign['settings'] ?? [], $data['settings'] ?? []),
            'goals' => $data['goals'] ?? $campaign['goals'],
            'owner_id' => $data['owner_id'] ?? $campaign['owner_id'],
        ]);
    }

    /**
     * Delete a campaign.
     */
    public function deleteCampaign(int $id): bool
    {
        if ($this->repository->isActive($id)) {
            throw new \InvalidArgumentException('Cannot delete an active campaign');
        }

        return $this->repository->delete($id);
    }

    /**
     * Duplicate a campaign.
     */
    public function duplicateCampaign(int $id): array
    {
        $original = $this->repository->findByIdWithRelations($id);

        if (!$original) {
            throw new \InvalidArgumentException('Campaign not found');
        }

        return DB::transaction(function () use ($original) {
            // Create the campaign copy
            $newCampaign = $this->repository->create([
                'name' => $original['name'] . ' (Copy)',
                'description' => $original['description'],
                'type' => $original['type'],
                'status' => 'draft',
                'module_id' => $original['module_id'],
                'budget' => $original['budget'],
                'settings' => $original['settings'],
                'goals' => $original['goals'],
                'created_by' => $this->authContext->userId(),
                'owner_id' => $this->authContext->userId(),
            ]);

            // Copy audiences
            foreach ($original['audiences'] ?? [] as $audience) {
                $this->repository->createAudience([
                    'campaign_id' => $newCampaign['id'],
                    'name' => $audience['name'],
                    'description' => $audience['description'],
                    'module_id' => $audience['module_id'],
                    'segment_rules' => $audience['segment_rules'],
                    'is_dynamic' => $audience['is_dynamic'],
                ]);
            }

            // Copy assets
            foreach ($original['assets'] ?? [] as $asset) {
                $this->repository->createAsset([
                    'campaign_id' => $newCampaign['id'],
                    'name' => $asset['name'],
                    'type' => $asset['type'],
                    'content' => $asset['content'],
                    'settings' => $asset['settings'],
                ]);
            }

            return $this->repository->findByIdWithRelations($newCampaign['id']);
        });
    }

    // =========================================================================
    // LIFECYCLE USE CASES
    // =========================================================================

    /**
     * Start/activate a campaign.
     */
    public function startCampaign(int $id): array
    {
        if (!$this->repository->canBeStarted($id)) {
            throw new \InvalidArgumentException('Campaign cannot be started in its current state');
        }

        // Validate campaign has required components
        if ($this->repository->countAudiences($id) === 0) {
            throw new \InvalidArgumentException('Campaign must have at least one audience');
        }

        if ($this->repository->countAssets($id) === 0) {
            throw new \InvalidArgumentException('Campaign must have at least one asset');
        }

        $campaign = $this->repository->findById($id);

        return $this->repository->update($id, [
            'status' => 'active',
            'start_date' => $campaign['start_date'] ?? now(),
        ]);
    }

    /**
     * Pause a campaign.
     */
    public function pauseCampaign(int $id): array
    {
        if (!$this->repository->canBePaused($id)) {
            throw new \InvalidArgumentException('Campaign cannot be paused in its current state');
        }

        return $this->repository->update($id, ['status' => 'paused']);
    }

    /**
     * Resume a paused campaign.
     */
    public function resumeCampaign(int $id): array
    {
        $campaign = $this->repository->findById($id);

        if (!$campaign) {
            throw new \InvalidArgumentException('Campaign not found');
        }

        if ($campaign['status'] !== 'paused') {
            throw new \InvalidArgumentException('Only paused campaigns can be resumed');
        }

        return $this->repository->update($id, ['status' => 'active']);
    }

    /**
     * Complete a campaign.
     */
    public function completeCampaign(int $id): array
    {
        return $this->repository->update($id, [
            'status' => 'completed',
            'end_date' => now(),
        ]);
    }

    /**
     * Cancel a campaign.
     */
    public function cancelCampaign(int $id): array
    {
        $campaign = $this->repository->findById($id);

        if (!$campaign) {
            throw new \InvalidArgumentException('Campaign not found');
        }

        if ($campaign['status'] === 'completed') {
            throw new \InvalidArgumentException('Cannot cancel a completed campaign');
        }

        return $this->repository->update($id, [
            'status' => 'cancelled',
            'end_date' => now(),
        ]);
    }

    /**
     * Schedule a campaign.
     */
    public function scheduleCampaign(int $id, \DateTimeInterface $startDate, ?\DateTimeInterface $endDate = null): array
    {
        if (!$this->repository->isDraft($id)) {
            throw new \InvalidArgumentException('Only draft campaigns can be scheduled');
        }

        return $this->repository->update($id, [
            'status' => 'scheduled',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    // =========================================================================
    // AUDIENCE USE CASES
    // =========================================================================

    /**
     * List audiences for a campaign.
     */
    public function listAudiences(int $campaignId): array
    {
        return $this->repository->findAudiences($campaignId);
    }

    /**
     * Create an audience for a campaign.
     */
    public function createAudience(int $campaignId, array $data): array
    {
        $audience = $this->repository->createAudience([
            'campaign_id' => $campaignId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'module_id' => $data['module_id'],
            'segment_rules' => $data['segment_rules'] ?? [],
            'is_dynamic' => $data['is_dynamic'] ?? true,
        ]);

        // Refresh count
        return $this->repository->refreshAudienceCount($audience['id']);
    }

    /**
     * Update an audience.
     */
    public function updateAudience(int $audienceId, array $data): array
    {
        $audience = $this->repository->findAudienceById($audienceId);

        if (!$audience) {
            throw new \InvalidArgumentException('Audience not found');
        }

        $updated = $this->repository->updateAudience($audienceId, [
            'name' => $data['name'] ?? $audience['name'],
            'description' => $data['description'] ?? $audience['description'],
            'module_id' => $data['module_id'] ?? $audience['module_id'],
            'segment_rules' => $data['segment_rules'] ?? $audience['segment_rules'],
            'is_dynamic' => $data['is_dynamic'] ?? $audience['is_dynamic'],
        ]);

        // Refresh count if rules changed
        if (isset($data['segment_rules'])) {
            return $this->repository->refreshAudienceCount($audienceId);
        }

        return $updated;
    }

    /**
     * Delete an audience.
     */
    public function deleteAudience(int $audienceId): bool
    {
        return $this->repository->deleteAudience($audienceId);
    }

    /**
     * Refresh audience count.
     */
    public function refreshAudienceCount(int $audienceId): array
    {
        return $this->repository->refreshAudienceCount($audienceId);
    }

    /**
     * Add members to a static audience.
     */
    public function addAudienceMembers(int $audienceId, array $recordIds): int
    {
        $audience = $this->repository->findAudienceById($audienceId);

        if (!$audience) {
            throw new \InvalidArgumentException('Audience not found');
        }

        if ($audience['is_dynamic']) {
            throw new \InvalidArgumentException('Cannot manually add members to a dynamic audience');
        }

        $added = $this->repository->addAudienceMembers($audienceId, $recordIds);

        $this->repository->refreshAudienceCount($audienceId);

        return $added;
    }

    /**
     * Remove members from a static audience.
     */
    public function removeAudienceMembers(int $audienceId, array $recordIds): int
    {
        $audience = $this->repository->findAudienceById($audienceId);

        if (!$audience) {
            throw new \InvalidArgumentException('Audience not found');
        }

        if ($audience['is_dynamic']) {
            throw new \InvalidArgumentException('Cannot manually remove members from a dynamic audience');
        }

        $removed = $this->repository->removeAudienceMembers($audienceId, $recordIds);

        $this->repository->refreshAudienceCount($audienceId);

        return $removed;
    }

    // =========================================================================
    // ASSET USE CASES
    // =========================================================================

    /**
     * List assets for a campaign.
     */
    public function listAssets(int $campaignId): array
    {
        return $this->repository->findAssets($campaignId);
    }

    /**
     * Create a campaign asset.
     */
    public function createAsset(int $campaignId, array $data): array
    {
        return $this->repository->createAsset([
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
    public function updateAsset(int $assetId, array $data): array
    {
        $asset = $this->repository->findAssetById($assetId);

        if (!$asset) {
            throw new \InvalidArgumentException('Asset not found');
        }

        return $this->repository->updateAsset($assetId, [
            'name' => $data['name'] ?? $asset['name'],
            'type' => $data['type'] ?? $asset['type'],
            'content' => $data['content'] ?? $asset['content'],
            'settings' => array_merge($asset['settings'] ?? [], $data['settings'] ?? []),
        ]);
    }

    /**
     * Delete a campaign asset.
     */
    public function deleteAsset(int $assetId): bool
    {
        return $this->repository->deleteAsset($assetId);
    }

    // =========================================================================
    // SEND USE CASES
    // =========================================================================

    /**
     * Queue sends for a campaign.
     */
    public function queueSends(int $campaignId, ?\DateTimeInterface $scheduledAt = null): int
    {
        if (!$this->repository->isActive($campaignId)) {
            throw new \InvalidArgumentException('Campaign must be active to queue sends');
        }

        $audiences = $this->repository->findAudiences($campaignId);

        $queued = 0;

        foreach ($audiences as $audience) {
            $records = $this->repository->getMatchingRecords($audience['id']);

            foreach ($records as $record) {
                // Check if not already sent
                if (!$this->repository->sendExists($campaignId, $record['id'])) {
                    $this->repository->createSend([
                        'campaign_id' => $campaignId,
                        'record_id' => $record['id'],
                        'channel' => 'email',
                        'recipient' => $record['data']['email'] ?? null,
                        'status' => 'pending',
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
    public function getPendingSends(int $limit = 100): array
    {
        return $this->repository->findPendingSends($limit);
    }

    /**
     * Mark a send as sent.
     */
    public function markSent(int $sendId): array
    {
        $send = $this->repository->updateSend($sendId, [
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Update daily metrics
        $this->incrementDailyMetric($send['campaign_id'], 'sends');

        return $send;
    }

    /**
     * Mark a send as delivered.
     */
    public function markDelivered(int $sendId): array
    {
        $send = $this->repository->updateSend($sendId, [
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $this->incrementDailyMetric($send['campaign_id'], 'delivered');

        return $send;
    }

    /**
     * Track an email open.
     */
    public function trackOpen(int $sendId): array
    {
        $send = $this->repository->findSendById($sendId);

        if (!$send) {
            throw new \InvalidArgumentException('Send not found');
        }

        $wasFirstOpen = $send['opened_at'] === null;

        $updated = $this->repository->updateSend($sendId, [
            'status' => 'opened',
            'opened_at' => $send['opened_at'] ?? now(),
        ]);

        $this->incrementDailyMetric($send['campaign_id'], 'opens');
        if ($wasFirstOpen) {
            $this->incrementDailyMetric($send['campaign_id'], 'unique_opens');
        }

        return $updated;
    }

    /**
     * Track a click.
     */
    public function trackClick(int $sendId, string $url): array
    {
        $send = $this->repository->findSendById($sendId);

        if (!$send) {
            throw new \InvalidArgumentException('Send not found');
        }

        $wasFirstClick = $send['clicked_at'] === null;

        // Mark as opened if not already
        if (!$send['opened_at']) {
            $send = $this->repository->updateSend($sendId, [
                'opened_at' => now(),
            ]);
        }

        $updated = $this->repository->updateSend($sendId, [
            'status' => 'clicked',
            'clicked_at' => $send['clicked_at'] ?? now(),
        ]);

        // Log the click
        $this->repository->createClick([
            'campaign_send_id' => $sendId,
            'url' => $url,
            'clicked_at' => now(),
        ]);

        $this->incrementDailyMetric($send['campaign_id'], 'clicks');
        if ($wasFirstClick) {
            $this->incrementDailyMetric($send['campaign_id'], 'unique_clicks');
        }

        return $updated;
    }

    /**
     * Mark a send as bounced.
     */
    public function markBounced(int $sendId, string $reason = null): array
    {
        $send = $this->repository->updateSend($sendId, [
            'status' => 'bounced',
            'error_message' => $reason,
        ]);

        $this->incrementDailyMetric($send['campaign_id'], 'bounces');

        return $send;
    }

    /**
     * Track an unsubscribe.
     */
    public function trackUnsubscribe(int $sendId, ?string $reason = null): void
    {
        $send = $this->repository->findSendById($sendId);

        if (!$send) {
            throw new \InvalidArgumentException('Send not found');
        }

        $this->repository->createUnsubscribe([
            'campaign_id' => $send['campaign_id'],
            'record_id' => $send['record_id'],
            'email' => $send['recipient'],
            'reason' => $reason,
        ]);

        $this->incrementDailyMetric($send['campaign_id'], 'unsubscribes');
    }

    /**
     * Track a conversion.
     */
    public function trackConversion(int $campaignId, int $recordId, string $type, float $revenue = 0, ?array $metadata = null): array
    {
        $conversion = $this->repository->createConversion([
            'campaign_id' => $campaignId,
            'record_id' => $recordId,
            'type' => $type,
            'revenue' => $revenue,
            'metadata' => $metadata,
        ]);

        $this->incrementDailyMetric($campaignId, 'conversions');

        if ($revenue > 0) {
            $metric = $this->repository->getOrCreateMetricForDate($campaignId, now()->toDateString());
            $this->repository->addRevenue($metric['id'], $revenue);

            // Update campaign spent
            $this->repository->incrementCampaignSpent($campaignId, $revenue);
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
        $campaign = $this->repository->findById($campaignId);

        if (!$campaign) {
            throw new \InvalidArgumentException('Campaign not found');
        }

        $metrics = $this->repository->findMetrics($campaignId, $fromDate, $toDate);

        $totals = [
            'sends' => array_sum(array_column($metrics, 'sends')),
            'delivered' => array_sum(array_column($metrics, 'delivered')),
            'opens' => array_sum(array_column($metrics, 'opens')),
            'unique_opens' => array_sum(array_column($metrics, 'unique_opens')),
            'clicks' => array_sum(array_column($metrics, 'clicks')),
            'unique_clicks' => array_sum(array_column($metrics, 'unique_clicks')),
            'bounces' => array_sum(array_column($metrics, 'bounces')),
            'unsubscribes' => array_sum(array_column($metrics, 'unsubscribes')),
            'conversions' => array_sum(array_column($metrics, 'conversions')),
            'revenue' => array_sum(array_column($metrics, 'revenue')),
        ];

        $totals['open_rate'] = $totals['delivered'] > 0 ? round(($totals['unique_opens'] / $totals['delivered']) * 100, 2) : 0;
        $totals['click_rate'] = $totals['delivered'] > 0 ? round(($totals['unique_clicks'] / $totals['delivered']) * 100, 2) : 0;
        $totals['bounce_rate'] = $totals['sends'] > 0 ? round(($totals['bounces'] / $totals['sends']) * 100, 2) : 0;
        $totals['conversion_rate'] = $totals['unique_clicks'] > 0 ? round(($totals['conversions'] / $totals['unique_clicks']) * 100, 2) : 0;

        return [
            'campaign' => $campaign,
            'totals' => $totals,
            'daily_metrics' => $metrics,
            'budget' => $campaign['budget'],
            'spent' => $campaign['spent'],
            'roi' => $campaign['spent'] > 0 ? round((($totals['revenue'] - $campaign['spent']) / $campaign['spent']) * 100, 2) : 0,
        ];
    }

    /**
     * Get aggregate analytics across all campaigns.
     */
    public function getAggregateAnalytics(array $filters = []): array
    {
        return $this->repository->getAggregateAnalytics($filters);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Increment a daily metric.
     */
    private function incrementDailyMetric(int $campaignId, string $metric, int $amount = 1): void
    {
        $dailyMetric = $this->repository->getOrCreateMetricForDate($campaignId, now()->toDateString());
        $this->repository->incrementMetric($dailyMetric['id'], $metric, $amount);
    }
}
