<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Document folders
        Schema::create('collaborative_document_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('collaborative_document_folders')->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('color', 7)->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'parent_id']);
        });

        // Main collaborative documents table
        Schema::create('collaborative_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 20)->default('word'); // word, spreadsheet, presentation
            $table->binary('yjs_state')->nullable(); // Y.js document state
            $table->text('html_snapshot')->nullable(); // For previews
            $table->text('text_content')->nullable(); // Plain text for full-text search
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_folder_id')->nullable()->constrained('collaborative_document_folders')->nullOnDelete();
            $table->boolean('is_template')->default(false);
            $table->boolean('is_publicly_shared')->default(false);
            $table->jsonb('share_settings')->nullable(); // {token, permission, password_hash, expires_at, allow_download, require_email}
            $table->integer('current_version')->default(1);
            $table->integer('character_count')->default(0);
            $table->integer('word_count')->default(0);
            $table->timestamp('last_edited_at')->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'type']);
            $table->index('parent_folder_id');
            $table->index('is_template');
            $table->index('is_publicly_shared');
            $table->index('last_edited_at');
        });

        // Add full-text search index for PostgreSQL
        if (config('database.default') === 'pgsql') {
            DB::statement('CREATE INDEX collaborative_documents_text_search_idx ON collaborative_documents USING gin(to_tsvector(\'english\', coalesce(title, \'\') || \' \' || coalesce(text_content, \'\')))');
        }

        // Document collaborators (people with access)
        Schema::create('document_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('collaborative_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission', 20)->default('view'); // view, comment, edit
            $table->jsonb('cursor_position')->nullable(); // {line, column, selection}
            $table->boolean('is_currently_viewing')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
            $table->index(['user_id', 'permission']);
            $table->index(['document_id', 'is_currently_viewing']);
        });

        // Document versions (for history)
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('collaborative_documents')->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('label')->nullable();
            $table->binary('yjs_state'); // Y.js document state snapshot
            $table->text('html_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_auto_save')->default(false);
            $table->timestamp('created_at');

            $table->unique(['document_id', 'version_number']);
            $table->index(['document_id', 'created_at']);
            $table->index(['document_id', 'is_auto_save']);
        });

        // Document comments
        Schema::create('document_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('collaborative_documents')->cascadeOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained('document_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->jsonb('selection_range')->nullable(); // {from, to} positions in document
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_id', 'thread_id']);
            $table->index(['document_id', 'is_resolved']);
            $table->index('user_id');
        });

        // External shares (for portal users and email-based sharing)
        Schema::create('document_external_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('collaborative_documents')->cascadeOnDelete();
            $table->string('share_token', 64)->unique();
            $table->string('permission', 20)->default('view'); // view, comment, edit
            $table->string('password_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('portal_user_id')->nullable()->constrained('portal_users')->cascadeOnDelete();
            $table->string('email')->nullable(); // For email-only access
            $table->string('name')->nullable(); // Display name for external user
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_accessed_at')->nullable();
            $table->integer('access_count')->default(0);
            $table->timestamps();

            $table->index(['document_id', 'portal_user_id']);
            $table->index(['document_id', 'email']);
            $table->index('expires_at');
        });

        // Change log for real-time sync debugging and analytics
        Schema::create('document_change_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('collaborative_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->binary('yjs_update'); // Incremental Y.js update
            $table->string('change_type', 50)->nullable(); // insert, delete, format, etc.
            $table->integer('change_size')->default(0); // Size of change in bytes
            $table->timestamp('created_at');

            $table->index(['document_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_change_log');
        Schema::dropIfExists('document_external_shares');
        Schema::dropIfExists('document_comments');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_collaborators');
        Schema::dropIfExists('collaborative_documents');
        Schema::dropIfExists('collaborative_document_folders');
    }
};
