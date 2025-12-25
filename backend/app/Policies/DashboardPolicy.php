<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Reporting\Entities\Dashboard;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any dashboards.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the dashboard.
     */
    public function view(User $user, Dashboard $dashboard): bool
    {
        // Users can view their own dashboards or public dashboards
        $dashboardUserId = $dashboard->userId()?->value();
        return $dashboardUserId === $user->id || $dashboard->isPublic();
    }

    /**
     * Determine if the user can create dashboards.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the dashboard.
     */
    public function update(User $user, Dashboard $dashboard): bool
    {
        // Only the owner can update a dashboard
        return $dashboard->userId()?->value() === $user->id;
    }

    /**
     * Determine if the user can delete the dashboard.
     */
    public function delete(User $user, Dashboard $dashboard): bool
    {
        // Only the owner can delete a dashboard
        return $dashboard->userId()?->value() === $user->id;
    }

    /**
     * Determine if the user can duplicate the dashboard.
     */
    public function duplicate(User $user, Dashboard $dashboard): bool
    {
        // Users can duplicate any dashboard they can view
        return $this->view($user, $dashboard);
    }
}
