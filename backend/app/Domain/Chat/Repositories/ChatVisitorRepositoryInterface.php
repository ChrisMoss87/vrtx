<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use Illuminate\Support\Collection;

interface ChatVisitorRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByFingerprint(int $widgetId, string $fingerprint): ?array;

    public function firstOrCreate(int $widgetId, string $fingerprint, array $data = []): array;

    public function identify(int $visitorId, string $email, ?string $name = null): array;

    public function recordPageView(int $visitorId, string $url, ?string $title = null): array;

    public function findOnlineVisitors(int $widgetId, int $minutesThreshold = 5): Collection;

    public function deleteByWidgetId(int $widgetId): int;
}
