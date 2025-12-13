<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 100)->unique(); // e.g., 'forecasting.ai_predictions'
            $table->string('plugin_slug', 50)->nullable(); // Which plugin enables this
            $table->string('plan_required', 50)->nullable(); // Minimum plan required
            $table->boolean('is_enabled')->default(false); // Tenant override
            $table->jsonb('config')->nullable(); // Feature-specific config
            $table->timestamps();

            $table->index('plugin_slug');
            $table->index('plan_required');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
