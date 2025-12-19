<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_email_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // compose, reply, improve, translate
            $table->string('tone')->nullable(); // formal, friendly, urgent, professional
            $table->jsonb('context')->default('{}'); // CRM context (deal, contact, etc.)
            $table->text('prompt')->nullable(); // User's instruction
            $table->text('original_content')->nullable(); // For improve/translate
            $table->text('generated_subject')->nullable();
            $table->text('generated_content');
            $table->string('model_used');
            $table->integer('tokens_used')->default(0);
            $table->boolean('was_used')->default(false); // Was this draft actually sent?
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('ai_subject_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_id')->nullable()->constrained('ai_email_drafts')->cascadeOnDelete();
            $table->text('email_content');
            $table->jsonb('suggestions')->default('[]'); // Array of suggested subjects
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_subject_suggestions');
        Schema::dropIfExists('ai_email_drafts');
    }
};
