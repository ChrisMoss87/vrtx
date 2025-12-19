<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recording_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_id')->constrained()->onDelete('cascade');
            $table->integer('step_order');
            $table->string('action_type', 50); // create_record, update_field, change_stage, send_email, etc.
            $table->string('target_module', 100)->nullable();
            $table->unsignedBigInteger('target_record_id')->nullable();
            $table->jsonb('action_data'); // captured action details
            $table->jsonb('parameterized_data')->nullable(); // data with field references
            $table->boolean('is_parameterized')->default(false);
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['recording_id', 'step_order']);
            $table->index('action_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_steps');
    }
};
