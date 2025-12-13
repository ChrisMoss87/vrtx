<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_room_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('deal_rooms')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('deal_room_members')->onDelete('set null');
            $table->string('assigned_party', 20)->nullable(); // seller, buyer, both
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending, in_progress, completed
            $table->integer('display_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('deal_room_members')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->index(['room_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_room_action_items');
    }
};
