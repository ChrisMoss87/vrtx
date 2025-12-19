<?php

declare(strict_types=1);

namespace App\Application\Services\Call;

use App\Domain\Call\Repositories\CallRepositoryInterface;
use App\Models\Call;
use App\Models\CallProvider;
use App\Models\CallTranscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CallApplicationService
{
    public function __construct(
        private CallRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - CALLS
    // =========================================================================

    /**
     * List calls with filtering and pagination.
     */
    public function listCalls(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Call::query()
            ->with(['user:id,name,email', 'provider:id,name,provider', 'contact']);

        // Filter by direction
        if (!empty($filters['direction'])) {
            if ($filters['direction'] === 'inbound') {
                $query->inbound();
            } elseif ($filters['direction'] === 'outbound') {
                $query->outbound();
            }
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by missed
        if (!empty($filters['missed'])) {
            $query->missed();
        }

        // Filter by completed
        if (!empty($filters['completed'])) {
            $query->completed();
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }

        // Filter by contact
        if (!empty($filters['contact_id'])) {
            $query->forContact($filters['contact_id']);
        }

        // Filter by provider
        if (!empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }

        // Filter by phone number
        if (!empty($filters['phone_number'])) {
            $phone = $filters['phone_number'];
            $query->where(function ($q) use ($phone) {
                $q->where('from_number', 'like', "%{$phone}%")
                    ->orWhere('to_number', 'like', "%{$phone}%");
            });
        }

        // Filter by outcome
        if (!empty($filters['outcome'])) {
            $query->where('outcome', $filters['outcome']);
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Filter by recording availability
        if (isset($filters['has_recording'])) {
            if ($filters['has_recording']) {
                $query->withRecording();
            } else {
                $query->whereNull('recording_url');
            }
        }

        // Filter by transcription availability
        if (isset($filters['has_transcription'])) {
            if ($filters['has_transcription']) {
                $query->whereHas('transcription');
            } else {
                $query->whereDoesntHave('transcription');
            }
        }

        // Search in notes
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhere('from_number', 'like', "%{$search}%")
                    ->orWhere('to_number', 'like', "%{$search}%");
            });
        }

        // Today's calls
        if (!empty($filters['today'])) {
            $query->today();
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single call by ID.
     */
    public function getCall(int $id): ?Call
    {
        return Call::with(['user:id,name,email', 'provider', 'contact', 'transcription'])->find($id);
    }

    /**
     * Get calls for a specific contact.
     */
    public function getContactCalls(int $contactId, int $limit = 50): Collection
    {
        return Call::forContact($contactId)
            ->with(['user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get today's calls for current user.
     */
    public function getTodayCalls(?int $userId = null): Collection
    {
        $query = Call::today()
            ->with(['contact', 'provider:id,name,provider'])
            ->orderBy('created_at', 'desc');

        if ($userId) {
            $query->forUser($userId);
        }

        return $query->get();
    }

    /**
     * Get recent calls for current user.
     */
    public function getRecentCalls(?int $userId = null, int $limit = 20): Collection
    {
        $query = Call::with(['contact', 'provider:id,name,provider'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($userId) {
            $query->forUser($userId);
        }

        return $query->get();
    }

    /**
     * Get call statistics.
     */
    public function getCallStats(?int $userId = null, ?string $period = 'today'): array
    {
        $query = Call::query();

        if ($userId) {
            $query->forUser($userId);
        }

        // Apply period filter
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfDay(),
        };

        $query->where('created_at', '>=', $startDate);

        $baseQuery = clone $query;

        $total = $baseQuery->count();
        $inbound = (clone $query)->inbound()->count();
        $outbound = (clone $query)->outbound()->count();
        $completed = (clone $query)->completed()->count();
        $missed = (clone $query)->missed()->count();
        $avgDuration = (clone $query)->completed()->avg('duration_seconds') ?? 0;
        $totalDuration = (clone $query)->completed()->sum('duration_seconds');

        $byOutcome = (clone $query)
            ->selectRaw('outcome, COUNT(*) as count')
            ->whereNotNull('outcome')
            ->groupBy('outcome')
            ->pluck('count', 'outcome')
            ->toArray();

        return [
            'total' => $total,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'completed' => $completed,
            'missed' => $missed,
            'average_duration_seconds' => round($avgDuration),
            'total_duration_seconds' => $totalDuration,
            'by_outcome' => $byOutcome,
            'answer_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get hourly call distribution.
     */
    public function getHourlyDistribution(?int $userId = null, int $days = 7): Collection
    {
        $query = Call::query()
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('hour')
            ->orderBy('hour');

        if ($userId) {
            $query->forUser($userId);
        }

        return $query->get();
    }

    /**
     * Get calls by day for charting.
     */
    public function getCallsByDay(?int $userId = null, int $days = 30): Collection
    {
        $query = Call::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date');

        if ($userId) {
            $query->forUser($userId);
        }

        return $query->get();
    }

    // =========================================================================
    // COMMAND USE CASES - CALLS
    // =========================================================================

    /**
     * Create a new call record (typically from webhook).
     */
    public function createCall(array $data): Call
    {
        return Call::create([
            'provider_id' => $data['provider_id'],
            'external_call_id' => $data['external_call_id'] ?? null,
            'direction' => $data['direction'],
            'status' => $data['status'] ?? 'in_progress',
            'from_number' => $data['from_number'],
            'to_number' => $data['to_number'],
            'user_id' => $data['user_id'] ?? Auth::id(),
            'contact_id' => $data['contact_id'] ?? null,
            'contact_module' => $data['contact_module'] ?? null,
            'started_at' => $data['started_at'] ?? now(),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Update call when answered.
     */
    public function callAnswered(int $callId): Call
    {
        $call = Call::findOrFail($callId);

        $call->update([
            'status' => 'in_progress',
            'answered_at' => now(),
        ]);

        return $call->fresh();
    }

    /**
     * Complete a call.
     */
    public function completeCall(int $callId, array $data = []): Call
    {
        $call = Call::findOrFail($callId);

        $call->markAsCompleted([
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'recording_url' => $data['recording_url'] ?? null,
            'recording_sid' => $data['recording_sid'] ?? null,
            'recording_duration_seconds' => $data['recording_duration_seconds'] ?? null,
        ]);

        return $call->fresh();
    }

    /**
     * Log call outcome and notes.
     */
    public function logOutcome(int $callId, string $outcome, ?string $notes = null): Call
    {
        $call = Call::findOrFail($callId);
        $call->logOutcome($outcome, $notes);
        return $call->fresh();
    }

    /**
     * Link call to a contact.
     */
    public function linkToContact(int $callId, int $contactId, string $module = 'contacts'): Call
    {
        $call = Call::findOrFail($callId);
        $call->linkToContact($contactId, $module);
        return $call->fresh();
    }

    /**
     * Update call recording info.
     */
    public function updateRecording(int $callId, array $data): Call
    {
        $call = Call::findOrFail($callId);

        $call->update([
            'recording_url' => $data['recording_url'] ?? $call->recording_url,
            'recording_sid' => $data['recording_sid'] ?? $call->recording_sid,
            'recording_duration_seconds' => $data['recording_duration_seconds'] ?? $call->recording_duration_seconds,
            'recording_status' => $data['recording_status'] ?? $call->recording_status,
        ]);

        return $call->fresh();
    }

    /**
     * Mark call as missed/no answer.
     */
    public function markMissed(int $callId, string $reason = 'no_answer'): Call
    {
        $call = Call::findOrFail($callId);

        $call->update([
            'status' => $reason,
            'ended_at' => now(),
        ]);

        return $call->fresh();
    }

    /**
     * Add notes to a call.
     */
    public function addNotes(int $callId, string $notes): Call
    {
        $call = Call::findOrFail($callId);

        $existingNotes = $call->notes;
        $newNotes = $existingNotes
            ? $existingNotes . "\n\n---\n\n" . $notes
            : $notes;

        $call->update(['notes' => $newNotes]);

        return $call->fresh();
    }

    /**
     * Update call custom fields.
     */
    public function updateCustomFields(int $callId, array $customFields): Call
    {
        $call = Call::findOrFail($callId);

        $call->update([
            'custom_fields' => array_merge($call->custom_fields ?? [], $customFields),
        ]);

        return $call->fresh();
    }

    /**
     * Delete a call.
     */
    public function deleteCall(int $callId): bool
    {
        $call = Call::findOrFail($callId);
        return $call->delete();
    }

    // =========================================================================
    // QUERY USE CASES - PROVIDERS
    // =========================================================================

    /**
     * List call providers.
     */
    public function listProviders(bool $activeOnly = false): Collection
    {
        $query = CallProvider::query();

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a provider by ID.
     */
    public function getProvider(int $id): ?CallProvider
    {
        return CallProvider::find($id);
    }

    /**
     * Get provider by type.
     */
    public function getProviderByType(string $provider): ?CallProvider
    {
        return CallProvider::where('provider', $provider)
            ->active()
            ->first();
    }

    // =========================================================================
    // COMMAND USE CASES - PROVIDERS
    // =========================================================================

    /**
     * Create a new call provider.
     */
    public function createProvider(array $data): CallProvider
    {
        return CallProvider::create([
            'name' => $data['name'],
            'provider' => $data['provider'],
            'api_key' => $data['api_key'] ?? null,
            'api_secret' => $data['api_secret'] ?? null,
            'auth_token' => $data['auth_token'] ?? null,
            'account_sid' => $data['account_sid'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'webhook_url' => $data['webhook_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'recording_enabled' => $data['recording_enabled'] ?? true,
            'transcription_enabled' => $data['transcription_enabled'] ?? false,
            'settings' => $data['settings'] ?? [],
        ]);
    }

    /**
     * Update a call provider.
     */
    public function updateProvider(int $id, array $data): CallProvider
    {
        $provider = CallProvider::findOrFail($id);

        $provider->update([
            'name' => $data['name'] ?? $provider->name,
            'api_key' => $data['api_key'] ?? $provider->api_key,
            'api_secret' => $data['api_secret'] ?? $provider->api_secret,
            'auth_token' => $data['auth_token'] ?? $provider->auth_token,
            'account_sid' => $data['account_sid'] ?? $provider->account_sid,
            'phone_number' => $data['phone_number'] ?? $provider->phone_number,
            'webhook_url' => $data['webhook_url'] ?? $provider->webhook_url,
            'is_active' => $data['is_active'] ?? $provider->is_active,
            'recording_enabled' => $data['recording_enabled'] ?? $provider->recording_enabled,
            'transcription_enabled' => $data['transcription_enabled'] ?? $provider->transcription_enabled,
            'settings' => array_merge($provider->settings ?? [], $data['settings'] ?? []),
        ]);

        return $provider->fresh();
    }

    /**
     * Verify a provider configuration.
     */
    public function verifyProvider(int $id): array
    {
        $provider = CallProvider::findOrFail($id);

        // This would typically make an API call to verify credentials
        // For now, just mark as verified
        $provider->update([
            'is_verified' => true,
            'last_synced_at' => now(),
        ]);

        return [
            'verified' => true,
            'provider' => $provider->fresh(),
        ];
    }

    /**
     * Activate/deactivate a provider.
     */
    public function toggleProviderActive(int $id): CallProvider
    {
        $provider = CallProvider::findOrFail($id);

        $provider->update([
            'is_active' => !$provider->is_active,
        ]);

        return $provider->fresh();
    }

    /**
     * Delete a provider.
     */
    public function deleteProvider(int $id): bool
    {
        $provider = CallProvider::findOrFail($id);

        // Check if there are calls using this provider
        if ($provider->calls()->exists()) {
            throw new \InvalidArgumentException('Cannot delete provider with existing calls');
        }

        return $provider->delete();
    }

    // =========================================================================
    // QUERY USE CASES - TRANSCRIPTIONS
    // =========================================================================

    /**
     * Get transcription for a call.
     */
    public function getTranscription(int $callId): ?CallTranscription
    {
        return CallTranscription::where('call_id', $callId)->first();
    }

    /**
     * List pending transcriptions.
     */
    public function getPendingTranscriptions(int $limit = 50): Collection
    {
        return CallTranscription::pending()
            ->with('call')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - TRANSCRIPTIONS
    // =========================================================================

    /**
     * Request transcription for a call.
     */
    public function requestTranscription(int $callId): CallTranscription
    {
        $call = Call::findOrFail($callId);

        if (!$call->hasRecording()) {
            throw new \InvalidArgumentException('Call has no recording');
        }

        // Check if transcription already exists
        $existing = $call->transcription;
        if ($existing) {
            return $existing;
        }

        return CallTranscription::create([
            'call_id' => $callId,
            'status' => 'pending',
        ]);
    }

    /**
     * Start processing a transcription.
     */
    public function startTranscription(int $transcriptionId): CallTranscription
    {
        $transcription = CallTranscription::findOrFail($transcriptionId);
        $transcription->markAsProcessing();
        return $transcription->fresh();
    }

    /**
     * Complete a transcription with results.
     */
    public function completeTranscription(int $transcriptionId, array $data): CallTranscription
    {
        $transcription = CallTranscription::findOrFail($transcriptionId);

        $transcription->markAsCompleted([
            'full_text' => $data['full_text'],
            'segments' => $data['segments'] ?? null,
            'language' => $data['language'] ?? 'en',
            'confidence' => $data['confidence'] ?? null,
            'provider' => $data['provider'] ?? 'default',
            'summary' => $data['summary'] ?? null,
            'key_points' => $data['key_points'] ?? null,
            'action_items' => $data['action_items'] ?? null,
            'sentiment' => $data['sentiment'] ?? null,
            'entities' => $data['entities'] ?? null,
            'word_count' => str_word_count($data['full_text']),
        ]);

        return $transcription->fresh();
    }

    /**
     * Mark transcription as failed.
     */
    public function failTranscription(int $transcriptionId, string $error): CallTranscription
    {
        $transcription = CallTranscription::findOrFail($transcriptionId);
        $transcription->markAsFailed($error);
        return $transcription->fresh();
    }

    /**
     * Retry failed transcription.
     */
    public function retryTranscription(int $transcriptionId): CallTranscription
    {
        $transcription = CallTranscription::findOrFail($transcriptionId);

        if (!$transcription->isFailed()) {
            throw new \InvalidArgumentException('Transcription is not in failed state');
        }

        $transcription->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        return $transcription->fresh();
    }

    /**
     * Search transcriptions by text.
     */
    public function searchTranscriptions(string $query, int $perPage = 25): LengthAwarePaginator
    {
        return CallTranscription::query()
            ->with('call')
            ->completed()
            ->where(function ($q) use ($query) {
                $q->where('full_text', 'like', "%{$query}%")
                    ->orWhere('summary', 'like', "%{$query}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get call analytics for a user or team.
     */
    public function getAnalytics(array $filters = []): array
    {
        $query = Call::query();

        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $baseQuery = clone $query;

        // Basic stats
        $totalCalls = $baseQuery->count();
        $completedCalls = (clone $query)->completed()->count();
        $missedCalls = (clone $query)->missed()->count();
        $avgDuration = (clone $query)->completed()->avg('duration_seconds') ?? 0;

        // Direction breakdown
        $inboundCalls = (clone $query)->inbound()->count();
        $outboundCalls = (clone $query)->outbound()->count();

        // Calls with recordings
        $callsWithRecording = (clone $query)->withRecording()->count();
        $callsWithTranscription = (clone $query)->whereHas('transcription', function ($q) {
            $q->completed();
        })->count();

        // Top performers (by call count)
        $topPerformers = (clone $query)
            ->selectRaw('user_id, COUNT(*) as call_count, AVG(duration_seconds) as avg_duration')
            ->groupBy('user_id')
            ->orderByDesc('call_count')
            ->limit(10)
            ->with('user:id,name')
            ->get();

        // Outcome distribution
        $outcomeDistribution = (clone $query)
            ->selectRaw('outcome, COUNT(*) as count')
            ->whereNotNull('outcome')
            ->groupBy('outcome')
            ->pluck('count', 'outcome')
            ->toArray();

        return [
            'total_calls' => $totalCalls,
            'completed_calls' => $completedCalls,
            'missed_calls' => $missedCalls,
            'average_duration_seconds' => round($avgDuration),
            'inbound_calls' => $inboundCalls,
            'outbound_calls' => $outboundCalls,
            'calls_with_recording' => $callsWithRecording,
            'calls_with_transcription' => $callsWithTranscription,
            'answer_rate' => $totalCalls > 0 ? round(($completedCalls / $totalCalls) * 100, 1) : 0,
            'recording_rate' => $completedCalls > 0 ? round(($callsWithRecording / $completedCalls) * 100, 1) : 0,
            'top_performers' => $topPerformers,
            'outcome_distribution' => $outcomeDistribution,
        ];
    }
}
