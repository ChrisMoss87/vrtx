<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use App\Domain\Chat\Entities\ChatConversation;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ChatConversationRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?ChatConversation;

    public function save(ChatConversation $entity): ChatConversation;

    public function delete(int $id): bool;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findByIdWithRelations(int $id, array $relations = []): ?array;

    public function findAll(): array;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    public function listConversations(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult;

    public function findActiveConversationForVisitor(int $visitorId): ?array;

    public function findUnassignedConversations(int $limit = 50): array;

    public function findMyConversations(int $userId): array;

    public function assign(int $conversationId, int $userId): array;

    public function unassign(int $conversationId): array;

    public function close(int $conversationId): array;

    public function reopen(int $conversationId): array;

    public function addTags(int $conversationId, array $tags): array;

    public function rate(int $conversationId, float $rating, ?string $comment = null): array;

    public function getConversationStats(?string $startDate = null, ?string $endDate = null): array;

    public function getAgentPerformance(int $userId, ?string $startDate = null, ?string $endDate = null): array;

    public function getHourlyChatVolume(int $days = 7): array;

    public function deleteByWidgetId(int $widgetId): int;
}
