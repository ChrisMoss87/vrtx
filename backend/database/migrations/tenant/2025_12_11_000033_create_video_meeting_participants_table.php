<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('video_meetings')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('email');
            $table->string('name');
            $table->string('role')->default('attendee'); // host, co-host, attendee
            $table->string('status')->default('invited'); // invited, joined, left, no_show
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('device_type')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('location')->nullable();
            $table->boolean('audio_enabled')->default(true);
            $table->boolean('video_enabled')->default(true);
            $table->boolean('screen_shared')->default(false);
            $table->integer('attentiveness_score')->nullable();
            $table->timestamps();

            $table->index('meeting_id');
            $table->index('user_id');
            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_meeting_participants');
    }
};
