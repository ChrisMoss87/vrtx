<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Dashboard;
use App\Models\ModuleRecord;
use App\Models\Report;
use App\Models\SchedulingPage;
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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
