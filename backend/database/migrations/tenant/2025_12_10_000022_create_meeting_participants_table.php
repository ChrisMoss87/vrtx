<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('synced_meetings')->onDelete('cascade');
            $table->string('email', 255);
            $table->string('name', 255)->nullable();
            $table->unsignedBigInteger('contact_id')->nullable(); // matched CRM contact
            $table->boolean('is_organizer')->default(false);
            $table->string('response_status', 20)->nullable(); // accepted, declined, tentative, needsAction
            $table->timestamps();

            $table->index(['meeting_id', 'email']);
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
