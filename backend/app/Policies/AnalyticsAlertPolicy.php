<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AnalyticsAlert;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnalyticsAlertPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any alerts.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the alert.
     */
    public function view(User $user, AnalyticsAlert $alert): bool
    {
        // Owner can always view
        if ($alert->user_id === $user->id) {
            return true;
        }

        // Users in recipients list can view
        $recipients = $alert->notification_config['recipients'] ?? [];
        return in_array($user->id, $recipients);
    }

    /**
     * Determine whether the user can create alerts.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the alert.
     */
    public function update(User $user, AnalyticsAlert $alert): bool
    {
        return $alert->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the alert.
     */
    public function delete(User $user, AnalyticsAlert $alert): bool
    {
        return $alert->user_id === $user->id;
    }
}
