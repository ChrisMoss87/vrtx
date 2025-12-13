<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any reports.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the report.
     */
    public function view(User $user, Report $report): bool
    {
        // Users can view their own reports or public reports
        return $report->user_id === $user->id || $report->is_public;
    }

    /**
     * Determine if the user can create reports.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the report.
     */
    public function update(User $user, Report $report): bool
    {
        // Only the owner can update a report
        return $report->user_id === $user->id;
    }

    /**
     * Determine if the user can delete the report.
     */
    public function delete(User $user, Report $report): bool
    {
        // Only the owner can delete a report
        return $report->user_id === $user->id;
    }

    /**
     * Determine if the user can duplicate the report.
     */
    public function duplicate(User $user, Report $report): bool
    {
        // Users can duplicate any report they can view
        return $this->view($user, $report);
    }
}
