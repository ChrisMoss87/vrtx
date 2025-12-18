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
        Schema::create('wizards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('api_name')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['record_creation', 'record_edit', 'standalone'])->default('record_creation');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable(); // showProgress, allowClickNavigation, saveAsDraft, etc.
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['module_id', 'is_active']);
            $table->index('api_name');
        });

        Schema::create('wizard_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wizard_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['form', 'review', 'confirmation', 'custom'])->default('form');
            $table->json('fields')->nullable(); // Array of field IDs assigned to this step
            $table->boolean('can_skip')->default(false);
            $table->integer('display_order')->default(0);
            $table->json('conditional_logic')->nullable(); // Skip conditions, show/hide rules
            $table->json('validation_rules')->nullable(); // Custom validation for the step
            $table->timestamps();

            $table->index(['wizard_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wizard_steps');
        Schema::dropIfExists('wizards');
    }
};
