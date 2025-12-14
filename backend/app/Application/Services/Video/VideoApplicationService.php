<?php

declare(strict_types=1);

namespace App\Application\Services\Video;

use App\Domain\Video\Repositories\VideoMeetingRepositoryInterface;

class VideoApplicationService
{
    public function __construct(
        private VideoMeetingRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
