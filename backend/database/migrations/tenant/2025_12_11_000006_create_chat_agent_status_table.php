<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_agent_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('offline'); // online, away, busy, offline
            $table->integer('max_conversations')->default(5);
            $table->integer('active_conversations')->default(0);
            $table->json('departments')->nullable(); // departments agent handles
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_agent_status');
    }
};
