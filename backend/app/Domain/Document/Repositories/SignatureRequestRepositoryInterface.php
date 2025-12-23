<?php

declare(strict_types=1);

namespace App\Domain\Document\Repositories;

use App\Domain\Document\Entities\SignatureRequest;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface SignatureRequestRepositoryInterface
{
    public function findById(int $id): ?SignatureRequest;

    public function findAll(): array;

    public function save(SignatureRequest $entity): SignatureRequest;

    public function delete(int $id): bool;

    /**
     * List signature requests with filters and pagination.
     *
     * @param array $filters Filters: status, pending_only, source_type, source_id, created_by, expired_only, search, sort_by, sort_dir
     * @param int $perPage
     * @param int $page
     * @return PaginatedResult
     */
    public function listSignatureRequests(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    /**
     * Get signature request with relationships as array.
     *
     * @param int $id
     * @return array|null
     */
    public function getSignatureRequestWithRelations(int $id): ?array;

    /**
     * Get signature request by UUID as array.
     *
     * @param string $uuid
     * @return array|null
     */
    public function getSignatureRequestByUuid(string $uuid): ?array;

    /**
     * Create signature request from data.
     *
     * @param array $data
     * @param int $userId
     * @return array
     */
    public function createSignatureRequest(array $data, int $userId): array;

    /**
     * Update signature request.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateSignatureRequest(int $id, array $data): array;

    /**
     * Send signature request.
     *
     * @param int $id
     * @return array
     */
    public function sendSignatureRequest(int $id): array;

    /**
     * Void signature request.
     *
     * @param int $id
     * @param string $reason
     * @return array
     */
    public function voidSignatureRequest(int $id, string $reason): array;

    /**
     * Add signer to request.
     *
     * @param int $requestId
     * @param array $data
     * @return array
     */
    public function addSigner(int $requestId, array $data): array;

    /**
     * Remove signer from request.
     *
     * @param int $signerId
     * @return bool
     */
    public function removeSigner(int $signerId): bool;

    /**
     * Record signature.
     *
     * @param string $requestUuid
     * @param string $signerEmail
     * @param array $signatureData
     * @return array
     */
    public function recordSignature(string $requestUuid, string $signerEmail, array $signatureData): array;

    /**
     * Decline signature.
     *
     * @param string $requestUuid
     * @param string $signerEmail
     * @param string $reason
     * @return array
     */
    public function declineSignature(string $requestUuid, string $signerEmail, string $reason): array;

    /**
     * Get audit trail.
     *
     * @param int $requestId
     * @return array
     */
    public function getAuditTrail(int $requestId): array;

    /**
     * Send reminder to signer.
     *
     * @param int $requestId
     * @param int $signerId
     * @return array
     */
    public function sendReminder(int $requestId, int $signerId): array;

    /**
     * Mark expired signature requests.
     *
     * @return int Number of records updated
     */
    public function markExpiredRequests(): int;

    /**
     * Check if request is editable.
     *
     * @param int $id
     * @return bool
     */
    public function isEditable(int $id): bool;

    /**
     * Check if request can be voided.
     *
     * @param int $id
     * @return bool
     */
    public function canBeVoided(int $id): bool;

    /**
     * Check if request is expired.
     *
     * @param int $id
     * @return bool
     */
    public function isExpired(int $id): bool;
}
