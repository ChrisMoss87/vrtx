<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email_address');
            $table->string('provider')->default('imap');

            // IMAP settings
            $table->string('imap_host')->nullable();
            $table->integer('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl');

            // SMTP settings
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->default(587);
            $table->string('smtp_encryption')->default('tls');

            // Credentials
            $table->string('username')->nullable();
            $table->text('password')->nullable();

            // OAuth
            $table->text('oauth_token')->nullable();
            $table->text('oauth_refresh_token')->nullable();
            $table->timestamp('oauth_expires_at')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_uid')->nullable();

            // Configuration
            $table->json('sync_folders')->default('["INBOX"]');
            $table->text('signature')->nullable();
            $table->json('settings')->default('{}');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
