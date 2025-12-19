<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Video;

use App\Domain\Video\Entities\VideoMeeting;
use App\Domain\Video\Repositories\VideoMeetingRepositoryInterface;
use DateTimeImmutable;

class EloquentVideoMeetingRepository implements VideoMeetingRepositoryInterface
{
    public function findById(int $id): ?VideoMeeting
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(VideoMeeting $entity): VideoMeeting
    {
        // TODO: Implement with Eloquent model
        return $entity;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement with Eloquent model
        return false;
    }
}
