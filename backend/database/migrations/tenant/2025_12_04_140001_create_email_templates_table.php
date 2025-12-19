<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('user');
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();

            // Content
            $table->string('subject');
            $table->longText('body_html');
            $table->longText('body_text')->nullable();

            // Configuration
            $table->json('variables')->default('[]');
            $table->json('attachments')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('category')->nullable();
            $table->json('tags')->default('[]');

            // Usage tracking
            $table->unsignedInteger('usage_count')->default(0);

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index(['module_id', 'is_active']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
