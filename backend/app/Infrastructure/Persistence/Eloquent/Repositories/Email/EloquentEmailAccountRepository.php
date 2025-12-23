<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Email;

use App\Domain\Email\Entities\EmailAccount;
use App\Domain\Email\Repositories\EmailAccountRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentEmailAccountRepository implements EmailAccountRepositoryInterface
{
    private const TABLE = 'email_accounts';

    public function findById(int $id): ?EmailAccount
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByUserId(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function findDefaultForUser(int $userId): ?EmailAccount
    {
        $row = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByEmail(string $email): ?EmailAccount
    {
        $row = DB::table(self::TABLE)->where('email', $email)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(EmailAccount $account): EmailAccount
    {
        $data = $this->toRowData($account);

        if ($account->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $account->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $account->getId();
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
    private function toDomainEntity(stdClass $row): EmailAccount
    {
        return EmailAccount::reconstitute(
            id: (int) $row->id,
            userId: (int) $row->user_id,
            email: $row->email,
            name: $row->name,
            provider: $row->provider,
            settings: $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            isActive: (bool) $row->is_active,
            isDefault: (bool) $row->is_default,
            lastSyncedAt: $row->last_synced_at ? new DateTimeImmutable($row->last_synced_at) : null,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(EmailAccount $account): array
    {
        return [
            'user_id' => $account->getUserId(),
            'email' => $account->getEmail(),
            'name' => $account->getName(),
            'provider' => $account->getProvider(),
            'settings' => json_encode($account->getSettings()),
            'is_active' => $account->isActive(),
            'is_default' => $account->isDefault(),
            'last_synced_at' => $account->getLastSyncedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
