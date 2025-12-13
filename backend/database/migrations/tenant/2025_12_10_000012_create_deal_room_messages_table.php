<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_room_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('deal_rooms')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('deal_room_members')->onDelete('cascade');
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->boolean('is_internal')->default(false); // Internal team only
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
            $table->index(['room_id', 'is_internal']);
        });

        Schema::create('deal_room_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('deal_rooms')->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('deal_room_members')->onDelete('set null');
            $table->string('activity_type', 50);
            $table->json('activity_data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['room_id', 'created_at']);
            $table->index(['room_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_room_activities');
        Schema::dropIfExists('deal_room_messages');
    }
};
