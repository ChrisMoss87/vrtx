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
        Schema::create('wizard_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('wizard_type'); // e.g., 'module_creation', 'record_creation'
            $table->string('reference_id')->nullable(); // e.g., module_id or record_id
            $table->string('name')->nullable(); // User-friendly name for the draft
            $table->json('form_data'); // Wizard form data
            $table->json('steps_state'); // Step completion/validation states
            $table->integer('current_step_index')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes for faster queries
            $table->index(['user_id', 'wizard_type']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wizard_drafts');
    }
};
