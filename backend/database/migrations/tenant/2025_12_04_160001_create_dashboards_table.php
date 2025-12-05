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
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(false);

            // Layout configuration (react-grid-layout compatible)
            $table->jsonb('layout')->default('[]');
            $table->jsonb('settings')->default('{}');
            $table->jsonb('filters')->default('{}'); // Global dashboard filters

            // Auto-refresh interval in seconds (0 = disabled)
            $table->integer('refresh_interval')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'is_default']);
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboards');
    }
};
