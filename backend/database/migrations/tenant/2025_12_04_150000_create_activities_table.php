<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Activity type and action
            $table->string('type'); // note, call, meeting, task, email, status_change, field_update, etc.
            $table->string('action')->nullable(); // created, updated, completed, sent, etc.

            // Subject (the record this activity is about)
            $table->string('subject_type'); // Module record type
            $table->unsignedBigInteger('subject_id');

            // Optional related entity (e.g., the email that was sent, the task that was completed)
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            // Content
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context data

            // For notes/comments
            $table->longText('content')->nullable();
            $table->boolean('is_pinned')->default(false);

            // For calls/meetings
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('outcome')->nullable();

            // Visibility
            $table->boolean('is_internal')->default(false); // Internal notes not shown to customers
            $table->boolean('is_system')->default(false); // Auto-generated system activities

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['subject_type', 'subject_id']);
            $table->index(['related_type', 'related_id']);
            $table->index(['user_id', 'type']);
            $table->index('type');
            $table->index('created_at');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
