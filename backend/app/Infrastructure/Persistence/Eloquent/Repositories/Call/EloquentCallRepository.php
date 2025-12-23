<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Call;

use App\Domain\Call\Entities\Call as CallEntity;
use App\Domain\Call\Repositories\CallRepositoryInterface;
use App\Domain\Call\ValueObjects\CallDirection;
use App\Domain\Call\ValueObjects\CallStatus;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentCallRepository implements CallRepositoryInterface
{
    private const TABLE = 'calls';
    private const PROVIDERS_TABLE = 'call_providers';
    private const TRANSCRIPTIONS_TABLE = 'call_transcriptions';

    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?CallEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(CallEntity $call): CallEntity
    {
        $data = $this->toRowData($call);

        if ($call->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $call->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $call->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $row = DB::table(self::TABLE)
            ->where(self::TABLE . '.id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        // Load user relation
        if ($row->user_id) {
            $user = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('id', $row->user_id)
                ->first();
            $result['user'] = $user ? (array) $user : null;
        }

        // Load provider relation
        if ($row->provider_id) {
            $provider = DB::table(self::PROVIDERS_TABLE)
                ->where('id', $row->provider_id)
                ->first();
            $result['provider'] = $provider ? (array) $provider : null;
        }

        // Load contact relation (polymorphic)
        if ($row->contact_id && $row->contact_module) {
            $contactTable = 'module_records';
            $contact = DB::table($contactTable)
                ->where('id', $row->contact_id)
                ->first();
            $result['contact'] = $contact ? (array) $contact : null;
        }

        // Load transcription relation
        $transcription = DB::table(self::TRANSCRIPTIONS_TABLE)
            ->where('call_id', $id)
            ->first();
        $result['transcription'] = $transcription ? (array) $transcription : null;

        return $result;
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TABLE)->where('id', $id)->first();
    }

    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return (array) DB::table(self::TABLE)->where('id', $id)->first();
    }

    public function delete(int $id): bool
    {
        $affected = DB::table(self::TABLE)
            ->where('id', $id)
            ->delete();

        return $affected > 0;
    }

    // =========================================================================
    // QUERY METHODS - CALLS
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Direction filter
        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Missed calls (no_answer, busy, failed statuses)
        if (!empty($filters['missed'])) {
            $query->whereIn('status', ['no_answer', 'busy', 'failed']);
        }

        // Completed calls
        if (!empty($filters['completed'])) {
            $query->where('status', 'completed');
        }

        // User filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Contact filter
        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        // Provider filter
        if (!empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }

        // Phone number filter
        if (!empty($filters['phone_number'])) {
            $phone = $filters['phone_number'];
            $query->where(function ($q) use ($phone) {
                $q->where('from_number', 'like', "%{$phone}%")
                    ->orWhere('to_number', 'like', "%{$phone}%");
            });
        }

        // Outcome filter
        if (!empty($filters['outcome'])) {
            $query->where('outcome', $filters['outcome']);
        }

        // Date range filters
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        // Recording filter
        if (isset($filters['has_recording'])) {
            if ($filters['has_recording']) {
                $query->whereNotNull('recording_url');
            } else {
                $query->whereNull('recording_url');
            }
        }

        // Transcription filter
        if (isset($filters['has_transcription'])) {
            if ($filters['has_transcription']) {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from(self::TRANSCRIPTIONS_TABLE)
                        ->whereColumn(self::TRANSCRIPTIONS_TABLE . '.call_id', '=', self::TABLE . '.id');
                });
            } else {
                $query->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from(self::TRANSCRIPTIONS_TABLE)
                        ->whereColumn(self::TRANSCRIPTIONS_TABLE . '.call_id', '=', self::TABLE . '.id');
                });
            }
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhere('from_number', 'like', "%{$search}%")
                    ->orWhere('to_number', 'like', "%{$search}%");
            });
        }

        // Today filter
        if (!empty($filters['today'])) {
            $query->whereDate('created_at', now()->toDateString());
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Get page
        $page = $filters['page'] ?? 1;
        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function findForContact(int $contactId, int $limit = 50): array
    {
        return DB::table(self::TABLE)
            ->where('contact_id', $contactId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();
    }

    public function findToday(?int $userId = null): array
    {
        $query = DB::table(self::TABLE)
            ->whereDate('created_at', now()->toDateString())
            ->orderBy('created_at', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($row) => (array) $row)->all();
    }

    public function findRecent(?int $userId = null, int $limit = 20): array
    {
        $query = DB::table(self::TABLE)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($row) => (array) $row)->all();
    }

    public function getStats(?int $userId = null, string $period = 'today'): array
    {
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfDay(),
        };

        $baseQuery = fn() => DB::table(self::TABLE)
            ->where('created_at', '>=', $startDate)
            ->when($userId, fn($q) => $q->where('user_id', $userId));

        $total = $baseQuery()->count();
        $inbound = $baseQuery()->where('direction', 'inbound')->count();
        $outbound = $baseQuery()->where('direction', 'outbound')->count();
        $completed = $baseQuery()->where('status', 'completed')->count();
        $missed = $baseQuery()->whereIn('status', ['no_answer', 'busy', 'failed'])->count();
        $avgDuration = $baseQuery()->where('status', 'completed')->avg('duration_seconds') ?? 0;
        $totalDuration = $baseQuery()->where('status', 'completed')->sum('duration_seconds') ?? 0;

        $byOutcome = $baseQuery()
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
            'total_duration_seconds' => (int) $totalDuration,
            'by_outcome' => $byOutcome,
            'answer_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    public function getHourlyDistribution(?int $userId = null, int $days = 7): array
    {
        $query = DB::table(self::TABLE)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('hour')
            ->orderBy('hour');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($row) => (array) $row)->all();
    }

    public function getCallsByDay(?int $userId = null, int $days = 30): array
    {
        $query = DB::table(self::TABLE)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(fn($row) => (array) $row)->all();
    }

    public function getAnalytics(array $filters = []): array
    {
        $baseQuery = fn() => DB::table(self::TABLE)
            ->when(!empty($filters['user_id']), fn($q) => $q->where('user_id', $filters['user_id']))
            ->when(!empty($filters['from_date']), fn($q) => $q->where('created_at', '>=', $filters['from_date']))
            ->when(!empty($filters['to_date']), fn($q) => $q->where('created_at', '<=', $filters['to_date']));

        $totalCalls = $baseQuery()->count();
        $completedCalls = $baseQuery()->where('status', 'completed')->count();
        $missedCalls = $baseQuery()->whereIn('status', ['no_answer', 'busy', 'failed'])->count();
        $avgDuration = $baseQuery()->where('status', 'completed')->avg('duration_seconds') ?? 0;
        $inboundCalls = $baseQuery()->where('direction', 'inbound')->count();
        $outboundCalls = $baseQuery()->where('direction', 'outbound')->count();
        $callsWithRecording = $baseQuery()->whereNotNull('recording_url')->count();
        $callsWithTranscription = $baseQuery()
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from(self::TRANSCRIPTIONS_TABLE)
                    ->whereColumn(self::TRANSCRIPTIONS_TABLE . '.call_id', '=', self::TABLE . '.id')
                    ->where(self::TRANSCRIPTIONS_TABLE . '.status', '=', 'completed');
            })
            ->count();

        $topPerformers = $baseQuery()
            ->selectRaw('user_id, COUNT(*) as call_count, AVG(duration_seconds) as avg_duration')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('call_count')
            ->limit(10)
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();

        $outcomeDistribution = $baseQuery()
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

    // =========================================================================
    // PROVIDER METHODS
    // =========================================================================

    public function findAllProviders(bool $activeOnly = false): array
    {
        $query = DB::table(self::PROVIDERS_TABLE);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get()->map(fn($row) => (array) $row)->all();
    }

    public function findProviderById(int $id): ?array
    {
        $row = DB::table(self::PROVIDERS_TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findProviderByType(string $provider): ?array
    {
        $row = DB::table(self::PROVIDERS_TABLE)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();
        return $row ? (array) $row : null;
    }

    public function createProvider(array $data): array
    {
        $id = DB::table(self::PROVIDERS_TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::PROVIDERS_TABLE)->where('id', $id)->first();
    }

    public function updateProvider(int $id, array $data): array
    {
        DB::table(self::PROVIDERS_TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return (array) DB::table(self::PROVIDERS_TABLE)->where('id', $id)->first();
    }

    public function deleteProvider(int $id): bool
    {
        $affected = DB::table(self::PROVIDERS_TABLE)
            ->where('id', $id)
            ->delete();

        return $affected > 0;
    }

    public function providerHasCalls(int $providerId): bool
    {
        return DB::table(self::TABLE)
            ->where('provider_id', $providerId)
            ->exists();
    }

    // =========================================================================
    // TRANSCRIPTION METHODS
    // =========================================================================

    public function findTranscriptionByCallId(int $callId): ?array
    {
        $row = DB::table(self::TRANSCRIPTIONS_TABLE)
            ->where('call_id', $callId)
            ->first();
        return $row ? (array) $row : null;
    }

    public function findPendingTranscriptions(int $limit = 50): array
    {
        return DB::table(self::TRANSCRIPTIONS_TABLE)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();
    }

    public function createTranscription(array $data): array
    {
        $id = DB::table(self::TRANSCRIPTIONS_TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (array) DB::table(self::TRANSCRIPTIONS_TABLE)->where('id', $id)->first();
    }

    public function updateTranscription(int $id, array $data): array
    {
        DB::table(self::TRANSCRIPTIONS_TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return (array) DB::table(self::TRANSCRIPTIONS_TABLE)->where('id', $id)->first();
    }

    public function searchTranscriptions(string $query, int $perPage = 25): PaginatedResult
    {
        $queryBuilder = DB::table(self::TRANSCRIPTIONS_TABLE)
            ->where('status', 'completed')
            ->where(function ($q) use ($query) {
                $q->where('full_text', 'like', "%{$query}%")
                    ->orWhere('summary', 'like', "%{$query}%");
            })
            ->orderBy('created_at', 'desc');

        $total = $queryBuilder->count();

        $page = 1; // Default page
        $items = $queryBuilder
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function callHasRecording(int $callId): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $callId)
            ->whereNotNull('recording_url')
            ->exists();
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): CallEntity
    {
        return CallEntity::reconstitute(
            id: (int) $row->id,
            providerId: $row->provider_id ? (int) $row->provider_id : null,
            externalCallId: $row->external_call_id,
            direction: CallDirection::from($row->direction),
            status: CallStatus::from($row->status),
            fromNumber: $row->from_number,
            toNumber: $row->to_number,
            userId: $row->user_id ? (int) $row->user_id : null,
            contactId: $row->contact_id ? (int) $row->contact_id : null,
            contactModule: $row->contact_module,
            durationSeconds: $row->duration_seconds ? (int) $row->duration_seconds : null,
            ringDurationSeconds: $row->ring_duration_seconds ? (int) $row->ring_duration_seconds : null,
            startedAt: $row->started_at ? new DateTimeImmutable($row->started_at) : null,
            answeredAt: $row->answered_at ? new DateTimeImmutable($row->answered_at) : null,
            endedAt: $row->ended_at ? new DateTimeImmutable($row->ended_at) : null,
            recordingUrl: $row->recording_url,
            recordingSid: $row->recording_sid,
            recordingDurationSeconds: $row->recording_duration_seconds ? (int) $row->recording_duration_seconds : null,
            recordingStatus: $row->recording_status,
            notes: $row->notes,
            outcome: $row->outcome,
            customFields: $row->custom_fields ? json_decode($row->custom_fields, true) : [],
            metadata: $row->metadata ? json_decode($row->metadata, true) : [],
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(CallEntity $call): array
    {
        return [
            'provider_id' => $call->getProviderId(),
            'external_call_id' => $call->getExternalCallId(),
            'direction' => $call->getDirection()->value,
            'status' => $call->getStatus()->value,
            'from_number' => $call->getFromNumber(),
            'to_number' => $call->getToNumber(),
            'user_id' => $call->getUserId(),
            'contact_id' => $call->getContactId(),
            'contact_module' => $call->getContactModule(),
            'duration_seconds' => $call->getDurationSeconds(),
            'ring_duration_seconds' => $call->getRingDurationSeconds(),
            'started_at' => $call->getStartedAt()?->format('Y-m-d H:i:s'),
            'answered_at' => $call->getAnsweredAt()?->format('Y-m-d H:i:s'),
            'ended_at' => $call->getEndedAt()?->format('Y-m-d H:i:s'),
            'recording_url' => $call->getRecordingUrl(),
            'recording_sid' => $call->getRecordingSid(),
            'recording_duration_seconds' => $call->getRecordingDurationSeconds(),
            'recording_status' => $call->getRecordingStatus(),
            'notes' => $call->getNotes(),
            'outcome' => $call->getOutcome(),
            'custom_fields' => json_encode($call->getCustomFields()),
            'metadata' => json_encode($call->getMetadata()),
        ];
    }
}
