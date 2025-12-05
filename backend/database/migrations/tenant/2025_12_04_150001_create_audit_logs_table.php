<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // What was changed
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');

            // Change details
            $table->string('event'); // created, updated, deleted, restored, etc.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->json('tags')->nullable(); // For categorization/filtering

            // Batch tracking for related changes
            $table->uuid('batch_id')->nullable();

            $table->timestamp('created_at');

            // Indexes
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
