<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create workflow versions table for tracking workflow history and enabling rollback.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->integer('version_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('workflow_data'); // Complete snapshot of workflow configuration
            $table->json('steps_data'); // Snapshot of all steps
            $table->string('trigger_type');
            $table->json('trigger_config')->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('is_active_version')->default(false); // Is this the currently active version?
            $table->string('change_summary')->nullable(); // Brief description of changes
            $table->string('change_type')->default('update'); // create, update, rollback, restore
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('workflows')
                ->onDelete('cascade');

            $table->unique(['workflow_id', 'version_number']);
            $table->index(['workflow_id', 'is_active_version']);
            $table->index('created_at');
        });

        // Add version tracking fields to workflows table
        Schema::table('workflows', function (Blueprint $table) {
            $table->integer('current_version')->default(1)->after('failure_count');
            $table->unsignedBigInteger('active_version_id')->nullable()->after('current_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['current_version', 'active_version_id']);
        });

        Schema::dropIfExists('workflow_versions');
    }
};
