<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('record_id');
            $table->string('field_api_name', 100);
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            // Index for efficient queries by record and time
            $table->index(['module_id', 'record_id', 'changed_at'], 'idx_field_changes');
            $table->index(['field_api_name'], 'idx_field_changes_field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_change_logs');
    }
};
