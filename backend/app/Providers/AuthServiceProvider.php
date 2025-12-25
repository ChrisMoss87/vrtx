<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analytics\Entities\AnalyticsAlert;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Domain\Reporting\Entities\Dashboard;
use App\Domain\Reporting\Entities\Report;
use App\Domain\Scheduling\Entities\SchedulingPage;
use App\Policies\AnalyticsAlertPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\ModuleRecordPolicy;
use App\Policies\ReportPolicy;
use App\Policies\SchedulingPagePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ModuleRecord::class => ModuleRecordPolicy::class,
        Dashboard::class => DashboardPolicy::class,
        Report::class => ReportPolicy::class,
        SchedulingPage::class => SchedulingPagePolicy::class,
        AnalyticsAlert::class => AnalyticsAlertPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
