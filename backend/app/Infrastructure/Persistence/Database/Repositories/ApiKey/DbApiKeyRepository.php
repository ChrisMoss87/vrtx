<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\ApiKey;

use App\Domain\ApiKey\Entities\ApiKey;
use App\Domain\ApiKey\Repositories\ApiKeyRepositoryInterface;
use App\Domain\ApiKey\ValueObjects\ApiKeyHash;
use App\Domain\ApiKey\ValueObjects\ApiKeyId;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use stdClass;

final class DbApiKeyRepository implements ApiKeyRepositoryInterface
{
    private const TABLE = 'api_keys';
    private const REQUEST_LOG_TABLE = 'api_request_logs';

    // ========== ENTITY-BASED (Domain operations) ==========

    public function findEntityById(ApiKeyId $id): ?ApiKey
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id->value())
            ->first();

        return $row ? $this->toEntity($row) : null;
    }

    public function findEntityByHash(string $keyHash): ?ApiKey
    {
        $row = DB::table(self::TABLE)
            ->where('key', $keyHash)
            ->first();

        return $row ? $this->toEntity($row) : null;
    }

    public function saveEntity(ApiKey $apiKey): ApiKey
    {
        $data = [
            'name' => $apiKey->getName(),
            'key' => $apiKey->getKeyHash()->value(),
            'user_id' => $apiKey->getUserId(),
            'scopes' => json_encode($apiKey->getScopes()->toArray()),
            'ip_whitelist' => json_encode($apiKey->getIpWhitelist()->toArray()),
            'rate_limit' => $apiKey->getRateLimit(),
            'expires_at' => $apiKey->getExpiresAt()?->format('Y-m-d H:i:s'),
            'last_used_at' => $apiKey->getLastUsedAt()?->format('Y-m-d H:i:s'),
            'is_active' => $apiKey->isActive(),
            'updated_at' => now(),
        ];

        if ($apiKey->getIdValue() !== null) {
            DB::table(self::TABLE)
                ->where('id', $apiKey->getIdValue())
                ->update($data);

            return $this->findEntityById($apiKey->getId());
        }

        $data['created_at'] = now();
        $id = DB::table(self::TABLE)->insertGetId($data);

        return $this->findEntityById(ApiKeyId::fromInt($id));
    }

    public function deleteEntity(ApiKeyId $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id->value())
            ->delete() > 0;
    }

    // ========== ARRAY-BASED (Application layer queries) ==========

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findByHash(string $keyHash): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('key', $keyHash)
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findByPlainKey(string $plainKey): ?array
    {
        $hash = ApiKeyHash::fromPlainKey($plainKey);

        return $this->findByHash($hash->value());
    }

    public function findByUserId(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->toArray($row))->toArray();
    }

    public function findWithFilters(array $filters, int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where('name', 'ILIKE', $search);
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $rows = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn ($row) => $this->toArray($row))->toArray();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId([
            'name' => $data['name'],
            'key' => $data['key'],
            'user_id' => $data['user_id'],
            'scopes' => json_encode($data['scopes'] ?? ['*']),
            'ip_whitelist' => json_encode($data['ip_whitelist'] ?? []),
            'rate_limit' => $data['rate_limit'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findById($id);
    }

    public function update(int $id, array $data): array
    {
        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('scopes', $data)) {
            $updateData['scopes'] = json_encode($data['scopes'] ?? []);
        }
        if (array_key_exists('ip_whitelist', $data)) {
            $updateData['ip_whitelist'] = json_encode($data['ip_whitelist'] ?? []);
        }
        if (array_key_exists('rate_limit', $data)) {
            $updateData['rate_limit'] = $data['rate_limit'];
        }
        if (array_key_exists('expires_at', $data)) {
            $updateData['expires_at'] = $data['expires_at'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update($updateData);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->delete() > 0;
    }

    // ========== USAGE TRACKING ==========

    public function updateLastUsed(int $id): void
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['last_used_at' => now()]);
    }

    public function logRequest(int $apiKeyId, string $endpoint, string $method, string $ip, int $responseCode): void
    {
        DB::table(self::REQUEST_LOG_TABLE)->insert([
            'api_key_id' => $apiKeyId,
            'endpoint' => $endpoint,
            'method' => $method,
            'ip_address' => $ip,
            'response_code' => $responseCode,
            'created_at' => now(),
        ]);
    }

    public function getRequestCount(int $apiKeyId, int $windowSeconds = 60): int
    {
        $key = "apikey:rate:{$apiKeyId}";

        return (int) Redis::get($key) ?: 0;
    }

    public function incrementRequestCount(int $apiKeyId): void
    {
        $key = "apikey:rate:{$apiKeyId}";

        $count = Redis::incr($key);

        // Set expiry on first increment
        if ($count === 1) {
            Redis::expire($key, 60); // 1 minute window
        }
    }

    // ========== UTILITY ==========

    public function exists(int $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->exists();
    }

    public function nameExistsForUser(string $name, int $userId, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)
            ->where('name', $name)
            ->where('user_id', $userId);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function countByUserId(int $userId): int
    {
        return DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->count();
    }

    public function findExpired(): array
    {
        $rows = DB::table(self::TABLE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('is_active', true)
            ->get();

        return $rows->map(fn ($row) => $this->toArray($row))->toArray();
    }

    public function deleteExpired(): int
    {
        return DB::table(self::TABLE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }

    // ========== PRIVATE HELPERS ==========

    private function toEntity(stdClass $row): ApiKey
    {
        return ApiKey::reconstitute(
            id: $row->id,
            name: $row->name,
            keyHash: $row->key,
            userId: $row->user_id,
            scopes: json_decode($row->scopes ?? '["*"]', true),
            ipWhitelist: json_decode($row->ip_whitelist ?? '[]', true),
            rateLimit: $row->rate_limit,
            expiresAt: $row->expires_at ? new DateTimeImmutable($row->expires_at) : null,
            lastUsedAt: $row->last_used_at ? new DateTimeImmutable($row->last_used_at) : null,
            isActive: (bool) $row->is_active,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: new DateTimeImmutable($row->updated_at),
        );
    }

    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'user_id' => $row->user_id,
            'scopes' => json_decode($row->scopes ?? '["*"]', true),
            'ip_whitelist' => json_decode($row->ip_whitelist ?? '[]', true),
            'rate_limit' => $row->rate_limit,
            'expires_at' => $row->expires_at,
            'last_used_at' => $row->last_used_at,
            'is_active' => (bool) $row->is_active,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
