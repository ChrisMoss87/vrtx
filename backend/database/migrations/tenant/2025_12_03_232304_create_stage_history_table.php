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
        Schema::create('stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_record_id')->constrained('module_records')->onDelete('cascade');
            $table->foreignId('pipeline_id')->constrained('pipelines')->onDelete('cascade');
            $table->foreignId('from_stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('stages')->onDelete('cascade');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->integer('duration_in_stage')->nullable();
            $table->timestamps();

            $table->index(['module_record_id', 'pipeline_id']);
            $table->index(['pipeline_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_history');
    }
};
