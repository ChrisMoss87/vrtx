<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_record_id'); // Reference to module_records (deal)
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active, won, lost, archived
            $table->json('branding')->nullable(); // Logo, colors, etc
            $table->json('settings')->nullable(); // Notification preferences, access settings
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index('deal_record_id');
            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_rooms');
    }
};
