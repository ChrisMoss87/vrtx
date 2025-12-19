<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained('chat_widgets')->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained('chat_visitors')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('module_records')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open'); // open, pending, closed
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('department')->nullable();
            $table->string('subject')->nullable();
            $table->json('tags')->nullable();
            $table->integer('message_count')->default(0);
            $table->integer('visitor_message_count')->default(0);
            $table->integer('agent_message_count')->default(0);
            $table->decimal('rating', 2, 1)->nullable(); // visitor satisfaction rating
            $table->text('rating_comment')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['widget_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
