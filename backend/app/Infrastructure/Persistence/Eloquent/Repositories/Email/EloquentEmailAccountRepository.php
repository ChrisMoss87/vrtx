<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Email;

use App\Domain\Email\Entities\EmailAccount;
use App\Domain\Email\Repositories\EmailAccountRepositoryInterface;
use App\Models\EmailAccount as EmailAccountModel;
use DateTimeImmutable;

class EloquentEmailAccountRepository implements EmailAccountRepositoryInterface
{
    public function findById(int $id): ?EmailAccount
    {
        $model = EmailAccountModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByUserId(int $userId): array
    {
        $models = EmailAccountModel::where('user_id', $userId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findDefaultForUser(int $userId): ?EmailAccount
    {
        $model = EmailAccountModel::where('user_id', $userId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByEmail(string $email): ?EmailAccount
    {
        $model = EmailAccountModel::where('email', $email)->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function save(EmailAccount $account): EmailAccount
    {
        $data = $this->toModelData($account);

        if ($account->getId() !== null) {
            $model = EmailAccountModel::findOrFail($account->getId());
            $model->update($data);
        } else {
            $model = EmailAccountModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = EmailAccountModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(EmailAccountModel $model): EmailAccount
    {
        return EmailAccount::reconstitute(
            id: $model->id,
            userId: $model->user_id,
            email: $model->email,
            name: $model->name,
            provider: $model->provider,
            settings: $model->settings ?? [],
            isActive: $model->is_active,
            isDefault: $model->is_default,
            lastSyncedAt: $model->last_synced_at
                ? new DateTimeImmutable($model->last_synced_at->toDateTimeString())
                : null,
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
    private function toModelData(EmailAccount $account): array
    {
        return [
            'user_id' => $account->getUserId(),
            'email' => $account->getEmail(),
            'name' => $account->getName(),
            'provider' => $account->getProvider(),
            'settings' => $account->getSettings(),
            'is_active' => $account->isActive(),
            'is_default' => $account->isDefault(),
            'last_synced_at' => $account->getLastSyncedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
