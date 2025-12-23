<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // table, chart, summary, matrix, pivot
            $table->string('chart_type')->nullable();
            $table->boolean('is_public')->default(false);
            $table->jsonb('config')->default('{}');
            $table->jsonb('filters')->default('[]');
            $table->jsonb('grouping')->default('[]');
            $table->jsonb('aggregations')->default('[]');
            $table->jsonb('sorting')->default('[]');
            $table->jsonb('date_range')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'module_id']);
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
