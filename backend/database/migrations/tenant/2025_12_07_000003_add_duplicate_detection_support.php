<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Duplicate detection rules - configurable per module
        Schema::create('duplicate_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('action')->default('warn'); // 'warn', 'block'
            $table->jsonb('conditions'); // Matching rules with logic
            $table->integer('priority')->default(0); // Higher = checked first
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['module_id', 'is_active']);
        });

        // Detected duplicate candidates awaiting review
        Schema::create('duplicate_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('record_id_a');
            $table->unsignedBigInteger('record_id_b');
            $table->decimal('match_score', 5, 4); // 0.0000 to 1.0000
            $table->jsonb('matched_rules')->nullable(); // Which rules matched and how
            $table->string('status')->default('pending'); // 'pending', 'merged', 'dismissed'
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('dismiss_reason')->nullable();
            $table->timestamps();

            // Ensure unique pair (order matters for consistency)
            $table->unique(['record_id_a', 'record_id_b']);
            $table->index(['module_id', 'status']);
            $table->index(['match_score']);
        });

        // Merge audit log - track all merge operations
        Schema::create('merge_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('surviving_record_id'); // The record that remains
            $table->jsonb('merged_record_ids'); // Array of IDs that were merged in
            $table->jsonb('field_selections'); // Which values were kept from which record
            $table->jsonb('merged_data')->nullable(); // Snapshot of merged records' data
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['module_id']);
            $table->index(['surviving_record_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merge_logs');
        Schema::dropIfExists('duplicate_candidates');
        Schema::dropIfExists('duplicate_rules');
    }
};
