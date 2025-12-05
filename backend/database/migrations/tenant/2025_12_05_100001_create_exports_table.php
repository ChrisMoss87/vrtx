<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type'); // csv, xlsx, pdf
            $table->bigInteger('file_size')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired'])->default('pending');
            $table->integer('total_records')->default(0);
            $table->integer('exported_records')->default(0);
            $table->json('selected_fields'); // Fields to include in export
            $table->json('filters')->nullable(); // Filter conditions for records
            $table->json('sorting')->nullable(); // Sort order for records
            $table->json('export_options')->nullable(); // include_headers, date_format, etc.
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Auto-delete exported file
            $table->integer('download_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
        });

        // Saved export templates for reuse
        Schema::create('export_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Null = shared
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('selected_fields');
            $table->json('filters')->nullable();
            $table->json('sorting')->nullable();
            $table->json('export_options')->nullable();
            $table->string('default_file_type')->default('csv');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module_id', 'is_shared']);
        });

        // Scheduled imports/exports
        Schema::create('scheduled_data_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('job_type', ['import', 'export']);
            $table->string('cron_expression'); // Cron schedule
            $table->boolean('is_active')->default(true);
            $table->json('job_config'); // Import mapping or export template config
            $table->string('source_type')->nullable(); // For imports: sftp, url, email
            $table->json('source_config')->nullable(); // Connection details
            $table->string('destination_type')->nullable(); // For exports: sftp, email, webhook
            $table->json('destination_config')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->integer('run_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['job_type', 'is_active']);
            $table->index('next_run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_data_jobs');
        Schema::dropIfExists('export_templates');
        Schema::dropIfExists('exports');
    }
};
