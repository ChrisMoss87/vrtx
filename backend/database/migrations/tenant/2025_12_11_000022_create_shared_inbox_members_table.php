<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_inbox_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inbox_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member'); // admin, member
            $table->boolean('can_reply')->default(true);
            $table->boolean('can_assign')->default(false);
            $table->boolean('can_close')->default(true);
            $table->boolean('receives_notifications')->default(true);
            $table->integer('active_conversation_limit')->nullable(); // Max concurrent assignments
            $table->integer('current_active_count')->default(0);
            $table->timestamps();

            $table->foreign('inbox_id')->references('id')->on('shared_inboxes')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['inbox_id', 'user_id']);
            $table->index(['user_id', 'inbox_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_inbox_members');
    }
};
