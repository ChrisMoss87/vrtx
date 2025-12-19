<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User notifications - the main notification inbox
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Notification content
            $table->string('type'); // e.g., 'approval.pending', 'record.assigned', 'mention'
            $table->string('category'); // e.g., 'approvals', 'assignments', 'mentions', 'updates', 'reminders'
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('icon')->nullable(); // Icon name or URL
            $table->string('icon_color')->nullable(); // Tailwind color class

            // Action/link data
            $table->string('action_url')->nullable(); // URL to navigate to
            $table->string('action_label')->nullable(); // e.g., 'View Approval', 'Open Record'

            // Related entity (polymorphic)
            $table->string('notifiable_type')->nullable(); // e.g., 'App\Models\ApprovalRequest'
            $table->unsignedBigInteger('notifiable_id')->nullable();

            // Additional data (JSON blob for type-specific data)
            $table->json('data')->nullable();

            // Status
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'created_at']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });

        // Notification preferences - granular control per category/channel
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Category of notifications
            $table->string('category'); // e.g., 'approvals', 'assignments', 'mentions', 'updates', 'reminders', 'deals', 'tasks'

            // Channel preferences
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(true);
            $table->boolean('push')->default(false);

            // Frequency for email digests (null = immediate)
            $table->string('email_frequency')->nullable(); // 'immediate', 'hourly', 'daily', 'weekly'

            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });

        // Notification schedule - quiet hours / do not disturb
        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Global DND
            $table->boolean('dnd_enabled')->default(false);

            // Quiet hours (daily recurring)
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable(); // e.g., 22:00
            $table->time('quiet_hours_end')->nullable();   // e.g., 08:00

            // Weekend notifications
            $table->boolean('weekend_notifications')->default(true);

            // Timezone for schedule calculations
            $table->string('timezone')->default('UTC');

            $table->timestamps();

            $table->unique('user_id');
        });

        // Email digest queue - for batched email notifications
        Schema::create('notification_email_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->string('frequency'); // 'hourly', 'daily', 'weekly'
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['frequency', 'scheduled_for', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_queue');
        Schema::dropIfExists('notification_schedules');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
