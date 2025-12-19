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
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('block_id')->nullable()->constrained('blocks')->onDelete('cascade');
            $table->string('label');
            $table->string('api_name');
            $table->string('type'); // text, select, date, etc.
            $table->text('description')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_searchable')->default(true);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_sortable')->default(true);
            $table->jsonb('validation_rules')->default('[]');
            $table->jsonb('settings')->default('{}');
            $table->string('default_value')->nullable();
            $table->integer('display_order')->default(0);
            $table->integer('width')->default(100); // Percentage width
            $table->timestamps();

            $table->unique(['module_id', 'api_name']);
            $table->index(['module_id', 'block_id', 'display_order']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
