<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Chat;

use App\Domain\Chat\Repositories\ChatVisitorRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DbChatVisitorRepository implements ChatVisitorRepositoryInterface
{
    private const TABLE = 'chat_visitors';
    private const TABLE_CONTACTS = 'module_records';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        if ($row->contact_id) {
            $result['contact'] = DB::table(self::TABLE_CONTACTS)
                ->where('id', $row->contact_id)
                ->first();
        }

        return $result;
    }

    public function findByFingerprint(int $widgetId, string $fingerprint): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('widget_id', $widgetId)
            ->where('fingerprint', $fingerprint)
            ->first();

        if (!$row) {
            return null;
        }

        $result = (array) $row;

        if ($row->contact_id) {
            $result['contact'] = DB::table(self::TABLE_CONTACTS)
                ->where('id', $row->contact_id)
                ->first();
        }

        return $result;
    }

    public function firstOrCreate(int $widgetId, string $fingerprint, array $data = []): array
    {
        $existing = DB::table(self::TABLE)
            ->where('widget_id', $widgetId)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing) {
            DB::table(self::TABLE)
                ->where('id', $existing->id)
                ->update(['last_seen_at' => now()]);

            return $this->findById($existing->id);
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'widget_id' => $widgetId,
            'fingerprint' => $fingerprint,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findById($id);
    }

    public function identify(int $visitorId, string $email, ?string $name = null): array
    {
        $updateData = [
            'email' => $email,
            'updated_at' => now(),
        ];

        if ($name) {
            $updateData['name'] = $name;
        }

        DB::table(self::TABLE)
            ->where('id', $visitorId)
            ->update($updateData);

        return $this->findById($visitorId);
    }

    public function recordPageView(int $visitorId, string $url, ?string $title = null): array
    {
        $visitor = DB::table(self::TABLE)->where('id', $visitorId)->first();

        $pageViews = $visitor->page_views ? json_decode($visitor->page_views, true) : [];
        $pageViews[] = [
            'url' => $url,
            'title' => $title,
            'timestamp' => now()->toIso8601String(),
        ];

        DB::table(self::TABLE)
            ->where('id', $visitorId)
            ->update([
                'page_views' => json_encode(array_slice($pageViews, -50)),
                'current_page' => $url,
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->findById($visitorId);
    }

    public function findOnlineVisitors(int $widgetId, int $minutesThreshold = 5): Collection
    {
        return DB::table(self::TABLE)
            ->where('widget_id', $widgetId)
            ->where('last_seen_at', '>=', now()->subMinutes($minutesThreshold))
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(function ($row) {
                $result = (array) $row;
                if ($row->contact_id) {
                    $result['contact'] = DB::table(self::TABLE_CONTACTS)
                        ->where('id', $row->contact_id)
                        ->first();
                }
                return $result;
            });
    }

    public function deleteByWidgetId(int $widgetId): int
    {
        return DB::table(self::TABLE)->where('widget_id', $widgetId)->delete();
    }
}
