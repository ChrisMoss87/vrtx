<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_meeting_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('video_meetings')->onDelete('cascade');
            $table->string('external_recording_id')->nullable();
            $table->string('type')->default('video'); // video, audio, transcript, chat
            $table->string('status')->default('processing'); // processing, completed, failed, deleted
            $table->string('file_url')->nullable();
            $table->string('download_url')->nullable();
            $table->string('play_url')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('format')->nullable(); // mp4, m4a, vtt, txt
            $table->timestamp('recording_start')->nullable();
            $table->timestamp('recording_end')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('transcript_text')->nullable();
            $table->json('transcript_segments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('meeting_id');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_meeting_recordings');
    }
};
