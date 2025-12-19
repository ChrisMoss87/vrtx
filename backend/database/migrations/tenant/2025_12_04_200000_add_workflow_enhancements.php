<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Track "run once per record" executions
        Schema::create('workflow_run_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedBigInteger('record_id');
            $table->string('record_type'); // Module API name or model class
            $table->string('trigger_type'); // Which trigger caused this execution
            $table->timestamp('executed_at');
            $table->timestamps();

            // Ensure each workflow only runs once per record (per trigger type)
            $table->unique(['workflow_id', 'record_id', 'record_type', 'trigger_type'], 'workflow_run_unique');
            $table->index(['workflow_id', 'executed_at']);
            $table->index(['record_id', 'record_type']);
        });

        // Add enhancements to workflows table
        Schema::table('workflows', function (Blueprint $table) {
            // Trigger timing: when should this workflow run
            // 'all' = on create and update, 'create_only' = only on create, 'update_only' = only on update
            $table->string('trigger_timing')->default('all')->after('trigger_config');

            // For field_changed trigger: which specific fields to watch
            $table->json('watched_fields')->nullable()->after('trigger_timing');

            // For inbound webhook triggers: secret for authentication
            $table->string('webhook_secret')->nullable()->after('watched_fields');

            // Stop processing other workflows if this one matches
            $table->boolean('stop_on_first_match')->default(false)->after('webhook_secret');

            // Rate limiting: max executions per day (null = unlimited)
            $table->integer('max_executions_per_day')->nullable()->after('stop_on_first_match');

            // Today's execution count for rate limiting
            $table->integer('executions_today')->default(0)->after('max_executions_per_day');
            $table->date('executions_today_date')->nullable()->after('executions_today');
        });

        // Add enhancements to workflow_steps table
        Schema::table('workflow_steps', function (Blueprint $table) {
            // Branching: which step to go to on success/failure (null = next in order)
            $table->unsignedBigInteger('on_success_goto')->nullable()->after('retry_delay_seconds');
            $table->unsignedBigInteger('on_failure_goto')->nullable()->after('on_success_goto');

            // Step timeout in seconds (0 = no timeout)
            $table->integer('timeout_seconds')->default(300)->after('on_failure_goto');

            // Execute asynchronously (don't wait for completion before next step)
            $table->boolean('is_async')->default(false)->after('timeout_seconds');

            // Step is disabled (skip during execution)
            $table->boolean('is_disabled')->default(false)->after('is_async');

            // Human-readable step description (auto-generated or custom)
            $table->string('description')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn([
                'on_success_goto',
                'on_failure_goto',
                'timeout_seconds',
                'is_async',
                'is_disabled',
                'description',
            ]);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn([
                'trigger_timing',
                'watched_fields',
                'webhook_secret',
                'stop_on_first_match',
                'max_executions_per_day',
                'executions_today',
                'executions_today_date',
            ]);
        });

        Schema::dropIfExists('workflow_run_history');
    }
};
