<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inbox_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('priority')->default(0);
            $table->jsonb('conditions'); // [{field, operator, value}]
            $table->string('condition_match')->default('all'); // all, any
            $table->jsonb('actions'); // [{type, value}] - assign, tag, priority, auto_reply, etc.
            $table->boolean('is_active')->default(true);
            $table->boolean('stop_processing')->default(false); // Stop other rules after this
            $table->integer('triggered_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('inbox_id')->references('id')->on('shared_inboxes')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['inbox_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_rules');
    }
};
