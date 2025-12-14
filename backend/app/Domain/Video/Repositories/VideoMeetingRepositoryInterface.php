<?php

declare(strict_types=1);

namespace App\Domain\Video\Repositories;

use App\Domain\Video\Entities\VideoMeeting;

interface VideoMeetingRepositoryInterface
{
    public function findById(int $id): ?VideoMeeting;
    
    public function findAll(): array;
    
    public function save(VideoMeeting $entity): VideoMeeting;
    
    public function delete(int $id): bool;
}
