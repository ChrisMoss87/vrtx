<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Video;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Video\Entities\VideoMeeting;
use App\Domain\Video\Repositories\VideoMeetingRepositoryInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentVideoMeetingRepository implements VideoMeetingRepositoryInterface
{
    private const TABLE = 'video_meetings';
    private const TABLE_PROVIDERS = 'video_providers';
    private const TABLE_PARTICIPANTS = 'video_meeting_participants';
    private const TABLE_RECORDINGS = 'video_meeting_recordings';
    private const TABLE_USERS = 'users';
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?VideoMeeting
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(VideoMeeting $entity): VideoMeeting
    {
        $data = $this->toModelData($entity);

        if ($entity->getId() !== null) {
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

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $meeting = $this->toArray($row);
        $meeting['provider'] = $this->getProviderById($row->provider_id);
        $meeting['host'] = $this->getUserById($row->host_id);
        $meeting['participants'] = $this->findParticipants($id);
        $meeting['recordings'] = $this->findRecordings($id);

        return $meeting;
    }

    public function findAll(): array
    {
        return DB::table(self::TABLE)
            ->get()
            ->map(fn($row) => $this->toArray($row))
            ->toArray();
    }

    // =========================================================================
    // QUERY METHODS - PROVIDERS
    // =========================================================================

    public function findProviders(array $filters = []): array
    {
        $query = DB::table(self::TABLE_PROVIDERS);

        if (!empty($filters['active'])) {
            $query->where('is_active', true);
        }

        if (!empty($filters['verified'])) {
            $query->where('is_verified', true);
        }

        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        return $query->orderBy('name')
            ->get()
            ->map(fn($row) => $this->providerToArray($row))
            ->toArray();
    }

    public function findProviderById(int $id): ?array
    {
        $row = DB::table(self::TABLE_PROVIDERS)->where('id', $id)->first();
        return $row ? $this->providerToArray($row) : null;
    }

    public function findActiveProviders(): array
    {
        return DB::table(self::TABLE_PROVIDERS)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->orderBy('name')
            ->get()
            ->map(fn($row) => $this->providerToArray($row))
            ->toArray();
    }

    public function createProvider(array $data): array
    {
        $insertData = [
            'name' => $data['name'],
            'provider' => $data['provider'],
            'api_key' => $data['api_key'] ?? null,
            'api_secret' => $data['api_secret'] ?? null,
            'access_token' => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'client_secret' => $data['client_secret'] ?? null,
            'webhook_secret' => $data['webhook_secret'] ?? null,
            'token_expires_at' => $data['token_expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_verified' => $data['is_verified'] ?? false,
            'settings' => json_encode($data['settings'] ?? []),
            'scopes' => json_encode($data['scopes'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table(self::TABLE_PROVIDERS)->insertGetId($insertData);
        $row = DB::table(self::TABLE_PROVIDERS)->where('id', $id)->first();

        return $this->providerToArray($row);
    }

    public function updateProvider(int $id, array $data): array
    {
        $existing = DB::table(self::TABLE_PROVIDERS)->where('id', $id)->first();
        if (!$existing) {
            throw new \InvalidArgumentException("Provider not found: {$id}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['api_key'])) $updateData['api_key'] = $data['api_key'];
        if (isset($data['api_secret'])) $updateData['api_secret'] = $data['api_secret'];
        if (isset($data['access_token'])) $updateData['access_token'] = $data['access_token'];
        if (isset($data['refresh_token'])) $updateData['refresh_token'] = $data['refresh_token'];
        if (isset($data['client_id'])) $updateData['client_id'] = $data['client_id'];
        if (isset($data['client_secret'])) $updateData['client_secret'] = $data['client_secret'];
        if (isset($data['webhook_secret'])) $updateData['webhook_secret'] = $data['webhook_secret'];
        if (isset($data['token_expires_at'])) $updateData['token_expires_at'] = $data['token_expires_at'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
        if (isset($data['is_verified'])) $updateData['is_verified'] = $data['is_verified'];
        if (isset($data['settings'])) {
            $existingSettings = isset($existing->settings) ? (is_string($existing->settings) ? json_decode($existing->settings, true) : $existing->settings) : [];
            $updateData['settings'] = json_encode(array_merge($existingSettings, $data['settings']));
        }
        if (isset($data['scopes'])) $updateData['scopes'] = json_encode($data['scopes']);
        if (isset($data['last_synced_at'])) $updateData['last_synced_at'] = $data['last_synced_at'];

        DB::table(self::TABLE_PROVIDERS)->where('id', $id)->update($updateData);
        $row = DB::table(self::TABLE_PROVIDERS)->where('id', $id)->first();

        return $this->providerToArray($row);
    }

    public function deleteProvider(int $id): bool
    {
        if ($this->providerHasMeetings($id)) {
            throw new \InvalidArgumentException('Cannot delete provider with existing meetings');
        }

        return DB::table(self::TABLE_PROVIDERS)->where('id', $id)->delete() > 0;
    }

    public function refreshProviderToken(int $id, string $accessToken, string $refreshToken, DateTimeInterface $expiresAt): array
    {
        DB::table(self::TABLE_PROVIDERS)->where('id', $id)->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $expiresAt,
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_PROVIDERS)->where('id', $id)->first();
        return $this->providerToArray($row);
    }

    public function providerHasMeetings(int $id): bool
    {
        return DB::table(self::TABLE)->where('provider_id', $id)->exists();
    }

    // =========================================================================
    // QUERY METHODS - MEETINGS
    // =========================================================================

    public function findMeetings(array $filters = [], int $page = 1, int $perPage = 25): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by provider
        if (!empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }

        // Filter by host
        if (!empty($filters['host_id'])) {
            $query->where('host_id', $filters['host_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter upcoming only
        if (!empty($filters['upcoming'])) {
            $query->where('status', 'scheduled')->where('scheduled_at', '>=', now());
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->where('scheduled_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('scheduled_at', '<=', $filters['to_date']);
        }

        // Filter by deal/record
        if (!empty($filters['deal_id']) && !empty($filters['deal_module'])) {
            $query->where('deal_id', $filters['deal_id'])->where('deal_module', $filters['deal_module']);
        }

        // Filter by meeting type
        if (!empty($filters['meeting_type'])) {
            $query->where('meeting_type', $filters['meeting_type']);
        }

        // Filter by recording status
        if (!empty($filters['has_recording'])) {
            $query->whereNotNull('recording_url');
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('external_meeting_id', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'scheduled_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Get paginated items
        $items = $query->forPage($page, $perPage)->get();

        $mappedItems = $items->map(function ($row) {
            $meeting = $this->toArray($row);
            $meeting['provider'] = $this->getProviderById($row->provider_id);
            $meeting['host'] = $this->getUserById($row->host_id);
            return $meeting;
        })->toArray();

        return PaginatedResult::create(
            items: $mappedItems,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findMeetingById(int $id): ?array
    {
        return $this->findByIdAsArray($id);
    }

    public function findUpcomingMeetings(?int $hostId, int $days = 7): array
    {
        $query = DB::table(self::TABLE)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addDays($days))
            ->orderBy('scheduled_at');

        if ($hostId) {
            $query->where('host_id', $hostId);
        }

        return $query->get()->map(function ($row) {
            $meeting = $this->toArray($row);
            $meeting['provider'] = $this->getProviderById($row->provider_id);
            $meeting['host'] = $this->getUserById($row->host_id);
            $meeting['participants'] = $this->findParticipants($row->id);
            return $meeting;
        })->toArray();
    }

    public function findMeetingsForDeal(int $dealId, string $module): array
    {
        return DB::table(self::TABLE)
            ->where('deal_id', $dealId)
            ->where('deal_module', $module)
            ->orderBy('scheduled_at', 'desc')
            ->get()
            ->map(function ($row) {
                $meeting = $this->toArray($row);
                $meeting['provider'] = $this->getProviderById($row->provider_id);
                $meeting['host'] = $this->getUserById($row->host_id);
                $meeting['participants'] = $this->findParticipants($row->id);
                return $meeting;
            })->toArray();
    }

    public function createMeeting(array $data): array
    {
        $insertData = [
            'provider_id' => $data['provider_id'],
            'external_meeting_id' => $data['external_meeting_id'] ?? null,
            'host_id' => $data['host_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'scheduled',
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'join_url' => $data['join_url'] ?? null,
            'host_url' => $data['host_url'] ?? null,
            'password' => $data['password'] ?? null,
            'waiting_room_enabled' => $data['waiting_room_enabled'] ?? false,
            'recording_enabled' => $data['recording_enabled'] ?? false,
            'recording_auto_start' => $data['recording_auto_start'] ?? false,
            'meeting_type' => $data['meeting_type'] ?? 'scheduled',
            'recurrence_type' => $data['recurrence_type'] ?? null,
            'recurrence_settings' => isset($data['recurrence_settings']) ? json_encode($data['recurrence_settings']) : null,
            'deal_id' => $data['deal_id'] ?? null,
            'deal_module' => $data['deal_module'] ?? null,
            'custom_fields' => json_encode($data['custom_fields'] ?? []),
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table(self::TABLE)->insertGetId($insertData);
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toArray($row);
    }

    public function updateMeeting(int $id, array $data): array
    {
        $existing = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$existing) {
            throw new \InvalidArgumentException("Meeting not found: {$id}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['scheduled_at'])) $updateData['scheduled_at'] = $data['scheduled_at'];
        if (isset($data['duration_minutes'])) $updateData['duration_minutes'] = $data['duration_minutes'];
        if (isset($data['join_url'])) $updateData['join_url'] = $data['join_url'];
        if (isset($data['host_url'])) $updateData['host_url'] = $data['host_url'];
        if (isset($data['password'])) $updateData['password'] = $data['password'];
        if (isset($data['waiting_room_enabled'])) $updateData['waiting_room_enabled'] = $data['waiting_room_enabled'];
        if (isset($data['recording_enabled'])) $updateData['recording_enabled'] = $data['recording_enabled'];
        if (isset($data['recording_auto_start'])) $updateData['recording_auto_start'] = $data['recording_auto_start'];
        if (isset($data['deal_id'])) $updateData['deal_id'] = $data['deal_id'];
        if (isset($data['deal_module'])) $updateData['deal_module'] = $data['deal_module'];
        if (isset($data['custom_fields'])) {
            $existingFields = isset($existing->custom_fields) ? (is_string($existing->custom_fields) ? json_decode($existing->custom_fields, true) : $existing->custom_fields) : [];
            $updateData['custom_fields'] = json_encode(array_merge($existingFields, $data['custom_fields']));
        }
        if (isset($data['metadata'])) {
            $existingMeta = isset($existing->metadata) ? (is_string($existing->metadata) ? json_decode($existing->metadata, true) : $existing->metadata) : [];
            $updateData['metadata'] = json_encode(array_merge($existingMeta, $data['metadata']));
        }

        DB::table(self::TABLE)->where('id', $id)->update($updateData);
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toArray($row);
    }

    public function deleteMeeting(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function startMeeting(int $id): array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$row) {
            throw new \InvalidArgumentException("Meeting not found: {$id}");
        }

        if ($row->status !== 'scheduled') {
            throw new \InvalidArgumentException('Only scheduled meetings can be started');
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'started',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $this->toArray($row);
    }

    public function endMeeting(int $id, ?int $actualDurationSeconds = null): array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$row) {
            throw new \InvalidArgumentException("Meeting not found: {$id}");
        }

        if ($row->status !== 'started') {
            throw new \InvalidArgumentException('Only started meetings can be ended');
        }

        $duration = $actualDurationSeconds;
        if (!$duration && $row->started_at) {
            $duration = now()->diffInSeconds($row->started_at);
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'ended',
            'ended_at' => now(),
            'actual_duration_seconds' => $duration,
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $this->toArray($row);
    }

    public function cancelMeeting(int $id): array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$row) {
            throw new \InvalidArgumentException("Meeting not found: {$id}");
        }

        if ($row->status === 'ended') {
            throw new \InvalidArgumentException('Cannot cancel an ended meeting');
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'canceled',
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $this->toArray($row);
    }

    // =========================================================================
    // QUERY METHODS - PARTICIPANTS
    // =========================================================================

    public function findParticipants(int $meetingId): array
    {
        return DB::table(self::TABLE_PARTICIPANTS)
            ->where('meeting_id', $meetingId)
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(function ($row) {
                $participant = $this->participantToArray($row);
                $participant['user'] = $this->getUserById($row->user_id);
                return $participant;
            })->toArray();
    }

    public function createParticipant(int $meetingId, array $data): array
    {
        $insertData = [
            'meeting_id' => $meetingId,
            'user_id' => $data['user_id'] ?? null,
            'email' => $data['email'],
            'name' => $data['name'],
            'role' => $data['role'] ?? 'attendee',
            'status' => 'invited',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table(self::TABLE_PARTICIPANTS)->insertGetId($insertData);
        $row = DB::table(self::TABLE_PARTICIPANTS)->where('id', $id)->first();

        return $this->participantToArray($row);
    }

    public function updateParticipant(int $participantId, array $data): array
    {
        $updateData = ['updated_at' => now()];

        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['role'])) $updateData['role'] = $data['role'];
        if (isset($data['joined_at'])) $updateData['joined_at'] = $data['joined_at'];
        if (isset($data['left_at'])) $updateData['left_at'] = $data['left_at'];
        if (isset($data['duration_seconds'])) $updateData['duration_seconds'] = $data['duration_seconds'];
        if (isset($data['device_type'])) $updateData['device_type'] = $data['device_type'];
        if (isset($data['ip_address'])) $updateData['ip_address'] = $data['ip_address'];
        if (isset($data['location'])) $updateData['location'] = $data['location'];
        if (isset($data['audio_enabled'])) $updateData['audio_enabled'] = $data['audio_enabled'];
        if (isset($data['video_enabled'])) $updateData['video_enabled'] = $data['video_enabled'];
        if (isset($data['screen_shared'])) $updateData['screen_shared'] = $data['screen_shared'];
        if (isset($data['attentiveness_score'])) $updateData['attentiveness_score'] = $data['attentiveness_score'];

        DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->update($updateData);
        $row = DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->first();

        return $this->participantToArray($row);
    }

    public function deleteParticipant(int $participantId): bool
    {
        return DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->delete() > 0;
    }

    public function markParticipantJoined(int $participantId, array $data = []): array
    {
        DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->update([
            'status' => 'joined',
            'joined_at' => now(),
            'device_type' => $data['device_type'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'location' => $data['location'] ?? null,
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->first();
        return $this->participantToArray($row);
    }

    public function markParticipantLeft(int $participantId): array
    {
        $row = DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->first();

        $duration = null;
        if ($row && $row->joined_at) {
            $duration = now()->diffInSeconds($row->joined_at);
        }

        DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->update([
            'status' => 'left',
            'left_at' => now(),
            'duration_seconds' => $duration,
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->first();
        return $this->participantToArray($row);
    }

    public function markParticipantNoShow(int $participantId): array
    {
        DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->update([
            'status' => 'no_show',
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE_PARTICIPANTS)->where('id', $participantId)->first();
        return $this->participantToArray($row);
    }

    // =========================================================================
    // QUERY METHODS - RECORDINGS
    // =========================================================================

    public function findRecordings(int $meetingId): array
    {
        return DB::table(self::TABLE_RECORDINGS)
            ->where('meeting_id', $meetingId)
            ->orderBy('recording_start', 'desc')
            ->get()
            ->map(fn($row) => $this->recordingToArray($row))
            ->toArray();
    }

    public function createRecording(int $meetingId, array $data): array
    {
        $insertData = [
            'meeting_id' => $meetingId,
            'external_recording_id' => $data['external_recording_id'] ?? null,
            'type' => $data['type'] ?? 'video',
            'status' => $data['status'] ?? 'processing',
            'file_url' => $data['file_url'] ?? null,
            'download_url' => $data['download_url'] ?? null,
            'play_url' => $data['play_url'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'format' => $data['format'] ?? null,
            'recording_start' => $data['recording_start'] ?? null,
            'recording_end' => $data['recording_end'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table(self::TABLE_RECORDINGS)->insertGetId($insertData);
        $row = DB::table(self::TABLE_RECORDINGS)->where('id', $id)->first();

        return $this->recordingToArray($row);
    }

    public function updateRecording(int $recordingId, array $data): array
    {
        $existing = DB::table(self::TABLE_RECORDINGS)->where('id', $recordingId)->first();
        $updateData = ['updated_at' => now()];

        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['file_url'])) $updateData['file_url'] = $data['file_url'];
        if (isset($data['download_url'])) $updateData['download_url'] = $data['download_url'];
        if (isset($data['play_url'])) $updateData['play_url'] = $data['play_url'];
        if (isset($data['file_size'])) $updateData['file_size'] = $data['file_size'];
        if (isset($data['duration_seconds'])) $updateData['duration_seconds'] = $data['duration_seconds'];
        if (isset($data['format'])) $updateData['format'] = $data['format'];
        if (isset($data['recording_start'])) $updateData['recording_start'] = $data['recording_start'];
        if (isset($data['recording_end'])) $updateData['recording_end'] = $data['recording_end'];
        if (isset($data['expires_at'])) $updateData['expires_at'] = $data['expires_at'];
        if (isset($data['transcript_text'])) $updateData['transcript_text'] = $data['transcript_text'];
        if (isset($data['transcript_segments'])) $updateData['transcript_segments'] = json_encode($data['transcript_segments']);
        if (isset($data['metadata'])) {
            $existingMeta = isset($existing->metadata) ? (is_string($existing->metadata) ? json_decode($existing->metadata, true) : $existing->metadata) : [];
            $updateData['metadata'] = json_encode(array_merge($existingMeta, $data['metadata']));
        }

        DB::table(self::TABLE_RECORDINGS)->where('id', $recordingId)->update($updateData);
        $row = DB::table(self::TABLE_RECORDINGS)->where('id', $recordingId)->first();

        return $this->recordingToArray($row);
    }

    public function deleteRecording(int $recordingId): bool
    {
        return DB::table(self::TABLE_RECORDINGS)->where('id', $recordingId)->delete() > 0;
    }

    // =========================================================================
    // ANALYTICS METHODS
    // =========================================================================

    public function getMeetingStats(?int $hostId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table(self::TABLE);

        if ($hostId) {
            $query->where('host_id', $hostId);
        }

        if ($fromDate) {
            $query->where('scheduled_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('scheduled_at', '<=', $toDate);
        }

        $total = (clone $query)->count();
        $scheduled = (clone $query)->where('status', 'scheduled')->count();
        $started = (clone $query)->where('status', 'started')->count();
        $ended = (clone $query)->where('status', 'ended')->count();
        $canceled = (clone $query)->where('status', 'canceled')->count();

        $totalDuration = (clone $query)->where('status', 'ended')->sum('actual_duration_seconds');
        $avgDuration = $ended > 0 ? $totalDuration / $ended : 0;

        $withRecordings = (clone $query)->whereNotNull('recording_url')->count();

        return [
            'total_meetings' => $total,
            'scheduled' => $scheduled,
            'started' => $started,
            'ended' => $ended,
            'canceled' => $canceled,
            'total_duration_seconds' => $totalDuration,
            'avg_duration_seconds' => round($avgDuration),
            'with_recordings' => $withRecordings,
        ];
    }

    public function getParticipantStats(int $meetingId): array
    {
        $query = DB::table(self::TABLE_PARTICIPANTS)->where('meeting_id', $meetingId);

        $total = (clone $query)->count();
        $joined = (clone $query)->where('status', 'joined')->count();
        $noShow = (clone $query)->where('status', 'no_show')->count();

        $totalDuration = (clone $query)->where('status', 'left')->sum('duration_seconds');
        $avgDuration = $joined > 0 ? $totalDuration / $joined : 0;

        $avgAttentiveness = (clone $query)
            ->whereNotNull('attentiveness_score')
            ->avg('attentiveness_score');

        return [
            'total_invited' => $total,
            'joined' => $joined,
            'no_show' => $noShow,
            'attendance_rate' => $total > 0 ? round(($joined / $total) * 100, 2) : 0,
            'total_duration_seconds' => $totalDuration,
            'avg_duration_seconds' => round($avgDuration),
            'avg_attentiveness_score' => $avgAttentiveness ? round($avgAttentiveness, 2) : null,
        ];
    }

    public function getDailyMeetingCount(?int $hostId, int $days = 30): array
    {
        $query = DB::table(self::TABLE)
            ->selectRaw('DATE(scheduled_at) as date, COUNT(*) as count')
            ->where('scheduled_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date');

        if ($hostId) {
            $query->where('host_id', $hostId);
        }

        return $query->get()->map(fn($row) => ['date' => $row->date, 'count' => $row->count])->toArray();
    }

    public function getProviderUsageStats(?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table(self::TABLE)
            ->selectRaw('provider_id, COUNT(*) as meeting_count, SUM(actual_duration_seconds) as total_duration')
            ->groupBy('provider_id');

        if ($fromDate) {
            $query->where('scheduled_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('scheduled_at', '<=', $toDate);
        }

        $results = $query->get();

        $byProvider = $results->map(function ($item) {
            $provider = $this->getProviderById($item->provider_id);
            return [
                'provider_name' => $provider['name'] ?? null,
                'provider_type' => $provider['provider'] ?? null,
                'meeting_count' => $item->meeting_count,
                'total_duration_seconds' => $item->total_duration ?? 0,
            ];
        })->toArray();

        return [
            'by_provider' => $byProvider,
            'total_meetings' => $results->sum('meeting_count'),
            'total_duration_seconds' => $results->sum('total_duration'),
        ];
    }

    public function getRecordingStats(?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table(self::TABLE_RECORDINGS);

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $processing = (clone $query)->where('status', 'processing')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        $totalSize = (clone $query)->where('status', 'completed')->sum('file_size');
        $totalDuration = (clone $query)->where('status', 'completed')->sum('duration_seconds');

        $byType = DB::table(self::TABLE_RECORDINGS)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total_recordings' => $total,
            'completed' => $completed,
            'processing' => $processing,
            'failed' => $failed,
            'total_size_bytes' => $totalSize,
            'total_duration_seconds' => $totalDuration,
            'by_type' => $byType,
        ];
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): VideoMeeting
    {
        return VideoMeeting::reconstitute(
            id: $row->id,
            createdAt: isset($row->created_at) ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: isset($row->updated_at) ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(VideoMeeting $entity): array
    {
        return [
            'updated_at' => now(),
        ];
    }

    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'provider_id' => $row->provider_id ?? null,
            'external_meeting_id' => $row->external_meeting_id ?? null,
            'host_id' => $row->host_id ?? null,
            'title' => $row->title ?? null,
            'description' => $row->description ?? null,
            'status' => $row->status ?? null,
            'scheduled_at' => $row->scheduled_at ?? null,
            'started_at' => $row->started_at ?? null,
            'ended_at' => $row->ended_at ?? null,
            'duration_minutes' => $row->duration_minutes ?? null,
            'actual_duration_seconds' => $row->actual_duration_seconds ?? null,
            'join_url' => $row->join_url ?? null,
            'host_url' => $row->host_url ?? null,
            'password' => $row->password ?? null,
            'waiting_room_enabled' => $row->waiting_room_enabled ?? false,
            'recording_enabled' => $row->recording_enabled ?? false,
            'recording_auto_start' => $row->recording_auto_start ?? false,
            'recording_url' => $row->recording_url ?? null,
            'meeting_type' => $row->meeting_type ?? null,
            'recurrence_type' => $row->recurrence_type ?? null,
            'recurrence_settings' => isset($row->recurrence_settings) ? (is_string($row->recurrence_settings) ? json_decode($row->recurrence_settings, true) : $row->recurrence_settings) : null,
            'deal_id' => $row->deal_id ?? null,
            'deal_module' => $row->deal_module ?? null,
            'custom_fields' => isset($row->custom_fields) ? (is_string($row->custom_fields) ? json_decode($row->custom_fields, true) : $row->custom_fields) : [],
            'metadata' => isset($row->metadata) ? (is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata) : [],
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function providerToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
            'provider' => $row->provider ?? null,
            'api_key' => $row->api_key ?? null,
            'api_secret' => $row->api_secret ?? null,
            'access_token' => $row->access_token ?? null,
            'refresh_token' => $row->refresh_token ?? null,
            'client_id' => $row->client_id ?? null,
            'client_secret' => $row->client_secret ?? null,
            'webhook_secret' => $row->webhook_secret ?? null,
            'token_expires_at' => $row->token_expires_at ?? null,
            'is_active' => $row->is_active ?? false,
            'is_verified' => $row->is_verified ?? false,
            'settings' => isset($row->settings) ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            'scopes' => isset($row->scopes) ? (is_string($row->scopes) ? json_decode($row->scopes, true) : $row->scopes) : [],
            'last_synced_at' => $row->last_synced_at ?? null,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function participantToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'meeting_id' => $row->meeting_id ?? null,
            'user_id' => $row->user_id ?? null,
            'email' => $row->email ?? null,
            'name' => $row->name ?? null,
            'role' => $row->role ?? null,
            'status' => $row->status ?? null,
            'joined_at' => $row->joined_at ?? null,
            'left_at' => $row->left_at ?? null,
            'duration_seconds' => $row->duration_seconds ?? null,
            'device_type' => $row->device_type ?? null,
            'ip_address' => $row->ip_address ?? null,
            'location' => $row->location ?? null,
            'audio_enabled' => $row->audio_enabled ?? null,
            'video_enabled' => $row->video_enabled ?? null,
            'screen_shared' => $row->screen_shared ?? null,
            'attentiveness_score' => $row->attentiveness_score ?? null,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function recordingToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'meeting_id' => $row->meeting_id ?? null,
            'external_recording_id' => $row->external_recording_id ?? null,
            'type' => $row->type ?? null,
            'status' => $row->status ?? null,
            'file_url' => $row->file_url ?? null,
            'download_url' => $row->download_url ?? null,
            'play_url' => $row->play_url ?? null,
            'file_size' => $row->file_size ?? null,
            'duration_seconds' => $row->duration_seconds ?? null,
            'format' => $row->format ?? null,
            'recording_start' => $row->recording_start ?? null,
            'recording_end' => $row->recording_end ?? null,
            'expires_at' => $row->expires_at ?? null,
            'transcript_text' => $row->transcript_text ?? null,
            'transcript_segments' => isset($row->transcript_segments) ? (is_string($row->transcript_segments) ? json_decode($row->transcript_segments, true) : $row->transcript_segments) : null,
            'metadata' => isset($row->metadata) ? (is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata) : [],
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function getProviderById(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $row = DB::table(self::TABLE_PROVIDERS)->where('id', $id)->first();
        return $row ? $this->providerToArray($row) : null;
    }

    private function getUserById(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        $row = DB::table(self::TABLE_USERS)->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        return [
            'id' => $row->id,
            'name' => $row->name ?? null,
            'email' => $row->email ?? null,
        ];
    }
}
