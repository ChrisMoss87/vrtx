<?php

namespace App\Policies;

use App\Models\SchedulingPage;
use App\Models\User;

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
        return $user->id === $schedulingPage->user_id;
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
        return $user->id === $schedulingPage->user_id;
    }

    /**
     * Determine whether the user can delete the scheduling page.
     */
    public function delete(User $user, SchedulingPage $schedulingPage): bool
    {
        return $user->id === $schedulingPage->user_id;
    }
}
