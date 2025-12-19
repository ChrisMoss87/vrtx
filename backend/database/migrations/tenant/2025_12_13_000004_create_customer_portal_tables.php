<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Portal Users - external customer users
        Schema::create('portal_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();

            // Link to CRM contact
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('contact_module')->default('contacts');

            // Link to account/company
            $table->unsignedBigInteger('account_id')->nullable();

            // Status
            $table->string('status')->default('pending'); // pending, active, suspended
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();

            // Preferences
            $table->jsonb('preferences')->default('{}');
            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('en');

            // Security
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['contact_id', 'contact_module']);
            $table->index('account_id');
        });

        // Portal Access Tokens
        Schema::create('portal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Portal Settings - tenant-level portal configuration
        Schema::create('portal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, json, integer
            $table->timestamps();
        });

        // Portal Invitations
        Schema::create('portal_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('role')->default('user'); // user, admin

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });

        // Portal Activity Log
        Schema::create('portal_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('action'); // login, view_deal, download_document, submit_ticket, etc.
            $table->string('resource_type')->nullable(); // deal, invoice, document, ticket
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['portal_user_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
        });

        // Portal Announcements
        Schema::create('portal_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('type')->default('info'); // info, warning, success, error
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dismissible')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('target_accounts')->default('[]'); // Empty = all accounts
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Portal Document Shares - documents shared with portal users
        Schema::create('portal_document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->nullable()->constrained('portal_users')->cascadeOnDelete();
            $table->unsignedBigInteger('account_id')->nullable(); // Share with entire account

            // The document being shared
            $table->string('document_type'); // quote, invoice, contract, file
            $table->unsignedBigInteger('document_id');

            // Access settings
            $table->boolean('can_download')->default(true);
            $table->boolean('requires_signature')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_ip')->nullable();

            // Tracking
            $table->integer('view_count')->default(0);
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['portal_user_id', 'document_type']);
        });

        // Portal Notifications
        Schema::create('portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('type'); // deal_update, invoice_due, document_shared, ticket_reply
            $table->string('title');
            $table->text('message');
            $table->string('action_url')->nullable();
            $table->jsonb('data')->default('{}');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['portal_user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_notifications');
        Schema::dropIfExists('portal_document_shares');
        Schema::dropIfExists('portal_announcements');
        Schema::dropIfExists('portal_activity_logs');
        Schema::dropIfExists('portal_invitations');
        Schema::dropIfExists('portal_settings');
        Schema::dropIfExists('portal_access_tokens');
        Schema::dropIfExists('portal_users');
    }
};
