<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

interface ChatMessageRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByConversationId(int $conversationId): array;

    public function create(int $conversationId, string $content, string $senderType, ?int $senderId = null, array $options = []): array;

    public function markAsRead(int $conversationId, string $readerType): int;

    public function deleteByConversationId(int $conversationId): int;

    public function deleteByWidgetId(int $widgetId): int;
}
