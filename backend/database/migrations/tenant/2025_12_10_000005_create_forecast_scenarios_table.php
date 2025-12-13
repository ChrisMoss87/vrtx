<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->nullable()->constrained()->onDelete('set null');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('scenario_type')->default('custom'); // current, best_case, worst_case, target_hit, custom
            $table->boolean('is_baseline')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->decimal('total_weighted', 15, 2)->default(0);
            $table->decimal('total_unweighted', 15, 2)->default(0);
            $table->decimal('target_amount', 15, 2)->nullable();
            $table->integer('deal_count')->default(0);
            $table->json('settings')->nullable(); // Additional scenario configuration
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'scenario_type']);
            $table->index(['period_start', 'period_end']);
            $table->index('is_shared');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_scenarios');
    }
};
