<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('widget_key', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // position, greeting, offline message, etc.
            $table->json('styling')->nullable(); // colors, fonts, avatar, etc.
            $table->json('routing_rules')->nullable(); // department routing, round-robin, etc.
            $table->json('business_hours')->nullable(); // available hours per day
            $table->json('allowed_domains')->nullable(); // whitelisted domains
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_widgets');
    }
};
