<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_canned_responses', function (Blueprint $table) {
            $table->id();
            $table->string('shortcut', 50)->index(); // e.g., /hello, /pricing
            $table->string('title');
            $table->text('content');
            $table->string('category')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_global')->default(true); // available to all agents
            $table->integer('usage_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_canned_responses');
    }
};
