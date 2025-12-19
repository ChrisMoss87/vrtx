<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained('chat_widgets')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('module_records')->nullOnDelete();
            $table->string('fingerprint', 64)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->json('custom_data')->nullable(); // any custom fields collected
            $table->json('pages_viewed')->nullable(); // array of page views with timestamps
            $table->string('current_page')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['widget_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_visitors');
    }
};
