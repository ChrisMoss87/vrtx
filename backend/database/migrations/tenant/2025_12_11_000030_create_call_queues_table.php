<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_queues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('provider_id');
            $table->string('phone_number')->nullable(); // Queue's dedicated number
            $table->string('routing_strategy')->default('round_robin'); // round_robin, longest_idle, skills_based
            $table->integer('max_wait_time_seconds')->default(300);
            $table->integer('max_queue_size')->default(50);
            $table->text('welcome_message')->nullable();
            $table->text('hold_music_url')->nullable();
            $table->text('voicemail_greeting')->nullable();
            $table->boolean('voicemail_enabled')->default(true);
            $table->jsonb('business_hours')->nullable();
            $table->text('after_hours_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('call_providers')->cascadeOnDelete();
        });

        Schema::create('call_queue_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('queue_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('offline'); // online, offline, busy, break
            $table->timestamp('last_call_at')->nullable();
            $table->integer('calls_handled_today')->default(0);
            $table->timestamps();

            $table->foreign('queue_id')->references('id')->on('call_queues')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['queue_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_queue_members');
        Schema::dropIfExists('call_queues');
    }
};
