<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Reporting\Entities\Report;
use App\Domain\Reporting\Repositories\ReportRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private ReportRepositoryInterface $reportRepository
    ) {}

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
        // Users can view their own reports, public reports, or reports shared with them
        $reportUserId = $report->userId()?->value();
        return $reportUserId === $user->id
            || $report->isPublic()
            || $this->reportRepository->isSharedWith($report->getId(), $user->id);
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
        // Owner or users with edit permission can update
        $reportUserId = $report->userId()?->value();
        return $reportUserId === $user->id
            || $this->reportRepository->canUserEdit($report->getId(), $user->id);
    }

    /**
     * Determine if the user can delete the report.
     */
    public function delete(User $user, Report $report): bool
    {
        // Only the owner can delete a report
        return $report->userId()?->value() === $user->id;
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
