<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widget_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained('dashboard_widgets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('condition_type'); // above, below, percent_change, equals
            $table->decimal('threshold_value', 20, 4);
            $table->string('comparison_period')->nullable(); // previous_day, previous_week, previous_month
            $table->string('severity')->default('warning'); // info, warning, critical
            $table->json('notification_channels')->default('["in_app"]');
            $table->integer('cooldown_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->timestamps();

            $table->index(['widget_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('dashboard_widget_alert_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('dashboard_widget_alerts')->cascadeOnDelete();
            $table->decimal('triggered_value', 20, 4);
            $table->decimal('threshold_value', 20, 4);
            $table->json('context')->nullable();
            $table->string('status')->default('triggered'); // triggered, acknowledged, dismissed
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['alert_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widget_alert_history');
        Schema::dropIfExists('dashboard_widget_alerts');
    }
};
