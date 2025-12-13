<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->string('template_id')->nullable(); // Meta template ID
            $table->string('name');
            $table->string('language')->default('en');
            $table->enum('category', ['UTILITY', 'MARKETING', 'AUTHENTICATION']);
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED'])->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->json('components'); // Header, body, footer, buttons
            $table->json('example')->nullable(); // Example values for variables
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
