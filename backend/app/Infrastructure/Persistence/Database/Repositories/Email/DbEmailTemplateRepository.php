<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Email;

use App\Domain\Email\Entities\EmailTemplate;
use App\Domain\Email\Repositories\EmailTemplateRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbEmailTemplateRepository implements EmailTemplateRepositoryInterface
{
    private const TABLE = 'email_templates';

    public function findById(int $id): ?EmailTemplate
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByModuleId(int $moduleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findShared(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_shared', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findByUserId(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('created_by', $userId)
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findActive(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)->orderBy('name')->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function paginate(
        int $page = 1,
        int $perPage = 25,
        array $filters = [],
        string $sortBy = 'name',
        string $sortDirection = 'asc'
    ): PaginatedResult {
        $query = DB::table(self::TABLE);

        $this->applyFilters($query, $filters);

        $total = $query->count();

        $rows = $query
            ->orderBy($sortBy, $sortDirection)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn($row) => $this->toArray($this->toDomainEntity($row)))->all();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function search(string $search, ?int $userId = null): array
    {
        $query = DB::table(self::TABLE);

        $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('subject', 'ilike', "%{$search}%");
        });

        if ($userId !== null) {
            $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere('is_shared', true);
            });
        }

        $query->where('is_active', true);

        $rows = $query->orderBy('name')->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function toArray(EmailTemplate $template): array
    {
        return [
            'id' => $template->getId(),
            'name' => $template->getName(),
            'subject' => $template->getSubject(),
            'body_html' => $template->getBodyHtml(),
            'body_text' => $template->getBodyText(),
            'module_id' => $template->getModuleId(),
            'folder_id' => $template->getFolderId(),
            'is_shared' => $template->isShared(),
            'is_active' => $template->isActive(),
            'variables' => $template->getVariables(),
            'created_by' => $template->getCreatedBy(),
            'created_at' => $template->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $template->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function save(EmailTemplate $template): EmailTemplate
    {
        $data = $this->toRowData($template);

        if ($template->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $template->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $template->getId();
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
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row): EmailTemplate
    {
        return EmailTemplate::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            subject: $row->subject,
            bodyHtml: $row->body_html,
            bodyText: $row->body_text,
            moduleId: $row->module_id ? (int) $row->module_id : null,
            folderId: $row->folder_id ? (int) $row->folder_id : null,
            isShared: (bool) $row->is_shared,
            isActive: (bool) $row->is_active,
            variables: $row->variables ? (is_string($row->variables) ? json_decode($row->variables, true) : $row->variables) : [],
            createdBy: $row->created_by ? (int) $row->created_by : null,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(EmailTemplate $template): array
    {
        return [
            'name' => $template->getName(),
            'subject' => $template->getSubject(),
            'body_html' => $template->getBodyHtml(),
            'body_text' => $template->getBodyText(),
            'module_id' => $template->getModuleId(),
            'is_shared' => $template->isShared(),
            'is_active' => $template->isActive(),
            'variables' => json_encode($template->getVariables()),
        ];
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (isset($filters['is_shared'])) {
            $query->where('is_shared', $filters['is_shared']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('subject', 'ilike', "%{$search}%");
            });
        }
    }
}
