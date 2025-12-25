<?php

namespace App\Services\Call;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallService
{
    protected ?TwilioCallService $twilioService = null;

    public function getProviderService(CallProvider $provider): ?TwilioCallService
    {
        if ($provider->isTwilio()) {
            return new TwilioCallService($provider);
        }

        // Add other providers as needed
        return null;
    }

    public function initiateCall(
        CallProvider $provider,
        string $toNumber,
        User $user,
        array $options = []
    ): array {
        $service = $this->getProviderService($provider);

        if (!$service) {
            return ['success' => false, 'error' => 'Unsupported provider'];
        }

        $result = $service->makeCall(
            $toNumber,
            $options['from_number'] ?? $provider->phone_number,
            $options
        );

        if ($result['success']) {
            $call = DB::table('calls')->insertGetId([
                'provider_id' => $provider->id,
                'external_call_id' => $result['external_call_id'],
                'direction' => 'outbound',
                'status' => 'initiated',
                'from_number' => $options['from_number'] ?? $provider->phone_number,
                'to_number' => $toNumber,
                'user_id' => $user->id,
                'contact_id' => $options['contact_id'] ?? null,
                'contact_module' => $options['contact_module'] ?? null,
                'started_at' => now(),
                'metadata' => $options['metadata'] ?? null,
            ]);

            $result['call_id'] = $call->id;
        }

        return $result;
    }

    public function handleInboundCall(
        CallProvider $provider,
        string $fromNumber,
        string $toNumber,
        string $externalCallId,
        array $metadata = []
    ): Call {
        // Try to find contact by phone number
        $contact = $this->findContactByPhone($fromNumber);

        $call = DB::table('calls')->insertGetId([
            'provider_id' => $provider->id,
            'external_call_id' => $externalCallId,
            'direction' => 'inbound',
            'status' => 'ringing',
            'from_number' => $fromNumber,
            'to_number' => $toNumber,
            'contact_id' => $contact?->id,
            'contact_module' => $contact ? 'contacts' : null,
            'started_at' => now(),
            'metadata' => $metadata,
        ]);

        return $call;
    }

    public function routeToQueue(Call $call, CallQueue $queue): ?User
    {
        if (!$queue->is_active) {
            return null;
        }

        if (!$queue->isWithinBusinessHours()) {
            $call->update([
                'status' => 'after_hours',
                'metadata' => array_merge($call->metadata ?? [], [
                    'after_hours_queue' => $queue->id,
                ]),
            ]);
            return null;
        }

        $agent = $queue->getNextAvailableAgent();

        if ($agent) {
            $call->update([
                'user_id' => $agent->id,
                'status' => 'connecting',
                'metadata' => array_merge($call->metadata ?? [], [
                    'queue_id' => $queue->id,
                    'routing_strategy' => $queue->routing_strategy,
                ]),
            ]);

            // Update queue member stats
            $member = $queue->members()->where('user_id', $agent->id)->first();
            if ($member) {
                $member->setBusy();
            }
        }

        return $agent;
    }

    public function endCall(Call $call, array $data = []): void
    {
        $call->update(array_merge([
            'status' => 'completed',
            'ended_at' => now(),
        ], $data));

        if ($call->answered_at) {
            $duration = $call->ended_at->diffInSeconds($call->answered_at);
            $call->update(['duration_seconds' => $duration]);
        }

        // Update queue member status if applicable
        if ($call->user_id && isset($call->metadata['queue_id'])) {
            $member = DB::table('call_queue_members')->where('queue_id', $call->metadata['queue_id'])
                ->where('user_id', $call->user_id)
                ->first();

            if ($member) {
                $member->recordCall();
                $member->goOnline();
            }
        }
    }

    public function updateCallStatus(string $externalCallId, string $status, array $data = []): ?Call
    {
        $call = DB::table('calls')->where('external_call_id', $externalCallId)->first();

        if (!$call) {
            Log::warning('Call not found for status update', ['external_call_id' => $externalCallId]);
            return null;
        }

        $updateData = ['status' => $this->mapExternalStatus($status)];

        if ($status === 'in-progress' && !$call->answered_at) {
            $updateData['answered_at'] = now();
            $updateData['ring_duration_seconds'] = now()->diffInSeconds($call->started_at);
        }

        if (in_array($status, ['completed', 'busy', 'no-answer', 'canceled', 'failed'])) {
            $updateData['ended_at'] = now();

            if ($call->answered_at) {
                $updateData['duration_seconds'] = now()->diffInSeconds($call->answered_at);
            }
        }

        $call->update(array_merge($updateData, $data));

        return $call;
    }

    public function attachRecording(
        string $externalCallId,
        string $recordingUrl,
        string $recordingSid,
        int $duration
    ): ?Call {
        $call = DB::table('calls')->where('external_call_id', $externalCallId)->first();

        if (!$call) {
            Log::warning('Call not found for recording attachment', ['external_call_id' => $externalCallId]);
            return null;
        }

        $call->update([
            'recording_url' => $recordingUrl,
            'recording_sid' => $recordingSid,
            'recording_duration_seconds' => $duration,
            'recording_status' => 'completed',
        ]);

        // Trigger transcription if enabled
        if ($call->provider->transcription_enabled) {
            $this->queueTranscription($call);
        }

        return $call;
    }

    public function queueTranscription(Call $call): void
    {
        if (!$call->recording_url) {
            return;
        }

        $call->transcription()->create([
            'status' => 'pending',
            'provider' => $call->provider->getSetting('transcription_provider', 'openai'),
        ]);

        // Dispatch transcription job
        // TranscribeCallJob::dispatch($call);
    }

    public function logCallOutcome(Call $call, string $outcome, ?string $notes = null): void
    {
        $call->update([
            'outcome' => $outcome,
            'notes' => $notes,
        ]);

        // Create activity record
        if ($call->contact_id) {
            // ActivityService::log($call->contact_module, $call->contact_id, 'call', [
            //     'call_id' => $call->id,
            //     'direction' => $call->direction,
            //     'duration' => $call->duration_seconds,
            //     'outcome' => $outcome,
            // ]);
        }
    }

    public function getCallStats(User $user, ?string $period = 'today'): array
    {
        $query = Call::forUser($user->id);

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
        }

        $calls = $query->get();

        return [
            'total' => $calls->count(),
            'inbound' => $calls->where('direction', 'inbound')->count(),
            'outbound' => $calls->where('direction', 'outbound')->count(),
            'completed' => $calls->where('status', 'completed')->count(),
            'missed' => $calls->whereIn('status', ['no_answer', 'busy', 'canceled'])->count(),
            'total_duration' => $calls->sum('duration_seconds'),
            'avg_duration' => $calls->where('duration_seconds', '>', 0)->avg('duration_seconds') ?? 0,
            'with_recording' => $calls->whereNotNull('recording_url')->count(),
        ];
    }

    public function getQueueStats(CallQueue $queue): array
    {
        $members = $queue->members()->with('user')->get();
        $todayCalls = Call::whereJsonContains('metadata->queue_id', $queue->id)
            ->whereDate('created_at', today())
            ->get();

        return [
            'total_members' => $members->count(),
            'online_members' => $members->where('status', 'online')->count(),
            'busy_members' => $members->where('status', 'busy')->count(),
            'today_calls' => $todayCalls->count(),
            'today_answered' => $todayCalls->where('status', 'completed')->count(),
            'today_missed' => $todayCalls->whereIn('status', ['no_answer', 'busy'])->count(),
            'avg_wait_time' => $todayCalls->where('ring_duration_seconds', '>', 0)
                ->avg('ring_duration_seconds') ?? 0,
            'avg_duration' => $todayCalls->where('duration_seconds', '>', 0)
                ->avg('duration_seconds') ?? 0,
        ];
    }

    protected function mapExternalStatus(string $status): string
    {
        return match ($status) {
            'queued' => 'queued',
            'initiated' => 'initiated',
            'ringing' => 'ringing',
            'in-progress' => 'in_progress',
            'completed' => 'completed',
            'busy' => 'busy',
            'no-answer' => 'no_answer',
            'canceled' => 'canceled',
            'failed' => 'failed',
            default => $status,
        };
    }

    protected function findContactByPhone(string $phone): ?object
    {
        // Clean phone number
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

        // Search in contacts module
        // This would integrate with your ModuleRecord system
        // return ModuleRecord::where('module_api_name', 'contacts')
        //     ->whereJsonContains('data->phone', $cleanPhone)
        //     ->first();

        return null;
    }
}
