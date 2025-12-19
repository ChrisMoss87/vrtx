<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_inboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('support'); // support, sales, general
            $table->string('imap_host')->nullable();
            $table->integer('imap_port')->nullable();
            $table->string('imap_encryption')->nullable(); // ssl, tls, none
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable(); // Encrypted
            $table->boolean('is_active')->default(true);
            $table->boolean('is_connected')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->jsonb('settings')->nullable(); // Auto-reply, signature, etc.
            $table->unsignedBigInteger('default_assignee_id')->nullable();
            $table->string('assignment_method')->default('round_robin'); // round_robin, load_balanced, manual
            $table->timestamps();

            $table->foreign('default_assignee_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_inboxes');
    }
};
