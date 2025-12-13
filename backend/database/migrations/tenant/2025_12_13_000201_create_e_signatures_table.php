<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signature Requests
        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique(); // Public identifier
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('generated_documents')->nullOnDelete();
            $table->string('source_type')->nullable(); // quote, invoice, proposal, custom
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('file_path')->nullable(); // Uploaded document path
            $table->string('file_url')->nullable();
            $table->string('signed_file_path')->nullable(); // Final signed document
            $table->string('signed_file_url')->nullable();
            $table->string('status')->default('draft'); // draft, pending, in_progress, completed, declined, expired, voided
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->json('settings')->nullable(); // reminder settings, etc.
            $table->string('external_provider')->nullable(); // docusign, hellosign, pandadoc
            $table->string('external_id')->nullable(); // External service ID
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('status');
            $table->index('expires_at');
        });

        // Signature Signers
        Schema::create('signature_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('signature_requests')->cascadeOnDelete();
            $table->string('email');
            $table->string('name');
            $table->string('role')->default('signer'); // signer, viewer, approver, cc
            $table->unsignedInteger('sign_order')->default(1);
            $table->string('status')->default('pending'); // pending, viewed, signed, declined
            $table->string('access_token')->unique(); // For signing link
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason')->nullable();
            $table->string('signed_ip')->nullable();
            $table->string('signed_user_agent')->nullable();
            $table->json('signature_data')->nullable(); // Base64 signature image
            $table->unsignedBigInteger('contact_id')->nullable(); // Link to CRM contact
            $table->timestamps();

            $table->index('request_id');
            $table->index('email');
            $table->index(['request_id', 'sign_order']);
        });

        // Signature Fields (placement on document)
        Schema::create('signature_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('signature_requests')->cascadeOnDelete();
            $table->foreignId('signer_id')->constrained('signature_signers')->cascadeOnDelete();
            $table->string('field_type'); // signature, initials, date, text, checkbox
            $table->unsignedInteger('page_number')->default(1);
            $table->decimal('x_position', 8, 2); // Position from left
            $table->decimal('y_position', 8, 2); // Position from top
            $table->decimal('width', 8, 2)->default(200);
            $table->decimal('height', 8, 2)->default(50);
            $table->boolean('required')->default(true);
            $table->string('label')->nullable();
            $table->text('value')->nullable(); // Filled value
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->index('request_id');
            $table->index('signer_id');
        });

        // Signature Audit Log
        Schema::create('signature_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('signature_requests')->cascadeOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained('signature_signers')->nullOnDelete();
            $table->string('event_type'); // created, sent, viewed, signed, declined, completed, voided, reminded
            $table->text('event_description')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['request_id', 'created_at']);
            $table->index('event_type');
        });

        // Signature Templates (reusable field placements)
        Schema::create('signature_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('signers')->nullable(); // Default signer roles
            $table->json('fields')->nullable(); // Default field placements
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_templates');
        Schema::dropIfExists('signature_audit_logs');
        Schema::dropIfExists('signature_fields');
        Schema::dropIfExists('signature_signers');
        Schema::dropIfExists('signature_requests');
    }
};
