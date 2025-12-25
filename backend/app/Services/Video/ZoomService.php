<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;

class ZoomService
{
    protected VideoProvider $provider;
    protected string $baseUrl = 'https://api.zoom.us/v2';

    public function __construct(VideoProvider $provider)
    {
        $this->provider = $provider;
    }

    protected function getAccessToken(): string
    {
        // Check if we have a valid access token
        if ($this->provider->access_token && !$this->provider->isTokenExpired()) {
            return $this->provider->access_token;
        }

        // Refresh the token if we have a refresh token
        if ($this->provider->refresh_token) {
            return $this->refreshAccessToken();
        }

        // Generate JWT for Server-to-Server OAuth
        return $this->generateServerToServerToken();
    }

    protected function refreshAccessToken(): string
    {
        $response = Http::asForm()->post('https://zoom.us/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->provider->refresh_token,
            'client_id' => $this->provider->client_id,
            'client_secret' => $this->provider->client_secret,
        ]);

        if ($response->successful()) {
            $data = $response->json();

            $this->provider->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $this->provider->refresh_token,
                'token_expires_at' => now()->addSeconds($data['expires_in']),
            ]);

            return $data['access_token'];
        }

        throw new \Exception('Failed to refresh Zoom access token: ' . $response->body());
    }

    protected function generateServerToServerToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->provider->client_id, $this->provider->client_secret)
            ->post('https://zoom.us/oauth/token', [
                'grant_type' => 'account_credentials',
                'account_id' => $this->provider->api_key, // Account ID stored in api_key
            ]);

        if ($response->successful()) {
            $data = $response->json();

            $this->provider->update([
                'access_token' => $data['access_token'],
                'token_expires_at' => now()->addSeconds($data['expires_in']),
            ]);

            return $data['access_token'];
        }

        throw new \Exception('Failed to generate Zoom server-to-server token: ' . $response->body());
    }

    protected function request(string $method, string $endpoint, array $data = [])
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->{$method}($this->baseUrl . $endpoint, $data);

        if ($response->failed()) {
            Log::error('Zoom API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Zoom API error: ' . $response->body());
        }

        return $response->json();
    }

    public function verifyConnection(): bool
    {
        try {
            $this->request('get', '/users/me');
            return true;
        } catch (\Exception $e) {
            Log::error('Zoom connection verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function createMeeting(array $data): array
    {
        $meetingData = [
            'topic' => $data['title'],
            'type' => $this->getMeetingType($data['meeting_type'] ?? 'scheduled'),
            'start_time' => $data['scheduled_at'],
            'duration' => $data['duration_minutes'] ?? 60,
            'timezone' => config('app.timezone'),
            'agenda' => $data['description'] ?? '',
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'watermark' => false,
                'use_pmi' => false,
                'approval_type' => 0,
                'audio' => 'both',
                'auto_recording' => $data['recording_auto_start'] ?? false ? 'cloud' : 'none',
                'waiting_room' => $data['waiting_room_enabled'] ?? true,
            ],
        ];

        if (isset($data['password'])) {
            $meetingData['password'] = $data['password'];
        }

        if (isset($data['recurrence_type'])) {
            $meetingData['recurrence'] = $this->buildRecurrence($data);
        }

        $userId = $data['host_zoom_id'] ?? 'me';
        $response = $this->request('post', "/users/{$userId}/meetings", $meetingData);

        return [
            'external_meeting_id' => (string) $response['id'],
            'join_url' => $response['join_url'],
            'host_url' => $response['start_url'],
            'password' => $response['password'] ?? null,
        ];
    }

    protected function getMeetingType(string $type): int
    {
        return match ($type) {
            'instant' => 1,
            'scheduled' => 2,
            'recurring' => 8,
            default => 2,
        };
    }

    protected function buildRecurrence(array $data): array
    {
        $recurrence = [
            'type' => match ($data['recurrence_type']) {
                'daily' => 1,
                'weekly' => 2,
                'monthly' => 3,
                default => 1,
            },
        ];

        $settings = $data['recurrence_settings'] ?? [];

        if (isset($settings['repeat_interval'])) {
            $recurrence['repeat_interval'] = $settings['repeat_interval'];
        }

        if (isset($settings['weekly_days'])) {
            $recurrence['weekly_days'] = implode(',', $settings['weekly_days']);
        }

        if (isset($settings['end_date_time'])) {
            $recurrence['end_date_time'] = $settings['end_date_time'];
        }

        if (isset($settings['end_times'])) {
            $recurrence['end_times'] = $settings['end_times'];
        }

        return $recurrence;
    }

    public function updateMeeting(string $meetingId, array $data): array
    {
        $meetingData = [];

        if (isset($data['title'])) {
            $meetingData['topic'] = $data['title'];
        }

        if (isset($data['description'])) {
            $meetingData['agenda'] = $data['description'];
        }

        if (isset($data['scheduled_at'])) {
            $meetingData['start_time'] = $data['scheduled_at'];
        }

        if (isset($data['duration_minutes'])) {
            $meetingData['duration'] = $data['duration_minutes'];
        }

        if (!empty($meetingData)) {
            $this->request('patch', "/meetings/{$meetingId}", $meetingData);
        }

        return $this->getMeeting($meetingId);
    }

    public function getMeeting(string $meetingId): array
    {
        return $this->request('get', "/meetings/{$meetingId}");
    }

    public function deleteMeeting(string $meetingId): void
    {
        $this->request('delete', "/meetings/{$meetingId}");
    }

    public function endMeeting(string $meetingId): void
    {
        $this->request('put', "/meetings/{$meetingId}/status", [
            'action' => 'end',
        ]);
    }

    public function addParticipant(string $meetingId, array $participant): void
    {
        $this->request('post', "/meetings/{$meetingId}/registrants", [
            'email' => $participant['email'],
            'first_name' => $participant['first_name'] ?? '',
            'last_name' => $participant['last_name'] ?? '',
        ]);
    }

    public function getParticipants(string $meetingId): array
    {
        $response = $this->request('get', "/past_meetings/{$meetingId}/participants");
        return $response['participants'] ?? [];
    }

    public function getRecordings(string $meetingId): array
    {
        try {
            $response = $this->request('get', "/meetings/{$meetingId}/recordings");
            return $response['recording_files'] ?? [];
        } catch (\Exception $e) {
            // Meeting may not have recordings yet
            return [];
        }
    }

    public function deleteRecording(string $meetingId, string $recordingId = null): void
    {
        $endpoint = "/meetings/{$meetingId}/recordings";
        if ($recordingId) {
            $endpoint .= "/{$recordingId}";
        }

        $this->request('delete', $endpoint);
    }

    public function getRecordingDownloadUrl(string $recordingFileId): string
    {
        $token = $this->getAccessToken();
        return "https://zoom.us/rec/download/{$recordingFileId}?access_token={$token}";
    }

    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? '';
        $data = $payload['payload']['object'] ?? [];

        return match ($event) {
            'meeting.started' => $this->handleMeetingStarted($data),
            'meeting.ended' => $this->handleMeetingEnded($data),
            'meeting.participant_joined' => $this->handleParticipantJoined($data),
            'meeting.participant_left' => $this->handleParticipantLeft($data),
            'recording.completed' => $this->handleRecordingCompleted($data),
            'recording.transcript_completed' => $this->handleTranscriptCompleted($data),
            default => ['handled' => false, 'event' => $event],
        };
    }

    protected function handleMeetingStarted(array $data): array
    {
        $meeting = DB::table('video_meetings')->where('external_meeting_id', (string) $data['id'])->first();

        if ($meeting) {
            $meeting->update([
                'status' => 'started',
                'started_at' => now(),
            ]);
        }

        return ['handled' => true, 'meeting_id' => $meeting?->id];
    }

    protected function handleMeetingEnded(array $data): array
    {
        $meeting = DB::table('video_meetings')->where('external_meeting_id', (string) $data['id'])->first();

        if ($meeting) {
            $duration = isset($data['duration']) ? $data['duration'] * 60 : null;

            $meeting->update([
                'status' => 'ended',
                'ended_at' => now(),
                'actual_duration_seconds' => $duration,
            ]);
        }

        return ['handled' => true, 'meeting_id' => $meeting?->id];
    }

    protected function handleParticipantJoined(array $data): array
    {
        $meeting = DB::table('video_meetings')->where('external_meeting_id', (string) $data['id'])->first();

        if ($meeting) {
            $participantData = $data['participant'] ?? [];

            $participant = $meeting->participants()
                ->where('email', $participantData['email'] ?? '')
                ->first();

            if ($participant) {
                $participant->update([
                    'status' => 'joined',
                    'joined_at' => now(),
                ]);
            } else {
                $meeting->participants()->create([
                    'email' => $participantData['email'] ?? 'unknown@example.com',
                    'name' => $participantData['user_name'] ?? 'Unknown',
                    'role' => 'attendee',
                    'status' => 'joined',
                    'joined_at' => now(),
                ]);
            }
        }

        return ['handled' => true, 'meeting_id' => $meeting?->id];
    }

    protected function handleParticipantLeft(array $data): array
    {
        $meeting = DB::table('video_meetings')->where('external_meeting_id', (string) $data['id'])->first();

        if ($meeting) {
            $participantData = $data['participant'] ?? [];

            $participant = $meeting->participants()
                ->where('email', $participantData['email'] ?? '')
                ->first();

            if ($participant) {
                $joinedAt = $participant->joined_at;
                $duration = $joinedAt ? now()->diffInSeconds($joinedAt) : null;

                $participant->update([
                    'status' => 'left',
                    'left_at' => now(),
                    'duration_seconds' => $duration,
                ]);
            }
        }

        return ['handled' => true, 'meeting_id' => $meeting?->id];
    }

    protected function handleRecordingCompleted(array $data): array
    {
        $meeting = DB::table('video_meetings')->where('external_meeting_id', (string) $data['id'])->first();

        if ($meeting) {
            $recordingFiles = $data['recording_files'] ?? [];

            foreach ($recordingFiles as $file) {
                $meeting->recordings()->updateOrCreate(
                    ['external_recording_id' => $file['id']],
                    [
                        'type' => $this->mapRecordingType($file['recording_type']),
                        'status' => 'completed',
                        'file_url' => $file['recording_url'] ?? null,
                        'download_url' => $file['download_url'] ?? null,
                        'play_url' => $file['play_url'] ?? null,
                        'file_size' => $file['file_size'] ?? null,
                        'format' => $file['file_extension'] ?? null,
                        'recording_start' => $file['recording_start'] ?? null,
                        'recording_end' => $file['recording_end'] ?? null,
                    ]
                );
            }

            $meeting->update(['recording_status' => 'completed']);
        }

        return ['handled' => true, 'meeting_id' => $meeting?->id];
    }

    protected function handleTranscriptCompleted(array $data): array
    {
        $meeting = DB::table('video_meetings')->where('external_meeting_id', (string) $data['id'])->first();

        if ($meeting) {
            $recordingFiles = $data['recording_files'] ?? [];

            foreach ($recordingFiles as $file) {
                if ($file['file_type'] === 'TRANSCRIPT') {
                    $meeting->recordings()->updateOrCreate(
                        ['external_recording_id' => $file['id']],
                        [
                            'type' => 'transcript',
                            'status' => 'completed',
                            'file_url' => $file['download_url'] ?? null,
                            'download_url' => $file['download_url'] ?? null,
                            'format' => 'vtt',
                        ]
                    );
                }
            }
        }

        return ['handled' => true, 'meeting_id' => $meeting?->id];
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
}
