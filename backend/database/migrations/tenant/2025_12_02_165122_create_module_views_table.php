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
        Schema::create('module_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('filters')->default('[]');
            $table->jsonb('sorting')->default('[]');
            $table->jsonb('column_visibility')->default('{}');
            $table->jsonb('column_order')->nullable();
            $table->jsonb('column_widths')->nullable();
            $table->integer('page_size')->default(50);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['module_id', 'user_id']);
            $table->index(['module_id', 'is_default']);
            $table->index(['module_id', 'is_shared']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_views');
    }
};
