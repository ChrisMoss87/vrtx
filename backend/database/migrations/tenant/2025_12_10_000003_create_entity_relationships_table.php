<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('from_entity_type', 50); // 'contact', 'company', 'deal', 'user'
            $table->unsignedBigInteger('from_entity_id');
            $table->string('to_entity_type', 50);
            $table->unsignedBigInteger('to_entity_id');
            $table->string('relationship_type', 50); // 'works_at', 'referred_by', 'influenced', etc.
            $table->integer('strength')->default(1); // 1-10 relationship strength
            $table->jsonb('metadata')->default('{}');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unique constraint to prevent duplicate relationships
            $table->unique(
                ['from_entity_type', 'from_entity_id', 'to_entity_type', 'to_entity_id', 'relationship_type'],
                'idx_entity_rel_unique'
            );

            // Indexes for graph queries
            $table->index(['from_entity_type', 'from_entity_id'], 'idx_rel_from');
            $table->index(['to_entity_type', 'to_entity_id'], 'idx_rel_to');
            $table->index(['relationship_type'], 'idx_rel_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_relationships');
    }
};
