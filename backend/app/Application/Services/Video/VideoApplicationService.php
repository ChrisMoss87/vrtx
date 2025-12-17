<?php

declare(strict_types=1);

namespace App\Application\Services\Video;

use App\Domain\Video\Repositories\VideoMeetingRepositoryInterface;
use App\Models\VideoMeeting;
use App\Models\VideoMeetingParticipant;
use App\Models\VideoMeetingRecording;
use App\Models\VideoProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VideoApplicationService
{
    public function __construct(
        private VideoMeetingRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - PROVIDERS
    // =========================================================================

    /**
     * List video providers.
     */
    public function listProviders(array $filters = []): Collection
    {
        $query = VideoProvider::query();

        if (!empty($filters['active'])) {
            $query->active();
        }

        if (!empty($filters['verified'])) {
            $query->verified();
        }

        if (!empty($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a single provider by ID.
     */
    public function getProvider(int $id): ?VideoProvider
    {
        return VideoProvider::find($id);
    }

    /**
     * Get active and verified providers.
     */
    public function getActiveProviders(): Collection
    {
        return VideoProvider::active()->verified()->orderBy('name')->get();
    }

    // =========================================================================
    // COMMAND USE CASES - PROVIDERS
    // =========================================================================

    /**
     * Create a video provider connection.
     */
    public function createProvider(array $data): VideoProvider
    {
        return VideoProvider::create([
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
            'settings' => $data['settings'] ?? [],
            'scopes' => $data['scopes'] ?? [],
        ]);
    }

    /**
     * Update a video provider.
     */
    public function updateProvider(int $id, array $data): VideoProvider
    {
        $provider = VideoProvider::findOrFail($id);

        $updateData = [];

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
        if (isset($data['settings'])) $updateData['settings'] = array_merge($provider->settings ?? [], $data['settings']);
        if (isset($data['scopes'])) $updateData['scopes'] = $data['scopes'];
        if (isset($data['last_synced_at'])) $updateData['last_synced_at'] = $data['last_synced_at'];

        $provider->update($updateData);

        return $provider->fresh();
    }

    /**
     * Delete a video provider.
     */
    public function deleteProvider(int $id): bool
    {
        $provider = VideoProvider::findOrFail($id);

        // Check if provider has meetings
        if ($provider->meetings()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete provider with existing meetings');
        }

        return $provider->delete();
    }

    /**
     * Refresh OAuth token for a provider.
     */
    public function refreshProviderToken(int $id, string $accessToken, string $refreshToken, \DateTimeInterface $expiresAt): VideoProvider
    {
        $provider = VideoProvider::findOrFail($id);

        $provider->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $expiresAt,
        ]);

        return $provider->fresh();
    }

    // =========================================================================
    // QUERY USE CASES - MEETINGS
    // =========================================================================

    /**
     * List meetings with filtering and pagination.
     */
    public function listMeetings(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = VideoMeeting::query()
            ->with(['provider:id,name,provider', 'host:id,name,email']);

        // Filter by provider
        if (!empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }

        // Filter by host
        if (!empty($filters['host_id'])) {
            $query->forHost($filters['host_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter upcoming only
        if (!empty($filters['upcoming'])) {
            $query->upcoming();
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
            $query->forDeal($filters['deal_id'], $filters['deal_module']);
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

        return $query->paginate($perPage);
    }

    /**
     * Get a single meeting by ID.
     */
    public function getMeeting(int $id): ?VideoMeeting
    {
        return VideoMeeting::with([
            'provider:id,name,provider',
            'host:id,name,email',
            'participants.user:id,name,email',
            'recordings'
        ])->find($id);
    }

    /**
     * Get upcoming meetings for a host.
     */
    public function getUpcomingMeetings(?int $hostId = null, int $days = 7): Collection
    {
        $query = VideoMeeting::upcoming()
            ->with(['provider:id,name', 'host:id,name', 'participants'])
            ->where('scheduled_at', '<=', now()->addDays($days))
            ->orderBy('scheduled_at');

        if ($hostId) {
            $query->forHost($hostId);
        } else {
            $query->forHost(Auth::id());
        }

        return $query->get();
    }

    /**
     * Get meetings for a specific deal/record.
     */
    public function getMeetingsForDeal(int $dealId, string $module): Collection
    {
        return VideoMeeting::forDeal($dealId, $module)
            ->with(['provider:id,name', 'host:id,name', 'participants'])
            ->orderBy('scheduled_at', 'desc')
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - MEETINGS
    // =========================================================================

    /**
     * Create/schedule a meeting.
     */
    public function createMeeting(array $data): VideoMeeting
    {
        return VideoMeeting::create([
            'provider_id' => $data['provider_id'],
            'external_meeting_id' => $data['external_meeting_id'] ?? null,
            'host_id' => $data['host_id'] ?? Auth::id(),
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
            'recurrence_settings' => $data['recurrence_settings'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'deal_module' => $data['deal_module'] ?? null,
            'custom_fields' => $data['custom_fields'] ?? [],
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Update a meeting.
     */
    public function updateMeeting(int $id, array $data): VideoMeeting
    {
        $meeting = VideoMeeting::findOrFail($id);

        $updateData = [];

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
        if (isset($data['custom_fields'])) $updateData['custom_fields'] = array_merge($meeting->custom_fields ?? [], $data['custom_fields']);
        if (isset($data['metadata'])) $updateData['metadata'] = array_merge($meeting->metadata ?? [], $data['metadata']);

        $meeting->update($updateData);

        return $meeting->fresh();
    }

    /**
     * Delete a meeting.
     */
    public function deleteMeeting(int $id): bool
    {
        $meeting = VideoMeeting::findOrFail($id);
        return $meeting->delete();
    }

    /**
     * Start a meeting.
     */
    public function startMeeting(int $id): VideoMeeting
    {
        $meeting = VideoMeeting::findOrFail($id);

        if (!$meeting->isScheduled()) {
            throw new \InvalidArgumentException('Only scheduled meetings can be started');
        }

        $meeting->update([
            'status' => 'started',
            'started_at' => now(),
        ]);

        return $meeting->fresh();
    }

    /**
     * End a meeting.
     */
    public function endMeeting(int $id, ?int $actualDurationSeconds = null): VideoMeeting
    {
        $meeting = VideoMeeting::findOrFail($id);

        if (!$meeting->isStarted()) {
            throw new \InvalidArgumentException('Only started meetings can be ended');
        }

        $duration = $actualDurationSeconds;
        if (!$duration && $meeting->started_at) {
            $duration = now()->diffInSeconds($meeting->started_at);
        }

        $meeting->update([
            'status' => 'ended',
            'ended_at' => now(),
            'actual_duration_seconds' => $duration,
        ]);

        return $meeting->fresh();
    }

    /**
     * Cancel a meeting.
     */
    public function cancelMeeting(int $id): VideoMeeting
    {
        $meeting = VideoMeeting::findOrFail($id);

        if ($meeting->isEnded()) {
            throw new \InvalidArgumentException('Cannot cancel an ended meeting');
        }

        $meeting->update(['status' => 'canceled']);

        return $meeting->fresh();
    }

    // =========================================================================
    // PARTICIPANT USE CASES
    // =========================================================================

    /**
     * Add a participant to a meeting.
     */
    public function addParticipant(int $meetingId, array $data): VideoMeetingParticipant
    {
        return VideoMeetingParticipant::create([
            'meeting_id' => $meetingId,
            'user_id' => $data['user_id'] ?? null,
            'email' => $data['email'],
            'name' => $data['name'],
            'role' => $data['role'] ?? 'attendee',
            'status' => 'invited',
        ]);
    }

    /**
     * Update participant details.
     */
    public function updateParticipant(int $participantId, array $data): VideoMeetingParticipant
    {
        $participant = VideoMeetingParticipant::findOrFail($participantId);

        $updateData = [];

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

        $participant->update($updateData);

        return $participant->fresh();
    }

    /**
     * Mark participant as joined.
     */
    public function markParticipantJoined(int $participantId, array $data = []): VideoMeetingParticipant
    {
        $participant = VideoMeetingParticipant::findOrFail($participantId);

        $participant->update([
            'status' => 'joined',
            'joined_at' => now(),
            'device_type' => $data['device_type'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'location' => $data['location'] ?? null,
        ]);

        return $participant->fresh();
    }

    /**
     * Mark participant as left.
     */
    public function markParticipantLeft(int $participantId): VideoMeetingParticipant
    {
        $participant = VideoMeetingParticipant::findOrFail($participantId);

        $duration = null;
        if ($participant->joined_at) {
            $duration = now()->diffInSeconds($participant->joined_at);
        }

        $participant->update([
            'status' => 'left',
            'left_at' => now(),
            'duration_seconds' => $duration,
        ]);

        return $participant->fresh();
    }

    /**
     * Mark participant as no-show.
     */
    public function markParticipantNoShow(int $participantId): VideoMeetingParticipant
    {
        $participant = VideoMeetingParticipant::findOrFail($participantId);

        $participant->update(['status' => 'no_show']);

        return $participant->fresh();
    }

    /**
     * Remove a participant from a meeting.
     */
    public function removeParticipant(int $participantId): bool
    {
        $participant = VideoMeetingParticipant::findOrFail($participantId);
        return $participant->delete();
    }

    /**
     * Get participants for a meeting.
     */
    public function getParticipants(int $meetingId): Collection
    {
        return VideoMeetingParticipant::where('meeting_id', $meetingId)
            ->with('user:id,name,email')
            ->orderBy('role')
            ->orderBy('name')
            ->get();
    }

    // =========================================================================
    // RECORDING USE CASES
    // =========================================================================

    /**
     * Add a recording to a meeting.
     */
    public function addRecording(int $meetingId, array $data): VideoMeetingRecording
    {
        return VideoMeetingRecording::create([
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
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Update a recording.
     */
    public function updateRecording(int $recordingId, array $data): VideoMeetingRecording
    {
        $recording = VideoMeetingRecording::findOrFail($recordingId);

        $updateData = [];

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
        if (isset($data['transcript_segments'])) $updateData['transcript_segments'] = $data['transcript_segments'];
        if (isset($data['metadata'])) $updateData['metadata'] = array_merge($recording->metadata ?? [], $data['metadata']);

        $recording->update($updateData);

        return $recording->fresh();
    }

    /**
     * Delete a recording.
     */
    public function deleteRecording(int $recordingId): bool
    {
        $recording = VideoMeetingRecording::findOrFail($recordingId);
        return $recording->delete();
    }

    /**
     * Get recordings for a meeting.
     */
    public function getRecordings(int $meetingId): Collection
    {
        return VideoMeetingRecording::where('meeting_id', $meetingId)
            ->orderBy('recording_start', 'desc')
            ->get();
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get meeting statistics for a host.
     */
    public function getMeetingStats(?int $hostId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = VideoMeeting::query();

        if ($hostId) {
            $query->forHost($hostId);
        } else {
            $query->forHost(Auth::id());
        }

        if ($fromDate) {
            $query->where('scheduled_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('scheduled_at', '<=', $toDate);
        }

        $total = $query->count();
        $scheduled = (clone $query)->scheduled()->count();
        $started = (clone $query)->started()->count();
        $ended = (clone $query)->ended()->count();
        $canceled = (clone $query)->where('status', 'canceled')->count();

        $totalDuration = (clone $query)->ended()->sum('actual_duration_seconds');
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

    /**
     * Get participant statistics for a meeting.
     */
    public function getParticipantStats(int $meetingId): array
    {
        $participants = VideoMeetingParticipant::where('meeting_id', $meetingId);

        $total = $participants->count();
        $joined = (clone $participants)->joined()->count();
        $noShow = (clone $participants)->noShow()->count();

        $totalDuration = (clone $participants)->where('status', 'left')->sum('duration_seconds');
        $avgDuration = $joined > 0 ? $totalDuration / $joined : 0;

        $avgAttentiveness = (clone $participants)
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

    /**
     * Get daily meeting count for dashboard.
     */
    public function getDailyMeetingCount(?int $hostId = null, int $days = 30): Collection
    {
        $query = VideoMeeting::query()
            ->selectRaw('DATE(scheduled_at) as date, COUNT(*) as count')
            ->where('scheduled_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date');

        if ($hostId) {
            $query->forHost($hostId);
        } else {
            $query->forHost(Auth::id());
        }

        return $query->get();
    }

    /**
     * Get provider usage statistics.
     */
    public function getProviderUsageStats(?string $fromDate = null, ?string $toDate = null): array
    {
        $query = VideoMeeting::query()
            ->selectRaw('provider_id, COUNT(*) as meeting_count, SUM(actual_duration_seconds) as total_duration')
            ->with('provider:id,name,provider')
            ->groupBy('provider_id');

        if ($fromDate) {
            $query->where('scheduled_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('scheduled_at', '<=', $toDate);
        }

        $results = $query->get();

        return [
            'by_provider' => $results->map(function ($item) {
                return [
                    'provider_name' => $item->provider?->name,
                    'provider_type' => $item->provider?->provider,
                    'meeting_count' => $item->meeting_count,
                    'total_duration_seconds' => $item->total_duration ?? 0,
                ];
            })->toArray(),
            'total_meetings' => $results->sum('meeting_count'),
            'total_duration_seconds' => $results->sum('total_duration'),
        ];
    }

    /**
     * Get recording statistics.
     */
    public function getRecordingStats(?string $fromDate = null, ?string $toDate = null): array
    {
        $query = VideoMeetingRecording::query();

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $total = $query->count();
        $completed = (clone $query)->completed()->count();
        $processing = (clone $query)->processing()->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        $totalSize = (clone $query)->completed()->sum('file_size');
        $totalDuration = (clone $query)->completed()->sum('duration_seconds');

        $byType = VideoMeetingRecording::query()
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
}
