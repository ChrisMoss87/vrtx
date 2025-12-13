<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('video_providers')->onDelete('cascade');
            $table->string('external_meeting_id')->nullable();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, started, ended, canceled
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('actual_duration_seconds')->nullable();
            $table->string('join_url')->nullable();
            $table->string('host_url')->nullable();
            $table->string('password')->nullable();
            $table->boolean('waiting_room_enabled')->default(true);
            $table->boolean('recording_enabled')->default(false);
            $table->boolean('recording_auto_start')->default(false);
            $table->string('recording_url')->nullable();
            $table->string('recording_status')->nullable();
            $table->string('meeting_type')->default('instant'); // instant, scheduled, recurring
            $table->string('recurrence_type')->nullable(); // daily, weekly, monthly
            $table->json('recurrence_settings')->nullable();
            $table->foreignId('deal_id')->nullable();
            $table->string('deal_module')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('external_meeting_id');
            $table->index('host_id');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index(['deal_id', 'deal_module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_meetings');
    }
};
