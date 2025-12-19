<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('deal_rooms')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // Internal users
            $table->string('external_email')->nullable(); // External stakeholders
            $table->string('external_name')->nullable();
            $table->string('role', 50)->default('viewer'); // owner, team, stakeholder, viewer
            $table->string('access_token', 100)->nullable()->unique(); // For external access
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'role']);
            $table->index('external_email');
            $table->index('access_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_room_members');
    }
};
