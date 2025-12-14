<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Email;

use App\Domain\Email\Entities\EmailTemplate;
use App\Domain\Email\Repositories\EmailTemplateRepositoryInterface;
use App\Models\EmailTemplate as EmailTemplateModel;
use DateTimeImmutable;

class EloquentEmailTemplateRepository implements EmailTemplateRepositoryInterface
{
    public function findById(int $id): ?EmailTemplate
    {
        $model = EmailTemplateModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByModuleId(int $moduleId): array
    {
        $models = EmailTemplateModel::where('module_id', $moduleId)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findShared(): array
    {
        $models = EmailTemplateModel::where('is_shared', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByUserId(int $userId): array
    {
        $models = EmailTemplateModel::where('created_by', $userId)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findActive(): array
    {
        $models = EmailTemplateModel::where('is_active', true)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(EmailTemplate $template): EmailTemplate
    {
        $data = $this->toModelData($template);

        if ($template->getId() !== null) {
            $model = EmailTemplateModel::findOrFail($template->getId());
            $model->update($data);
        } else {
            $model = EmailTemplateModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = EmailTemplateModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(EmailTemplateModel $model): EmailTemplate
    {
        return EmailTemplate::reconstitute(
            id: $model->id,
            name: $model->name,
            subject: $model->subject,
            bodyHtml: $model->body_html,
            bodyText: $model->body_text,
            moduleId: $model->module_id,
            folderId: $model->folder_id,
            isShared: $model->is_shared,
            isActive: $model->is_active,
            variables: $model->variables ?? [],
            createdBy: $model->created_by,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(EmailTemplate $template): array
    {
        return [
            'name' => $template->getName(),
            'subject' => $template->getSubject(),
            'body_html' => $template->getBodyHtml(),
            'body_text' => $template->getBodyText(),
            'module_id' => $template->getModuleId(),
            'is_shared' => $template->isShared(),
            'is_active' => $template->isActive(),
            'variables' => $template->getVariables(),
        ];
    }
}
