<?php

namespace App\Providers;

use App\Infrastructure\Persistence\Eloquent\Models\Role;
use App\Observers\RoleObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observer for Role events (custom DDD models)
        // Note: Module, ModuleRecord, and BlueprintApprovalRequest events
        // are now dispatched directly from their repositories (pure DDD)
        Role::observe(RoleObserver::class);

        // Configure rate limiters
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Default API rate limiter: 60 requests per minute per user/IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Strict rate limiter for authentication
        // Much higher limit for local/testing to support E2E tests
        RateLimiter::for('auth', function (Request $request) {
            $limit = app()->environment('local', 'testing') ? 1000 : 5;
            return Limit::perMinute($limit)->by($request->ip());
        });

        // File upload rate limiter: 20 uploads per minute
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Search rate limiter: 30 requests per minute
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Export rate limiter: 10 exports per minute
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Public form submissions: 10 per minute per IP
        RateLimiter::for('public-forms', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Webhook receiving: 100 per minute per token
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(100)->by($request->route('token') ?: $request->ip());
        });
    }
}
