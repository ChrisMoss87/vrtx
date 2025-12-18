<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create workflow templates table for pre-built automation templates.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('category'); // lead, deal, customer, data, productivity, communication
            $table->string('icon')->nullable();
            $table->json('workflow_data'); // Complete workflow JSON (trigger, conditions, steps)
            $table->json('required_modules')->nullable(); // Modules that must exist
            $table->json('required_fields')->nullable(); // Fields that must exist
            $table->json('variable_mappings')->nullable(); // User must configure these
            $table->boolean('is_system')->default(false); // System vs user-created
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->string('difficulty')->default('beginner'); // beginner, intermediate, advanced
            $table->integer('estimated_time_saved_hours')->nullable(); // Estimated time saved per month
            $table->timestamps();

            $table->index('category');
            $table->index('is_system');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
    }
};
