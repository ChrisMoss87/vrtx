<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('content');
            $table->string('category')->nullable(); // marketing, transactional, support
            $table->boolean('is_active')->default(true);
            $table->json('merge_fields')->nullable(); // Available merge fields like {{first_name}}
            $table->integer('character_count')->default(0);
            $table->integer('segment_count')->default(1); // SMS segments needed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
