<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\AI;

use App\Domain\AI\Entities\AiPrompt as AiPromptEntity;
use App\Domain\AI\Repositories\AiPromptRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

class DbAiPromptRepository implements AiPromptRepositoryInterface
{
    private const TABLE = 'ai_prompts';

    public function findById(int $id): ?AiPromptEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return $rows->map(fn ($row) => $this->toArray($row))->all();
    }

    public function findByFilters(array $filters = []): array
    {
        $query = DB::table(self::TABLE);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['active_only'])) {
            $query->where('is_active', true);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        $rows = $query->orderBy('category')
            ->orderBy('name')
            ->get();

        return $rows->map(fn ($row) => $this->toArray($row))->all();
    }

    public function save(AiPromptEntity $entity): AiPromptEntity
    {
        $data = $this->toRowData($entity);

        if ($entity->getId()) {
            DB::table(self::TABLE)
                ->where('id', $entity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $entity->getId();
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

    public function delete(int $id): bool
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return false;
        }

        if ($row->is_system) {
            throw new RuntimeException('Cannot delete system prompts');
        }

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'system_prompt' => $data['system_prompt'],
            'user_prompt_template' => $data['user_prompt_template'],
            'variables' => json_encode($data['variables'] ?? []),
            'is_system' => false,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toArray($row);
    }

    public function update(int $id, array $data): array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            throw new RuntimeException("AiPrompt not found: {$id}");
        }

        // Don't allow modifying system prompts' core fields
        if ($row->is_system) {
            DB::table(self::TABLE)
                ->where('id', $id)
                ->update([
                    'is_active' => $data['is_active'] ?? $row->is_active,
                    'updated_at' => now(),
                ]);
        } else {
            $updateData = [
                'name' => $data['name'] ?? $row->name,
                'description' => $data['description'] ?? $row->description,
                'system_prompt' => $data['system_prompt'] ?? $row->system_prompt,
                'user_prompt_template' => $data['user_prompt_template'] ?? $row->user_prompt_template,
                'is_active' => $data['is_active'] ?? $row->is_active,
                'updated_at' => now(),
            ];

            if (isset($data['variables'])) {
                $updateData['variables'] = json_encode($data['variables']);
            }

            DB::table(self::TABLE)->where('id', $id)->update($updateData);
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toArray($row);
    }

    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'slug' => $row->slug,
            'name' => $row->name,
            'description' => $row->description,
            'category' => $row->category,
            'system_prompt' => $row->system_prompt,
            'user_prompt_template' => $row->user_prompt_template,
            'variables' => $row->variables ? (is_string($row->variables) ? json_decode($row->variables, true) : $row->variables) : [],
            'is_system' => (bool) $row->is_system,
            'is_active' => (bool) $row->is_active,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Helper to get messages for a prompt
     */
    public function getMessages(array $prompt, array $variables): array
    {
        $userPrompt = $this->renderUserPrompt($prompt['user_prompt_template'], $variables);

        return [
            ['role' => 'system', 'content' => $prompt['system_prompt']],
            ['role' => 'user', 'content' => $userPrompt],
        ];
    }

    /**
     * Render user prompt with variables
     */
    private function renderUserPrompt(string $template, array $variables): string
    {
        $prompt = $template;

        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }
            $prompt = str_replace('{{' . $key . '}}', (string) $value, $prompt);
        }

        return $prompt;
    }

    private function toDomainEntity(stdClass $row): AiPromptEntity
    {
        return AiPromptEntity::reconstitute(
            id: (int) $row->id,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    private function toRowData(AiPromptEntity $entity): array
    {
        $data = [];

        if ($entity->getCreatedAt()) {
            $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');
        }

        if ($entity->getUpdatedAt()) {
            $data['updated_at'] = $entity->getUpdatedAt()->format('Y-m-d H:i:s');
        }

        return $data;
    }
}
