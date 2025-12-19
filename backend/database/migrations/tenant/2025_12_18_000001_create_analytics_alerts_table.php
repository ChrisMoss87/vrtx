<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Alert type: threshold, anomaly, trend, comparison
            $table->string('alert_type', 50);

            // What to monitor
            $table->foreignId('module_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('report_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('metric_field')->nullable(); // Field to monitor
            $table->string('aggregation', 50)->default('count'); // count, sum, avg, etc.
            $table->json('filters')->nullable(); // Additional filters for the metric

            // Condition configuration
            $table->json('condition_config');
            /*
             * For threshold alerts:
             * { "operator": "greater_than", "value": 100, "comparison_period": "previous_period" }
             *
             * For anomaly alerts:
             * { "sensitivity": "medium", "baseline_periods": 7, "min_deviation_percent": 20 }
             *
             * For trend alerts:
             * { "direction": "decreasing", "periods": 3, "min_change_percent": 10 }
             *
             * For comparison alerts:
             * { "compare_to": "previous_period", "change_type": "percent", "threshold": 15 }
             */

            // Notification settings
            $table->json('notification_config');
            /*
             * {
             *   "channels": ["email", "in_app"],
             *   "recipients": [1, 2, 3], // user IDs
             *   "email_addresses": ["user@example.com"],
             *   "frequency": "immediate", // immediate, daily_digest, weekly_digest
             *   "quiet_hours": { "start": "22:00", "end": "08:00" }
             * }
             */

            // Schedule
            $table->string('check_frequency', 50)->default('hourly'); // hourly, daily, weekly
            $table->time('check_time')->nullable(); // For daily/weekly checks

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->integer('consecutive_triggers')->default(0);

            // Cooldown to prevent alert fatigue
            $table->integer('cooldown_minutes')->default(60);
            $table->timestamp('cooldown_until')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'check_frequency']);
            $table->index('last_checked_at');
        });

        Schema::create('analytics_alert_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('analytics_alerts')->onDelete('cascade');

            // Trigger details
            $table->string('status', 50); // triggered, resolved, acknowledged, muted
            $table->decimal('metric_value', 20, 4)->nullable();
            $table->decimal('threshold_value', 20, 4)->nullable();
            $table->decimal('baseline_value', 20, 4)->nullable();
            $table->decimal('deviation_percent', 10, 2)->nullable();

            // Context
            $table->json('context')->nullable(); // Additional data about the trigger
            $table->text('message')->nullable(); // Human-readable description

            // Resolution
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgment_note')->nullable();

            // Notifications sent
            $table->json('notifications_sent')->nullable();

            $table->timestamps();

            $table->index(['alert_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('analytics_alert_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('analytics_alerts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Per-user notification preferences
            $table->json('channels')->nullable(); // Override default channels
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();

            $table->timestamps();

            $table->unique(['alert_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_alert_subscriptions');
        Schema::dropIfExists('analytics_alert_history');
        Schema::dropIfExists('analytics_alerts');
    }
};
