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
        Schema::create('module_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('target_module_id')->constrained('modules')->onDelete('cascade');
            $table->foreignId('field_id')->constrained('fields')->onDelete('cascade');
            $table->string('type'); // one_to_many, many_to_many
            $table->string('name');
            $table->string('inverse_name')->nullable();
            $table->timestamps();

            $table->unique(['source_module_id', 'field_id']);
            $table->index(['target_module_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_relationships');
    }
};
