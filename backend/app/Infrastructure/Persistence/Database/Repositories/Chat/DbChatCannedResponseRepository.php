<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Chat;

use App\Domain\Chat\Repositories\ChatCannedResponseRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DbChatCannedResponseRepository implements ChatCannedResponseRepositoryInterface
{
    private const TABLE = 'chat_canned_responses';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByShortcut(int $userId, string $shortcut): ?array
    {
        $row = DB::table(self::TABLE)
            ->where(function ($query) use ($userId) {
                $query->where('created_by', $userId)
                    ->orWhere('is_global', true);
            })
            ->where('shortcut', $shortcut)
            ->first();

        return $row ? (array) $row : null;
    }

    public function findForUser(int $userId, array $filters = []): Collection
    {
        $query = DB::table(self::TABLE)
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere('is_global', true);
            });

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('shortcut', 'ilike', "%{$search}%")
                    ->orWhere('content', 'ilike', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('usage_count')
            ->get()
            ->map(fn($row) => (array) $row);
    }

    public function create(array $data): int
    {
        return DB::table(self::TABLE)->insertGetId([
            'shortcut' => $data['shortcut'],
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'] ?? null,
            'is_global' => $data['is_global'] ?? false,
            'created_by' => $data['created_by'],
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function update(int $id, array $data): array
    {
        $updateData = ['updated_at' => now()];

        if (isset($data['shortcut'])) {
            $updateData['shortcut'] = $data['shortcut'];
        }
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
        }
        if (isset($data['category'])) {
            $updateData['category'] = $data['category'];
        }
        if (isset($data['is_global'])) {
            $updateData['is_global'] = $data['is_global'];
        }

        DB::table(self::TABLE)->where('id', $id)->update($updateData);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function incrementUsage(int $id): void
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->increment('usage_count');
    }

    public function renderContent(int $id, array $variables = []): string
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return '';
        }

        $content = $row->content;

        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }

        return $content;
    }
}
