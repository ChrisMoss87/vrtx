<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type'); // csv, xlsx, xls
            $table->bigInteger('file_size');
            $table->enum('status', ['pending', 'validating', 'validated', 'importing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            $table->json('column_mapping')->nullable(); // Maps file columns to module fields
            $table->json('import_options')->nullable(); // duplicate_handling, skip_empty, default_values, etc.
            $table->json('validation_errors')->nullable(); // Errors found during validation
            $table->json('field_transformations')->nullable(); // Value transformations per field
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->integer('row_number');
            $table->json('original_data');
            $table->json('mapped_data')->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'skipped'])->default('pending');
            $table->foreignId('record_id')->nullable()->constrained('module_records')->nullOnDelete();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'status']);
            $table->index(['import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
    }
};
