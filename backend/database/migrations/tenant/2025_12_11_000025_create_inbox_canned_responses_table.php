<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_canned_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inbox_id')->nullable(); // null = global
            $table->string('name');
            $table->string('shortcut')->nullable();
            $table->string('category')->nullable();
            $table->text('subject')->nullable();
            $table->text('body');
            $table->jsonb('attachments')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('use_count')->default(0);
            $table->timestamps();

            $table->foreign('inbox_id')->references('id')->on('shared_inboxes')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['inbox_id', 'is_active']);
            $table->index(['shortcut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_canned_responses');
    }
};
