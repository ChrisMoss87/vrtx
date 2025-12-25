<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CMS;

use App\Domain\CMS\Entities\CmsForm;
use App\Domain\CMS\Repositories\CmsFormRepositoryInterface;
use App\Domain\CMS\ValueObjects\FormSubmitAction;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbCmsFormRepository implements CmsFormRepositoryInterface
{
    private const TABLE = 'cms_forms';
    private const TABLE_SUBMISSIONS = 'cms_form_submissions';

    public function findById(int $id): ?CmsForm
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toDomainEntity($record);
    }

    public function findByIdAsArray(int $id): ?array
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toArray($record);
    }

    public function findBySlug(string $slug): ?CmsForm
    {
        $record = DB::table(self::TABLE)
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toDomainEntity($record);
    }

    public function findBySlugAsArray(string $slug): ?array
    {
        $record = DB::table(self::TABLE)
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toArray($record);
    }

    public function findActive(): array
    {
        $records = DB::table(self::TABLE)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findAll(): array
    {
        $records = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function paginate(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at');

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['submit_action'])) {
            $query->where('submit_action', $filters['submit_action']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        $records = $query->skip($offset)->take($perPage)->get();

        $items = array_map(fn($record) => $this->toArray($record), $records->all());

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function save(CmsForm $form): CmsForm
    {
        $data = $this->toModelData($form);

        if ($form->getId() !== null) {
            $data['updated_at'] = now();
            DB::table(self::TABLE)
                ->where('id', $form->getId())
                ->update($data);

            $record = DB::table(self::TABLE)
                ->where('id', $form->getId())
                ->first();
        } else {
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $id = DB::table(self::TABLE)->insertGetId($data);

            $record = DB::table(self::TABLE)
                ->where('id', $id)
                ->first();
        }

        return $this->toDomainEntity($record);
    }

    public function delete(int $id): bool
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return false;
        }

        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['deleted_at' => now()]) > 0;
    }

    private function toDomainEntity(stdClass $record): CmsForm
    {
        $fields = is_string($record->fields ?? null)
            ? json_decode($record->fields, true)
            : ($record->fields ?? []);

        $settings = is_string($record->settings ?? null)
            ? json_decode($record->settings, true)
            : ($record->settings ?? null);

        $fieldMapping = is_string($record->field_mapping ?? null)
            ? json_decode($record->field_mapping, true)
            : ($record->field_mapping ?? null);

        $notificationEmails = is_string($record->notification_emails ?? null)
            ? json_decode($record->notification_emails, true)
            : ($record->notification_emails ?? null);

        return CmsForm::reconstitute(
            id: $record->id,
            name: $record->name,
            slug: $record->slug,
            description: $record->description ?? null,
            fields: $fields,
            settings: $settings,
            submitAction: FormSubmitAction::from($record->submit_action),
            targetModuleId: $record->target_module_id ?? null,
            fieldMapping: $fieldMapping,
            submitButtonText: $record->submit_button_text ?? 'Submit',
            successMessage: $record->success_message ?? null,
            redirectUrl: $record->redirect_url ?? null,
            notificationEmails: $notificationEmails,
            notificationTemplateId: $record->notification_template_id ?? null,
            submissionCount: (int) ($record->submission_count ?? 0),
            viewCount: (int) ($record->view_count ?? 0),
            isActive: (bool) $record->is_active,
            createdBy: $record->created_by ?? null,
            createdAt: $record->created_at
                ? new DateTimeImmutable($record->created_at)
                : null,
            updatedAt: $record->updated_at
                ? new DateTimeImmutable($record->updated_at)
                : null,
            deletedAt: $record->deleted_at
                ? new DateTimeImmutable($record->deleted_at)
                : null,
        );
    }

    private function toModelData(CmsForm $form): array
    {
        return [
            'name' => $form->getName(),
            'slug' => $form->getSlug(),
            'description' => $form->getDescription(),
            'fields' => is_array($form->getFields())
                ? json_encode($form->getFields())
                : $form->getFields(),
            'settings' => is_array($form->getSettings())
                ? json_encode($form->getSettings())
                : $form->getSettings(),
            'submit_action' => $form->getSubmitAction()->value,
            'target_module_id' => $form->getTargetModuleId(),
            'field_mapping' => is_array($form->getFieldMapping())
                ? json_encode($form->getFieldMapping())
                : $form->getFieldMapping(),
            'submit_button_text' => $form->getSubmitButtonText(),
            'success_message' => $form->getSuccessMessage(),
            'redirect_url' => $form->getRedirectUrl(),
            'notification_emails' => is_array($form->getNotificationEmails())
                ? json_encode($form->getNotificationEmails())
                : $form->getNotificationEmails(),
            'notification_template_id' => $form->getNotificationTemplateId(),
            'submission_count' => $form->getSubmissionCount(),
            'view_count' => $form->getViewCount(),
            'is_active' => $form->isActive(),
            'created_by' => $form->getCreatedBy(),
        ];
    }

    private function toArray(stdClass $record): array
    {
        $fields = is_string($record->fields ?? null)
            ? json_decode($record->fields, true)
            : ($record->fields ?? []);

        $settings = is_string($record->settings ?? null)
            ? json_decode($record->settings, true)
            : ($record->settings ?? null);

        $fieldMapping = is_string($record->field_mapping ?? null)
            ? json_decode($record->field_mapping, true)
            : ($record->field_mapping ?? null);

        $notificationEmails = is_string($record->notification_emails ?? null)
            ? json_decode($record->notification_emails, true)
            : ($record->notification_emails ?? null);

        return [
            'id' => $record->id,
            'name' => $record->name,
            'slug' => $record->slug,
            'description' => $record->description ?? null,
            'fields' => $fields,
            'settings' => $settings,
            'submit_action' => $record->submit_action,
            'target_module_id' => $record->target_module_id ?? null,
            'field_mapping' => $fieldMapping,
            'submit_button_text' => $record->submit_button_text ?? 'Submit',
            'success_message' => $record->success_message ?? null,
            'redirect_url' => $record->redirect_url ?? null,
            'notification_emails' => $notificationEmails,
            'notification_template_id' => $record->notification_template_id ?? null,
            'submission_count' => (int) ($record->submission_count ?? 0),
            'view_count' => (int) ($record->view_count ?? 0),
            'is_active' => (bool) $record->is_active,
            'conversion_rate' => $this->calculateConversionRate(
                (int) ($record->submission_count ?? 0),
                (int) ($record->view_count ?? 0)
            ),
            'created_by' => $record->created_by ?? null,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
            'deleted_at' => $record->deleted_at ?? null,
        ];
    }

    private function calculateConversionRate(int $submissions, int $views): float
    {
        if ($views === 0) {
            return 0.0;
        }
        return round(($submissions / $views) * 100, 2);
    }
}
