<?php

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
        // Scheduling Pages - Personal booking pages for users
        Schema::create('scheduling_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('timezone', 50)->default('UTC');
            $table->json('branding')->nullable(); // logo, colors, etc.
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // Meeting Types - Different meeting options per scheduling page
        Schema::create('meeting_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_page_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug', 100);
            $table->integer('duration_minutes');
            $table->text('description')->nullable();
            $table->string('location_type', 50)->nullable(); // zoom, google_meet, phone, in_person, custom
            $table->text('location_details')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('questions')->nullable(); // pre-meeting questions
            $table->json('settings')->nullable(); // buffer_before, buffer_after, min_notice, max_days_advance
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['scheduling_page_id', 'slug']);
            $table->index(['scheduling_page_id', 'is_active']);
        });

        // Availability Rules - Weekly recurring availability
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('day_of_week'); // 0=Sunday, 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'day_of_week']);
        });

        // Scheduling Overrides - Date-specific availability changes
        Schema::create('scheduling_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->boolean('is_available')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        // Scheduled Meetings - Booked meetings
        Schema::create('scheduled_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('host_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained('module_records')->nullOnDelete();
            $table->string('attendee_name');
            $table->string('attendee_email');
            $table->string('attendee_phone')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('timezone', 50);
            $table->text('location')->nullable();
            $table->text('notes')->nullable();
            $table->json('answers')->nullable(); // answers to pre-meeting questions
            $table->string('status', 20)->default('scheduled'); // scheduled, completed, cancelled, rescheduled, no_show
            $table->string('calendar_event_id')->nullable(); // external calendar event ID
            $table->string('manage_token', 64)->unique(); // for reschedule/cancel links
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['host_user_id', 'start_time']);
            $table->index(['status', 'start_time']);
            $table->index('manage_token');
        });

        // Calendar Connections - OAuth connections to external calendars
        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider', 50); // google, outlook, apple
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('calendar_id')->nullable();
            $table->string('calendar_name')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'calendar_id']);
            $table->index(['user_id', 'provider']);
        });

        // Calendar Events Cache - Cached events from connected calendars
        Schema::create('calendar_events_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_connection_id')->constrained()->onDelete('cascade');
            $table->string('external_event_id');
            $table->string('title')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->boolean('is_all_day')->default(false);
            $table->string('status', 20)->default('confirmed'); // confirmed, tentative, cancelled
            $table->timestamps();

            $table->unique(['calendar_connection_id', 'external_event_id']);
            $table->index(['calendar_connection_id', 'start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events_cache');
        Schema::dropIfExists('calendar_connections');
        Schema::dropIfExists('scheduled_meetings');
        Schema::dropIfExists('scheduling_overrides');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('meeting_types');
        Schema::dropIfExists('scheduling_pages');
    }
};
