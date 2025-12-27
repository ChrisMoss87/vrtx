<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Chat;

use App\Domain\Chat\Repositories\ChatMessageRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DbChatMessageRepository implements ChatMessageRepositoryInterface
{
    private const TABLE = 'chat_messages';
    private const TABLE_CONVERSATIONS = 'chat_conversations';

    private const SENDER_VISITOR = 'visitor';
    private const SENDER_AGENT = 'agent';
    private const SENDER_SYSTEM = 'system';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByConversationId(int $conversationId): array
    {
        return DB::table(self::TABLE)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();
    }

    public function create(
        int $conversationId,
        string $content,
        string $senderType,
        ?int $senderId = null,
        array $options = []
    ): array {
        $id = DB::table(self::TABLE)->insertGetId([
            'conversation_id' => $conversationId,
            'content' => $content,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'is_internal' => $options['is_internal'] ?? false,
            'attachments' => isset($options['attachments']) ? json_encode($options['attachments']) : null,
            'metadata' => isset($options['metadata']) ? json_encode($options['metadata']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->updateConversationCounters($conversationId, $senderType);

        return $this->findById($id);
    }

    public function markAsRead(int $conversationId, string $readerType): int
    {
        $query = DB::table(self::TABLE)
            ->where('conversation_id', $conversationId)
            ->whereNull('read_at');

        if ($readerType === 'agent') {
            $query->where('sender_type', self::SENDER_VISITOR);
        } else {
            $query->where('sender_type', self::SENDER_AGENT);
        }

        return $query->update(['read_at' => now()]);
    }

    public function deleteByConversationId(int $conversationId): int
    {
        return DB::table(self::TABLE)->where('conversation_id', $conversationId)->delete();
    }

    public function deleteByWidgetId(int $widgetId): int
    {
        return DB::table(self::TABLE)
            ->whereIn('conversation_id', function ($query) use ($widgetId) {
                $query->select('id')
                    ->from(self::TABLE_CONVERSATIONS)
                    ->where('widget_id', $widgetId);
            })
            ->delete();
    }

    private function updateConversationCounters(int $conversationId, string $senderType): void
    {
        $updateData = [
            'message_count' => DB::raw('message_count + 1'),
            'last_message_at' => now(),
            'updated_at' => now(),
        ];

        if ($senderType === self::SENDER_VISITOR) {
            $updateData['visitor_message_count'] = DB::raw('visitor_message_count + 1');
        } elseif ($senderType === self::SENDER_AGENT) {
            $updateData['agent_message_count'] = DB::raw('agent_message_count + 1');

            $conversation = DB::table(self::TABLE_CONVERSATIONS)
                ->where('id', $conversationId)
                ->first();

            if (!$conversation->first_response_at) {
                $updateData['first_response_at'] = now();
            }
        }

        DB::table(self::TABLE_CONVERSATIONS)
            ->where('id', $conversationId)
            ->update($updateData);
    }
}
