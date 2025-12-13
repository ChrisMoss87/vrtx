<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add deal rotting alerts support.
 *
 * This migration adds:
 * - rotting_days column to stages for configurable thresholds per stage
 * - last_activity_at column to module_records for tracking activity
 * - rotting_alert_settings table for user notification preferences
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add rotting threshold to stages
        Schema::table('stages', function (Blueprint $table) {
            $table->integer('rotting_days')->nullable()->after('is_lost_stage');
        });

        // Add last activity tracking to module records
        Schema::table('module_records', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('updated_by');
            $table->index('last_activity_at', 'idx_records_last_activity');
        });

        // Create rotting alert settings for user preferences
        Schema::create('rotting_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('email_digest_enabled')->default(true);
            $table->string('digest_frequency', 20)->default('daily'); // daily, weekly, none
            $table->boolean('in_app_notifications')->default(true);
            $table->boolean('exclude_weekends')->default(false);
            $table->timestamps();

            // User can have one setting per pipeline, or one global (null pipeline_id)
            $table->unique(['user_id', 'pipeline_id'], 'idx_rotting_settings_user_pipeline');
        });

        // Create rotting alerts table for tracking sent alerts
        Schema::create('rotting_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type', 30); // warning, stale, rotting
            $table->integer('days_inactive');
            $table->timestamp('sent_at');
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            // Prevent duplicate alerts for same record/stage/type combo
            $table->unique(
                ['module_record_id', 'stage_id', 'alert_type'],
                'idx_rotting_alerts_unique'
            );
            $table->index(['user_id', 'acknowledged'], 'idx_rotting_alerts_user');
            $table->index('sent_at', 'idx_rotting_alerts_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotting_alerts');
        Schema::dropIfExists('rotting_alert_settings');

        Schema::table('module_records', function (Blueprint $table) {
            $table->dropIndex('idx_records_last_activity');
            $table->dropColumn('last_activity_at');
        });

        Schema::table('stages', function (Blueprint $table) {
            $table->dropColumn('rotting_days');
        });
    }
};
