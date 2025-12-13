<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_transcriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('call_id');
            $table->string('status'); // pending, processing, completed, failed
            $table->text('full_text')->nullable();
            $table->jsonb('segments')->nullable(); // [{speaker, text, start_time, end_time}]
            $table->string('language')->nullable();
            $table->float('confidence')->nullable();
            $table->string('provider')->nullable(); // whisper, deepgram, assembly_ai
            $table->text('summary')->nullable(); // AI-generated summary
            $table->jsonb('key_points')->nullable(); // AI-extracted key points
            $table->jsonb('action_items')->nullable(); // AI-extracted action items
            $table->string('sentiment')->nullable(); // positive, neutral, negative
            $table->jsonb('entities')->nullable(); // Extracted entities (names, companies, etc)
            $table->integer('word_count')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('call_id')->references('id')->on('calls')->cascadeOnDelete();
            $table->index(['call_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_transcriptions');
    }
};
