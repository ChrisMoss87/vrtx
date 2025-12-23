<?php

declare(strict_types=1);

namespace App\Domain\Video\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Video\Entities\VideoMeeting;

interface VideoMeetingRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?VideoMeeting;

    public function save(VideoMeeting $entity): VideoMeeting;

    public function delete(int $id): bool;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findAll(): array;

    // =========================================================================
    // QUERY METHODS - PROVIDERS
    // =========================================================================

    public function findProviders(array $filters = []): array;

    public function findProviderById(int $id): ?array;

    public function findActiveProviders(): array;

    public function createProvider(array $data): array;

    public function updateProvider(int $id, array $data): array;

    public function deleteProvider(int $id): bool;

    public function refreshProviderToken(int $id, string $accessToken, string $refreshToken, \DateTimeInterface $expiresAt): array;

    public function providerHasMeetings(int $id): bool;

    // =========================================================================
    // QUERY METHODS - MEETINGS
    // =========================================================================

    public function findMeetings(array $filters = [], int $page = 1, int $perPage = 25): PaginatedResult;

    public function findMeetingById(int $id): ?array;

    public function findUpcomingMeetings(?int $hostId, int $days = 7): array;

    public function findMeetingsForDeal(int $dealId, string $module): array;

    public function createMeeting(array $data): array;

    public function updateMeeting(int $id, array $data): array;

    public function deleteMeeting(int $id): bool;

    public function startMeeting(int $id): array;

    public function endMeeting(int $id, ?int $actualDurationSeconds = null): array;

    public function cancelMeeting(int $id): array;

    // =========================================================================
    // QUERY METHODS - PARTICIPANTS
    // =========================================================================

    public function findParticipants(int $meetingId): array;

    public function createParticipant(int $meetingId, array $data): array;

    public function updateParticipant(int $participantId, array $data): array;

    public function deleteParticipant(int $participantId): bool;

    public function markParticipantJoined(int $participantId, array $data = []): array;

    public function markParticipantLeft(int $participantId): array;

    public function markParticipantNoShow(int $participantId): array;

    // =========================================================================
    // QUERY METHODS - RECORDINGS
    // =========================================================================

    public function findRecordings(int $meetingId): array;

    public function createRecording(int $meetingId, array $data): array;

    public function updateRecording(int $recordingId, array $data): array;

    public function deleteRecording(int $recordingId): bool;

    // =========================================================================
    // ANALYTICS METHODS
    // =========================================================================

    public function getMeetingStats(?int $hostId, ?string $fromDate = null, ?string $toDate = null): array;

    public function getParticipantStats(int $meetingId): array;

    public function getDailyMeetingCount(?int $hostId, int $days = 30): array;

    public function getProviderUsageStats(?string $fromDate = null, ?string $toDate = null): array;

    public function getRecordingStats(?string $fromDate = null, ?string $toDate = null): array;
}
