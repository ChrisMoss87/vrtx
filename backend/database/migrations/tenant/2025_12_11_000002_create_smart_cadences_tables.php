<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cadences - the main sequence configuration
        Schema::create('cadences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft');
            $table->jsonb('entry_criteria')->nullable(); // When to auto-enroll
            $table->jsonb('exit_criteria')->nullable(); // When to auto-exit
            $table->jsonb('settings')->default('{}');
            $table->boolean('auto_enroll')->default(false);
            $table->boolean('allow_re_enrollment')->default(false);
            $table->integer('re_enrollment_days')->nullable();
            $table->integer('max_enrollments_per_day')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('module_id');
        });

        // Cadence steps - individual actions in a sequence
        Schema::create('cadence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cadence_id')->constrained('cadences')->cascadeOnDelete();
            $table->integer('step_order');
            $table->string('name')->nullable();
            $table->enum('channel', ['email', 'call', 'sms', 'linkedin', 'task', 'wait'])->default('email');
            $table->enum('delay_type', ['immediate', 'days', 'hours', 'business_days'])->default('days');
            $table->integer('delay_value')->default(0);
            $table->time('preferred_time')->nullable();
            $table->string('timezone')->nullable();

            // Content based on channel
            $table->string('subject')->nullable(); // For email
            $table->text('content')->nullable(); // Email body, SMS text, call script, etc.
            $table->foreignId('template_id')->nullable(); // Reference to email template

            // Branching/conditions
            $table->jsonb('conditions')->nullable(); // When to execute this step
            $table->foreignId('on_reply_goto_step')->nullable();
            $table->foreignId('on_click_goto_step')->nullable();
            $table->foreignId('on_no_response_goto_step')->nullable();

            // A/B testing
            $table->boolean('is_ab_test')->default(false);
            $table->foreignId('ab_variant_of')->nullable(); // Parent step if this is a variant
            $table->integer('ab_percentage')->nullable(); // Traffic percentage

            // LinkedIn specific
            $table->enum('linkedin_action', ['connection_request', 'message', 'view_profile', 'engage'])->nullable();

            // Task specific
            $table->string('task_type')->nullable();
            $table->foreignId('task_assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['cadence_id', 'step_order']);
            $table->foreign('on_reply_goto_step')->references('id')->on('cadence_steps')->nullOnDelete();
            $table->foreign('on_click_goto_step')->references('id')->on('cadence_steps')->nullOnDelete();
            $table->foreign('on_no_response_goto_step')->references('id')->on('cadence_steps')->nullOnDelete();
            $table->foreign('ab_variant_of')->references('id')->on('cadence_steps')->nullOnDelete();
        });

        // Cadence enrollments - contacts in a sequence
        Schema::create('cadence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cadence_id')->constrained('cadences')->cascadeOnDelete();
            $table->foreignId('record_id'); // The module record (contact, lead, etc.)
            $table->foreignId('current_step_id')->nullable()->constrained('cadence_steps')->nullOnDelete();
            $table->enum('status', ['active', 'paused', 'completed', 'replied', 'bounced', 'unsubscribed', 'meeting_booked', 'manually_removed'])->default('active');
            $table->timestamp('enrolled_at');
            $table->timestamp('next_step_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->string('exit_reason')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->unique(['cadence_id', 'record_id']);
            $table->index('status');
            $table->index('next_step_at');
        });

        // Cadence step executions - log of executed steps
        Schema::create('cadence_step_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('cadence_enrollments')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('cadence_steps')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->timestamp('executed_at')->nullable();
            $table->enum('status', ['scheduled', 'executing', 'completed', 'failed', 'skipped', 'cancelled'])->default('scheduled');
            $table->enum('result', ['sent', 'delivered', 'opened', 'clicked', 'replied', 'bounced', 'failed', 'completed', 'skipped'])->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->default('{}'); // Store message ID, delivery info, etc.
            $table->timestamps();

            $table->index(['enrollment_id', 'status']);
            $table->index('scheduled_at');
        });

        // Send time predictions - AI-learned optimal send times
        Schema::create('send_time_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id'); // Contact/lead record
            $table->enum('channel', ['email', 'sms', 'call'])->default('email');
            $table->tinyInteger('optimal_hour'); // 0-23
            $table->tinyInteger('optimal_day')->nullable(); // 0-6 (Sunday-Saturday)
            $table->string('timezone')->default('UTC');
            $table->decimal('confidence', 5, 4)->default(0); // 0-1
            $table->integer('data_points')->default(0);
            $table->timestamp('last_updated_at');
            $table->timestamps();

            $table->unique(['record_id', 'channel']);
            $table->index('confidence');
        });

        // Cadence analytics - aggregated metrics
        Schema::create('cadence_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cadence_id')->constrained('cadences')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('cadence_steps')->cascadeOnDelete();
            $table->date('date');
            $table->integer('enrollments')->default(0);
            $table->integer('completions')->default(0);
            $table->integer('replies')->default(0);
            $table->integer('meetings_booked')->default(0);
            $table->integer('bounces')->default(0);
            $table->integer('unsubscribes')->default(0);
            $table->integer('emails_sent')->default(0);
            $table->integer('emails_opened')->default(0);
            $table->integer('emails_clicked')->default(0);
            $table->integer('calls_made')->default(0);
            $table->integer('calls_connected')->default(0);
            $table->integer('sms_sent')->default(0);
            $table->integer('sms_replied')->default(0);
            $table->timestamps();

            $table->unique(['cadence_id', 'step_id', 'date']);
            $table->index('date');
        });

        // Cadence templates - reusable cadence configurations
        Schema::create('cadence_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->jsonb('steps_config'); // Serialized step configuration
            $table->jsonb('settings')->default('{}');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadence_templates');
        Schema::dropIfExists('cadence_metrics');
        Schema::dropIfExists('send_time_predictions');
        Schema::dropIfExists('cadence_step_executions');
        Schema::dropIfExists('cadence_enrollments');
        Schema::dropIfExists('cadence_steps');
        Schema::dropIfExists('cadences');
    }
};
