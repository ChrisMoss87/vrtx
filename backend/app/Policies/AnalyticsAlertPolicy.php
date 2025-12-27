<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Analytics\Entities\AnalyticsAlert;
use App\Domain\User\Entities\User;
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
        if ($alert->getUserId() === $user->id) {
            return true;
        }

        // Users in recipients list can view
        $notificationConfig = $alert->getNotificationConfig();
        $recipients = $notificationConfig['recipients'] ?? [];
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
        return $alert->getUserId() === $user->id;
    }

    /**
     * Determine whether the user can delete the alert.
     */
    public function delete(User $user, AnalyticsAlert $alert): bool
    {
        return $alert->getUserId() === $user->id;
    }
}
