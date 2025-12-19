<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->enum('type', ['all', 'marketing', 'transactional'])->default('all');
            $table->string('reason')->nullable(); // STOP, user request, bounce, etc.
            $table->foreignId('connection_id')->nullable()->constrained('sms_connections')->nullOnDelete();
            $table->timestamp('opted_out_at');
            $table->timestamp('opted_in_at')->nullable(); // If they re-subscribe
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['phone_number', 'type']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_opt_outs');
    }
};
