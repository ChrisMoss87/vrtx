<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\AI;

use App\Domain\AI\Repositories\AiEmailDraftRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DbAiEmailDraftRepository implements AiEmailDraftRepositoryInterface
{
    private const TABLE = 'ai_email_drafts';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? $this->formatRow($row) : null;
    }

    public function findByUserId(int $userId, int $limit = 20): array
    {
        return DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($row) => $this->formatRow($row))
            ->all();
    }

    public function create(array $data): int
    {
        return DB::table(self::TABLE)->insertGetId([
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'tone' => $data['tone'] ?? 'professional',
            'context' => isset($data['context']) ? json_encode($data['context']) : null,
            'original_content' => $data['original_content'] ?? null,
            'prompt' => $data['prompt'] ?? null,
            'generated_content' => $data['generated_content'],
            'model_used' => $data['model_used'],
            'tokens_used' => $data['tokens_used'] ?? 0,
            'is_used' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function markAsUsed(int $id): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'is_used' => true,
                'used_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->findById($id);
    }

    private function formatRow(object $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'type' => $row->type,
            'tone' => $row->tone,
            'context' => $row->context ? json_decode($row->context, true) : null,
            'original_content' => $row->original_content,
            'prompt' => $row->prompt,
            'generated_content' => $row->generated_content,
            'model_used' => $row->model_used,
            'tokens_used' => (int) $row->tokens_used,
            'is_used' => (bool) $row->is_used,
            'used_at' => $row->used_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
