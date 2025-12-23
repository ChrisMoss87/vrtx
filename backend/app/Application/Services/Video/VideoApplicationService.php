<?php

declare(strict_types=1);

namespace App\Application\Services\Video;

use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Video\Repositories\VideoMeetingRepositoryInterface;

class VideoApplicationService
{
    public function __construct(
        private VideoMeetingRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - PROVIDERS
    // =========================================================================

    /**
     * List video providers.
     */
    public function listProviders(array $filters = []): array
    {
        return $this->repository->findProviders($filters);
    }

    /**
     * Get a single provider by ID.
     */
    public function getProvider(int $id): ?array
    {
        return $this->repository->findProviderById($id);
    }

    /**
     * Get active and verified providers.
     */
    public function getActiveProviders(): array
    {
        return $this->repository->findActiveProviders();
    }

    // =========================================================================
    // COMMAND USE CASES - PROVIDERS
    // =========================================================================

    /**
     * Create a video provider connection.
     */
    public function createProvider(array $data): array
    {
        return $this->repository->createProvider($data);
    }

    /**
     * Update a video provider.
     */
    public function updateProvider(int $id, array $data): array
    {
        return $this->repository->updateProvider($id, $data);
    }

    /**
     * Delete a video provider.
     */
    public function deleteProvider(int $id): bool
    {
        return $this->repository->deleteProvider($id);
    }

    /**
     * Refresh OAuth token for a provider.
     */
    public function refreshProviderToken(int $id, string $accessToken, string $refreshToken, \DateTimeInterface $expiresAt): array
    {
        return $this->repository->refreshProviderToken($id, $accessToken, $refreshToken, $expiresAt);
    }

    // =========================================================================
    // QUERY USE CASES - MEETINGS
    // =========================================================================

    /**
     * List meetings with filtering and pagination.
     */
    public function listMeetings(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->findMeetings($filters, $page, $perPage);
    }

    /**
     * Get a single meeting by ID.
     */
    public function getMeeting(int $id): ?array
    {
        return $this->repository->findMeetingById($id);
    }

    /**
     * Get upcoming meetings for a host.
     */
    public function getUpcomingMeetings(?int $hostId = null, int $days = 7): array
    {
        $effectiveHostId = $hostId ?? $this->authContext->userId();
        return $this->repository->findUpcomingMeetings($effectiveHostId, $days);
    }

    /**
     * Get meetings for a specific deal/record.
     */
    public function getMeetingsForDeal(int $dealId, string $module): array
    {
        return $this->repository->findMeetingsForDeal($dealId, $module);
    }

    // =========================================================================
    // COMMAND USE CASES - MEETINGS
    // =========================================================================

    /**
     * Create/schedule a meeting.
     */
    public function createMeeting(array $data): array
    {
        // Set default host_id to current user if not provided
        if (!isset($data['host_id'])) {
            $data['host_id'] = $this->authContext->userId();
        }

        return $this->repository->createMeeting($data);
    }

    /**
     * Update a meeting.
     */
    public function updateMeeting(int $id, array $data): array
    {
        return $this->repository->updateMeeting($id, $data);
    }

    /**
     * Delete a meeting.
     */
    public function deleteMeeting(int $id): bool
    {
        return $this->repository->deleteMeeting($id);
    }

    /**
     * Start a meeting.
     */
    public function startMeeting(int $id): array
    {
        return $this->repository->startMeeting($id);
    }

    /**
     * End a meeting.
     */
    public function endMeeting(int $id, ?int $actualDurationSeconds = null): array
    {
        return $this->repository->endMeeting($id, $actualDurationSeconds);
    }

    /**
     * Cancel a meeting.
     */
    public function cancelMeeting(int $id): array
    {
        return $this->repository->cancelMeeting($id);
    }

    // =========================================================================
    // PARTICIPANT USE CASES
    // =========================================================================

    /**
     * Add a participant to a meeting.
     */
    public function addParticipant(int $meetingId, array $data): array
    {
        return $this->repository->createParticipant($meetingId, $data);
    }

    /**
     * Update participant details.
     */
    public function updateParticipant(int $participantId, array $data): array
    {
        return $this->repository->updateParticipant($participantId, $data);
    }

    /**
     * Mark participant as joined.
     */
    public function markParticipantJoined(int $participantId, array $data = []): array
    {
        return $this->repository->markParticipantJoined($participantId, $data);
    }

    /**
     * Mark participant as left.
     */
    public function markParticipantLeft(int $participantId): array
    {
        return $this->repository->markParticipantLeft($participantId);
    }

    /**
     * Mark participant as no-show.
     */
    public function markParticipantNoShow(int $participantId): array
    {
        return $this->repository->markParticipantNoShow($participantId);
    }

    /**
     * Remove a participant from a meeting.
     */
    public function removeParticipant(int $participantId): bool
    {
        return $this->repository->deleteParticipant($participantId);
    }

    /**
     * Get participants for a meeting.
     */
    public function getParticipants(int $meetingId): array
    {
        return $this->repository->findParticipants($meetingId);
    }

    // =========================================================================
    // RECORDING USE CASES
    // =========================================================================

    /**
     * Add a recording to a meeting.
     */
    public function addRecording(int $meetingId, array $data): array
    {
        return $this->repository->createRecording($meetingId, $data);
    }

    /**
     * Update a recording.
     */
    public function updateRecording(int $recordingId, array $data): array
    {
        return $this->repository->updateRecording($recordingId, $data);
    }

    /**
     * Delete a recording.
     */
    public function deleteRecording(int $recordingId): bool
    {
        return $this->repository->deleteRecording($recordingId);
    }

    /**
     * Get recordings for a meeting.
     */
    public function getRecordings(int $meetingId): array
    {
        return $this->repository->findRecordings($meetingId);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get meeting statistics for a host.
     */
    public function getMeetingStats(?int $hostId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $effectiveHostId = $hostId ?? $this->authContext->userId();
        return $this->repository->getMeetingStats($effectiveHostId, $fromDate, $toDate);
    }

    /**
     * Get participant statistics for a meeting.
     */
    public function getParticipantStats(int $meetingId): array
    {
        return $this->repository->getParticipantStats($meetingId);
    }

    /**
     * Get daily meeting count for dashboard.
     */
    public function getDailyMeetingCount(?int $hostId = null, int $days = 30): array
    {
        $effectiveHostId = $hostId ?? $this->authContext->userId();
        return $this->repository->getDailyMeetingCount($effectiveHostId, $days);
    }

    /**
     * Get provider usage statistics.
     */
    public function getProviderUsageStats(?string $fromDate = null, ?string $toDate = null): array
    {
        return $this->repository->getProviderUsageStats($fromDate, $toDate);
    }

    /**
     * Get recording statistics.
     */
    public function getRecordingStats(?string $fromDate = null, ?string $toDate = null): array
    {
        return $this->repository->getRecordingStats($fromDate, $toDate);
    }
}
