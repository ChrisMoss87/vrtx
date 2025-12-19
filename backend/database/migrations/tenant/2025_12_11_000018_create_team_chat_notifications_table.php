<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_chat_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('team_chat_connections')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('team_chat_channels')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_event'); // deal.won, lead.created, task.completed, etc.
            $table->string('trigger_module')->nullable();
            $table->json('trigger_conditions')->nullable(); // Filter conditions
            $table->text('message_template'); // Message template with placeholders
            $table->boolean('include_mentions')->default(false);
            $table->string('mention_field')->nullable(); // Field to get user for mention
            $table->boolean('is_active')->default(true);
            $table->integer('triggered_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('trigger_event');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_chat_notifications');
    }
};
