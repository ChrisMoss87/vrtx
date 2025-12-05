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
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();

            $table->string('title');
            $table->string('type'); // report, kpi, chart, table, activity, pipeline, tasks, calendar, text, iframe

            // Widget configuration
            $table->jsonb('config')->default('{}');

            // Position in the dashboard (for ordering)
            $table->integer('position')->default(0);

            // Default size (width/height in grid units)
            $table->jsonb('size')->default('{"w": 6, "h": 4}');

            // Widget-specific refresh interval (overrides dashboard)
            $table->integer('refresh_interval')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['dashboard_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
