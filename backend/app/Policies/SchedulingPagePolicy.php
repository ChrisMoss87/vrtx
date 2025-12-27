<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Scheduling\Entities\SchedulingPage;
use App\Domain\User\Entities\User;

class SchedulingPagePolicy
{
    /**
     * Determine whether the user can view any scheduling pages.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view their own pages (filtered in controller)
    }

    /**
     * Determine whether the user can view the scheduling page.
     */
    public function view(User $user, SchedulingPage $schedulingPage): bool
    {
        return $user->id === $schedulingPage->userId()->value();
    }

    /**
     * Determine whether the user can create scheduling pages.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the scheduling page.
     */
    public function update(User $user, SchedulingPage $schedulingPage): bool
    {
        return $user->id === $schedulingPage->userId()->value();
    }

    /**
     * Determine whether the user can delete the scheduling page.
     */
    public function delete(User $user, SchedulingPage $schedulingPage): bool
    {
        return $user->id === $schedulingPage->userId()->value();
    }
}
