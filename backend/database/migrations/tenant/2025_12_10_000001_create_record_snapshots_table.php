<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('record_id');
            $table->jsonb('snapshot_data');
            $table->string('snapshot_type', 50)->default('field_change'); // field_change, stage_change, daily, manual
            $table->jsonb('change_summary')->nullable(); // what changed from previous
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for fast time-based queries
            $table->index(['module_id', 'record_id', 'created_at'], 'idx_snapshots_record_time');
            $table->index(['created_at'], 'idx_snapshots_time');
            $table->index(['snapshot_type'], 'idx_snapshots_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_snapshots');
    }
};
