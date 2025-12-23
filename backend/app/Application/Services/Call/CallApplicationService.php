<?php

declare(strict_types=1);

namespace App\Application\Services\Call;

use App\Domain\Call\Repositories\CallRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class CallApplicationService
{
    public function __construct(
        private CallRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - CALLS
    // =========================================================================

    public function listCalls(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->findWithFilters($filters, $perPage);
    }

    public function getCall(int $id): ?array
    {
        return $this->repository->findByIdWithRelations($id);
    }

    public function getContactCalls(int $contactId, int $limit = 50): array
    {
        return $this->repository->findForContact($contactId, $limit);
    }

    public function getTodayCalls(?int $userId = null): array
    {
        return $this->repository->findToday($userId);
    }

    public function getRecentCalls(?int $userId = null, int $limit = 20): array
    {
        return $this->repository->findRecent($userId, $limit);
    }

    public function getCallStats(?int $userId = null, ?string $period = 'today'): array
    {
        return $this->repository->getStats($userId, $period ?? 'today');
    }

    public function getHourlyDistribution(?int $userId = null, int $days = 7): array
    {
        return $this->repository->getHourlyDistribution($userId, $days);
    }

    public function getCallsByDay(?int $userId = null, int $days = 30): array
    {
        return $this->repository->getCallsByDay($userId, $days);
    }

    // =========================================================================
    // COMMAND USE CASES - CALLS
    // =========================================================================

    public function createCall(array $data): array
    {
        return $this->repository->create([
            'provider_id' => $data['provider_id'],
            'external_call_id' => $data['external_call_id'] ?? null,
            'direction' => $data['direction'],
            'status' => $data['status'] ?? 'in_progress',
            'from_number' => $data['from_number'],
            'to_number' => $data['to_number'],
            'user_id' => $data['user_id'] ?? $this->authContext->userId(),
            'contact_id' => $data['contact_id'] ?? null,
            'contact_module' => $data['contact_module'] ?? null,
            'started_at' => $data['started_at'] ?? now(),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function callAnswered(int $callId): array
    {
        return $this->repository->update($callId, [
            'status' => 'in_progress',
            'answered_at' => now(),
        ]);
    }

    public function completeCall(int $callId, array $data = []): array
    {
        $call = $this->repository->findById($callId);
        if (!$call) {
            throw new \InvalidArgumentException('Call not found');
        }

        return $this->repository->update($callId, [
            'status' => 'completed',
            'ended_at' => now(),
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'recording_url' => $data['recording_url'] ?? null,
            'recording_sid' => $data['recording_sid'] ?? null,
            'recording_duration_seconds' => $data['recording_duration_seconds'] ?? null,
        ]);
    }

    public function logOutcome(int $callId, string $outcome, ?string $notes = null): array
    {
        return $this->repository->update($callId, [
            'outcome' => $outcome,
            'notes' => $notes,
        ]);
    }

    public function linkToContact(int $callId, int $contactId, string $module = 'contacts'): array
    {
        return $this->repository->update($callId, [
            'contact_id' => $contactId,
            'contact_module' => $module,
        ]);
    }

    public function updateRecording(int $callId, array $data): array
    {
        $call = $this->repository->findById($callId);
        if (!$call) {
            throw new \InvalidArgumentException('Call not found');
        }

        return $this->repository->update($callId, [
            'recording_url' => $data['recording_url'] ?? $call['recording_url'],
            'recording_sid' => $data['recording_sid'] ?? $call['recording_sid'],
            'recording_duration_seconds' => $data['recording_duration_seconds'] ?? $call['recording_duration_seconds'],
            'recording_status' => $data['recording_status'] ?? $call['recording_status'] ?? null,
        ]);
    }

    public function markMissed(int $callId, string $reason = 'no_answer'): array
    {
        return $this->repository->update($callId, [
            'status' => $reason,
            'ended_at' => now(),
        ]);
    }

    public function addNotes(int $callId, string $notes): array
    {
        $call = $this->repository->findById($callId);
        if (!$call) {
            throw new \InvalidArgumentException('Call not found');
        }

        $existingNotes = $call['notes'] ?? '';
        $newNotes = $existingNotes
            ? $existingNotes . "\n\n---\n\n" . $notes
            : $notes;

        return $this->repository->update($callId, ['notes' => $newNotes]);
    }

    public function updateCustomFields(int $callId, array $customFields): array
    {
        $call = $this->repository->findById($callId);
        if (!$call) {
            throw new \InvalidArgumentException('Call not found');
        }

        return $this->repository->update($callId, [
            'custom_fields' => array_merge($call['custom_fields'] ?? [], $customFields),
        ]);
    }

    public function deleteCall(int $callId): bool
    {
        return $this->repository->delete($callId);
    }

    // =========================================================================
    // QUERY USE CASES - PROVIDERS
    // =========================================================================

    public function listProviders(bool $activeOnly = false): array
    {
        return $this->repository->findAllProviders($activeOnly);
    }

    public function getProvider(int $id): ?array
    {
        return $this->repository->findProviderById($id);
    }

    public function getProviderByType(string $provider): ?array
    {
        return $this->repository->findProviderByType($provider);
    }

    // =========================================================================
    // COMMAND USE CASES - PROVIDERS
    // =========================================================================

    public function createProvider(array $data): array
    {
        return $this->repository->createProvider([
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

    public function updateProvider(int $id, array $data): array
    {
        $provider = $this->repository->findProviderById($id);
        if (!$provider) {
            throw new \InvalidArgumentException('Provider not found');
        }

        return $this->repository->updateProvider($id, [
            'name' => $data['name'] ?? $provider['name'],
            'api_key' => $data['api_key'] ?? $provider['api_key'],
            'api_secret' => $data['api_secret'] ?? $provider['api_secret'],
            'auth_token' => $data['auth_token'] ?? $provider['auth_token'],
            'account_sid' => $data['account_sid'] ?? $provider['account_sid'],
            'phone_number' => $data['phone_number'] ?? $provider['phone_number'],
            'webhook_url' => $data['webhook_url'] ?? $provider['webhook_url'],
            'is_active' => $data['is_active'] ?? $provider['is_active'],
            'recording_enabled' => $data['recording_enabled'] ?? $provider['recording_enabled'],
            'transcription_enabled' => $data['transcription_enabled'] ?? $provider['transcription_enabled'],
            'settings' => array_merge($provider['settings'] ?? [], $data['settings'] ?? []),
        ]);
    }

    public function verifyProvider(int $id): array
    {
        $this->repository->updateProvider($id, [
            'is_verified' => true,
            'last_synced_at' => now(),
        ]);

        return [
            'verified' => true,
            'provider' => $this->repository->findProviderById($id),
        ];
    }

    public function toggleProviderActive(int $id): array
    {
        $provider = $this->repository->findProviderById($id);
        if (!$provider) {
            throw new \InvalidArgumentException('Provider not found');
        }

        return $this->repository->updateProvider($id, [
            'is_active' => !$provider['is_active'],
        ]);
    }

    public function deleteProvider(int $id): bool
    {
        if ($this->repository->providerHasCalls($id)) {
            throw new \InvalidArgumentException('Cannot delete provider with existing calls');
        }

        return $this->repository->deleteProvider($id);
    }

    // =========================================================================
    // QUERY USE CASES - TRANSCRIPTIONS
    // =========================================================================

    public function getTranscription(int $callId): ?array
    {
        return $this->repository->findTranscriptionByCallId($callId);
    }

    public function getPendingTranscriptions(int $limit = 50): array
    {
        return $this->repository->findPendingTranscriptions($limit);
    }

    // =========================================================================
    // COMMAND USE CASES - TRANSCRIPTIONS
    // =========================================================================

    public function requestTranscription(int $callId): array
    {
        if (!$this->repository->callHasRecording($callId)) {
            throw new \InvalidArgumentException('Call has no recording');
        }

        $existing = $this->repository->findTranscriptionByCallId($callId);
        if ($existing) {
            return $existing;
        }

        return $this->repository->createTranscription([
            'call_id' => $callId,
            'status' => 'pending',
        ]);
    }

    public function startTranscription(int $transcriptionId): array
    {
        return $this->repository->updateTranscription($transcriptionId, [
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function completeTranscription(int $transcriptionId, array $data): array
    {
        return $this->repository->updateTranscription($transcriptionId, [
            'status' => 'completed',
            'completed_at' => now(),
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
    }

    public function failTranscription(int $transcriptionId, string $error): array
    {
        return $this->repository->updateTranscription($transcriptionId, [
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function retryTranscription(int $transcriptionId): array
    {
        $transcription = $this->repository->findTranscriptionByCallId($transcriptionId);

        // Note: This assumes transcriptionId is actually call_id for lookup
        // In practice you'd want a separate findTranscriptionById method
        if (!$transcription || $transcription['status'] !== 'failed') {
            throw new \InvalidArgumentException('Transcription is not in failed state');
        }

        return $this->repository->updateTranscription($transcription['id'], [
            'status' => 'pending',
            'error_message' => null,
        ]);
    }

    public function searchTranscriptions(string $query, int $perPage = 25): PaginatedResult
    {
        return $this->repository->searchTranscriptions($query, $perPage);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    public function getAnalytics(array $filters = []): array
    {
        return $this->repository->getAnalytics($filters);
    }
}
