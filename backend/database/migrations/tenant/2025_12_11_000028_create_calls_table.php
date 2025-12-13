<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->string('external_call_id')->nullable(); // Provider's call ID
            $table->string('direction'); // inbound, outbound
            $table->string('status'); // initiated, ringing, in_progress, completed, busy, failed, no_answer, canceled
            $table->string('from_number');
            $table->string('to_number');
            $table->unsignedBigInteger('user_id')->nullable(); // CRM user who made/received call
            $table->unsignedBigInteger('contact_id')->nullable(); // Linked contact record
            $table->string('contact_module')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('ring_duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('recording_sid')->nullable();
            $table->integer('recording_duration_seconds')->nullable();
            $table->string('recording_status')->nullable(); // processing, completed, failed
            $table->text('notes')->nullable();
            $table->string('outcome')->nullable(); // interested, not_interested, callback, voicemail, wrong_number, etc.
            $table->jsonb('custom_fields')->nullable();
            $table->jsonb('metadata')->nullable(); // Provider-specific data
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('call_providers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('module_records')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index(['contact_id']);
            $table->index(['status', 'created_at']);
            $table->index(['external_call_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
