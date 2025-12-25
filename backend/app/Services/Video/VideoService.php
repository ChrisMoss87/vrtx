<?php

namespace App\Services\Video;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class VideoService
{
    protected function getProviderService(VideoProvider $provider): ZoomService
    {
        return match ($provider->provider) {
            'zoom' => new ZoomService($provider),
            // 'teams' => new TeamsService($provider),
            // 'google_meet' => new GoogleMeetService($provider),
            // 'webex' => new WebexService($provider),
            default => throw new \Exception("Unsupported video provider: {$provider->provider}"),
        };
    }

    public function createMeeting(VideoProvider $provider, User $host, array $data): VideoMeeting
    {
        $service = $this->getProviderService($provider);

        // Create meeting with provider
        $providerResponse = $service->createMeeting(array_merge($data, [
            'host_zoom_id' => $host->zoom_user_id ?? 'me',
        ]));

        // Create local meeting record
        $meeting = DB::table('video_meetings')->insertGetId([
            'provider_id' => $provider->id,
            'external_meeting_id' => $providerResponse['external_meeting_id'],
            'host_id' => $host->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'scheduled',
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'join_url' => $providerResponse['join_url'],
            'host_url' => $providerResponse['host_url'],
            'password' => $providerResponse['password'],
            'waiting_room_enabled' => $data['waiting_room_enabled'] ?? true,
            'recording_enabled' => $data['recording_enabled'] ?? false,
            'recording_auto_start' => $data['recording_auto_start'] ?? false,
            'meeting_type' => $data['meeting_type'] ?? 'scheduled',
            'recurrence_type' => $data['recurrence_type'] ?? null,
            'recurrence_settings' => $data['recurrence_settings'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'deal_module' => $data['deal_module'] ?? null,
            'custom_fields' => $data['custom_fields'] ?? null,
        ]);

        // Add host as participant
        $meeting->participants()->create([
            'user_id' => $host->id,
            'email' => $host->email,
            'name' => $host->name,
            'role' => 'host',
            'status' => 'invited',
        ]);

        // Add invited participants
        if (!empty($data['participants'])) {
            foreach ($data['participants'] as $participant) {
                $this->addParticipant($meeting, $participant);
            }
        }

        return $meeting->fresh(['provider', 'host', 'participants']);
    }

    public function updateMeeting(VideoMeeting $meeting, array $data): VideoMeeting
    {
        $service = $this->getProviderService($meeting->provider);

        // Update with provider if external meeting exists
        if ($meeting->external_meeting_id) {
            $service->updateMeeting($meeting->external_meeting_id, $data);
        }

        // Update local record
        $meeting->update([
            'title' => $data['title'] ?? $meeting->title,
            'description' => $data['description'] ?? $meeting->description,
            'scheduled_at' => $data['scheduled_at'] ?? $meeting->scheduled_at,
            'duration_minutes' => $data['duration_minutes'] ?? $meeting->duration_minutes,
            'waiting_room_enabled' => $data['waiting_room_enabled'] ?? $meeting->waiting_room_enabled,
            'recording_enabled' => $data['recording_enabled'] ?? $meeting->recording_enabled,
            'recording_auto_start' => $data['recording_auto_start'] ?? $meeting->recording_auto_start,
        ]);

        return $meeting->fresh(['provider', 'host', 'participants']);
    }

    public function cancelMeeting(VideoMeeting $meeting): VideoMeeting
    {
        $service = $this->getProviderService($meeting->provider);

        // Delete from provider
        if ($meeting->external_meeting_id) {
            try {
                $service->deleteMeeting($meeting->external_meeting_id);
            } catch (\Exception $e) {
                Log::warning('Failed to delete meeting from provider', [
                    'meeting_id' => $meeting->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update local status
        $meeting->update(['status' => 'canceled']);

        return $meeting->fresh();
    }

    public function endMeeting(VideoMeeting $meeting): VideoMeeting
    {
        $service = $this->getProviderService($meeting->provider);

        // End on provider
        if ($meeting->external_meeting_id && $meeting->status === 'started') {
            try {
                $service->endMeeting($meeting->external_meeting_id);
            } catch (\Exception $e) {
                Log::warning('Failed to end meeting on provider', [
                    'meeting_id' => $meeting->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update local status
        $meeting->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        // Mark no-shows
        $meeting->participants()
            ->where('status', 'invited')
            ->update(['status' => 'no_show']);

        return $meeting->fresh(['participants']);
    }

    public function addParticipant(VideoMeeting $meeting, array $data): VideoMeetingParticipant
    {
        // Check if participant already exists
        $existing = $meeting->participants()
            ->where('email', $data['email'])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Find user by email if exists
        $user = User::where('email', $data['email'])->first();

        // Add to provider if supported
        if ($meeting->external_meeting_id) {
            try {
                $service = $this->getProviderService($meeting->provider);
                $service->addParticipant($meeting->external_meeting_id, [
                    'email' => $data['email'],
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to add participant to provider', [
                    'meeting_id' => $meeting->id,
                    'email' => $data['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $meeting->participants()->create([
            'user_id' => $user?->id,
            'email' => $data['email'],
            'name' => $data['name'] ?? ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''),
            'role' => $data['role'] ?? 'attendee',
            'status' => 'invited',
        ]);
    }

    public function removeParticipant(VideoMeeting $meeting, VideoMeetingParticipant $participant): void
    {
        // Cannot remove host
        if ($participant->isHost()) {
            throw new \Exception('Cannot remove meeting host');
        }

        $participant->delete();
    }

    public function syncRecordings(VideoMeeting $meeting): void
    {
        if (!$meeting->external_meeting_id) {
            return;
        }

        $service = $this->getProviderService($meeting->provider);
        $recordings = $service->getRecordings($meeting->external_meeting_id);

        foreach ($recordings as $recording) {
            $meeting->recordings()->updateOrCreate(
                ['external_recording_id' => $recording['id']],
                [
                    'type' => $this->mapRecordingType($recording['recording_type'] ?? 'video'),
                    'status' => $recording['status'] ?? 'completed',
                    'file_url' => $recording['recording_url'] ?? null,
                    'download_url' => $recording['download_url'] ?? null,
                    'play_url' => $recording['play_url'] ?? null,
                    'file_size' => $recording['file_size'] ?? null,
                    'format' => $recording['file_extension'] ?? null,
                    'recording_start' => $recording['recording_start'] ?? null,
                    'recording_end' => $recording['recording_end'] ?? null,
                ]
            );
        }
    }

    protected function mapRecordingType(string $type): string
    {
        return match ($type) {
            'shared_screen_with_speaker_view', 'shared_screen_with_gallery_view',
            'speaker_view', 'gallery_view', 'shared_screen' => 'video',
            'audio_only' => 'audio',
            'chat_file' => 'chat',
            'TRANSCRIPT' => 'transcript',
            default => 'video',
        };
    }

    public function syncParticipants(VideoMeeting $meeting): void
    {
        if (!$meeting->external_meeting_id || $meeting->status !== 'ended') {
            return;
        }

        $service = $this->getProviderService($meeting->provider);
        $participants = $service->getParticipants($meeting->external_meeting_id);

        foreach ($participants as $participantData) {
            $participant = $meeting->participants()
                ->where('email', $participantData['user_email'] ?? '')
                ->first();

            if ($participant) {
                $participant->update([
                    'status' => 'left',
                    'joined_at' => $participantData['join_time'] ?? null,
                    'left_at' => $participantData['leave_time'] ?? null,
                    'duration_seconds' => $participantData['duration'] ?? null,
                    'device_type' => $participantData['device'] ?? null,
                    'attentiveness_score' => $participantData['attentiveness_score'] ?? null,
                ]);
            }
        }
    }

    public function deleteRecording(VideoMeetingRecording $recording): void
    {
        $meeting = $recording->meeting;

        if ($meeting->external_meeting_id && $recording->external_recording_id) {
            try {
                $service = $this->getProviderService($meeting->provider);
                $service->deleteRecording($meeting->external_meeting_id, $recording->external_recording_id);
            } catch (\Exception $e) {
                Log::warning('Failed to delete recording from provider', [
                    'recording_id' => $recording->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $recording->delete();
    }

    public function getUpcomingMeetings(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return DB::table('video_meetings')->where(function ($query) use ($user) {
            $query->where('host_id', $user->id)
                ->orWhereHas('participants', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('email', $user->email);
                });
        })
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->with(['provider', 'host', 'participants'])
            ->get();
    }

    public function getMeetingsForDeal(int $dealId, string $module): \Illuminate\Database\Eloquent\Collection
    {
        return DB::table('video_meetings')->where('deal_id', $dealId)
            ->where('deal_module', $module)
            ->orderBy('scheduled_at', 'desc')
            ->with(['provider', 'host', 'participants', 'recordings'])
            ->get();
    }

    public function getMeetingStats(User $user, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table('video_meetings')->where('host_id', $user->id);

        if ($startDate) {
            $query->where('scheduled_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('scheduled_at', '<=', $endDate);
        }

        $meetings = $query->get();

        $totalMeetings = $meetings->count();
        $completedMeetings = $meetings->where('status', 'ended')->count();
        $canceledMeetings = $meetings->where('status', 'canceled')->count();
        $totalDurationSeconds = $meetings->sum('actual_duration_seconds');
        $avgParticipants = $meetings->avg(fn ($m) => $m->participants()->count());

        return [
            'total_meetings' => $totalMeetings,
            'completed_meetings' => $completedMeetings,
            'canceled_meetings' => $canceledMeetings,
            'completion_rate' => $totalMeetings > 0
                ? round(($completedMeetings / $totalMeetings) * 100, 1)
                : 0,
            'total_duration_hours' => round($totalDurationSeconds / 3600, 1),
            'avg_duration_minutes' => $completedMeetings > 0
                ? round(($totalDurationSeconds / $completedMeetings) / 60, 1)
                : 0,
            'avg_participants' => round($avgParticipants ?? 0, 1),
        ];
    }
}
