<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Document Templates
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable(); // contract, proposal, letter, agreement, etc.
            $table->string('description')->nullable();
            $table->longText('content'); // Rich text with merge fields
            $table->json('merge_fields')->nullable(); // List of merge fields used
            $table->json('conditional_blocks')->nullable(); // Conditional content logic
            $table->string('output_format')->default('pdf'); // pdf, docx, html
            $table->json('page_settings')->nullable(); // margins, orientation, paper size
            $table->json('header_settings')->nullable();
            $table->json('footer_settings')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_shared')->default(false); // Shared with team
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
            $table->index('created_by');
        });

        // Document Template Variables (predefined merge fields)
        Schema::create('document_template_variables', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Variable name
            $table->string('api_name'); // {{contact.name}}
            $table->string('category'); // contact, company, deal, user, custom
            $table->string('field_path'); // contacts.first_name
            $table->string('default_value')->nullable();
            $table->string('format')->nullable(); // date, currency, number
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique('api_name');
            $table->index('category');
        });

        // Generated Documents
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->string('record_type'); // Module name
            $table->unsignedBigInteger('record_id');
            $table->string('name');
            $table->string('output_format')->default('pdf');
            $table->string('file_path')->nullable(); // Storage path
            $table->string('file_url')->nullable(); // Public URL
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('merged_data')->nullable(); // Snapshot of merged data
            $table->string('status')->default('generated'); // generated, sent, viewed, signed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['record_type', 'record_id']);
            $table->index('template_id');
            $table->index('status');
        });

        // Document Send History
        Schema::create('document_send_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('generated_documents')->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('sent'); // sent, delivered, opened, bounced
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('document_id');
            $table->index('recipient_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_send_logs');
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_template_variables');
        Schema::dropIfExists('document_templates');
    }
};
