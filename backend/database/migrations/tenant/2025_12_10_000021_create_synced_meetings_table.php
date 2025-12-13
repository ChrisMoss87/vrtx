<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synced_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('calendar_provider', 50); // google, outlook
            $table->string('external_event_id', 255);
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('location', 500)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('meeting_url', 500)->nullable();
            $table->string('organizer_email', 255)->nullable();
            $table->jsonb('attendees')->default('[]');
            $table->string('status', 20)->default('confirmed'); // confirmed, tentative, cancelled
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('outcome', 50)->nullable(); // completed, no_show, rescheduled, cancelled
            $table->text('outcome_notes')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'calendar_provider', 'external_event_id'], 'synced_meetings_unique');
            $table->index(['user_id', 'start_time']);
            $table->index('deal_id');
            $table->index('company_id');
            $table->index('start_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synced_meetings');
    }
};
