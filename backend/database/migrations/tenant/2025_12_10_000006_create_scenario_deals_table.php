<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('forecast_scenarios')->onDelete('cascade');
            $table->unsignedBigInteger('deal_record_id'); // Reference to module_records
            $table->foreignId('stage_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->integer('probability')->nullable(); // Override probability (0-100)
            $table->date('close_date')->nullable();
            $table->boolean('is_committed')->default(false);
            $table->boolean('is_excluded')->default(false); // Explicitly excluded from scenario
            $table->text('notes')->nullable();
            $table->json('original_data')->nullable(); // Store original values for comparison
            $table->timestamps();

            $table->index(['scenario_id', 'deal_record_id']);
            $table->index(['scenario_id', 'stage_id']);
            $table->index('is_committed');
            $table->unique(['scenario_id', 'deal_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_deals');
    }
};
