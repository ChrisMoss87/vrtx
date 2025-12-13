<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objection_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objection_id')->constrained('competitor_objections')->onDelete('cascade');
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->boolean('was_successful');
            $table->text('feedback')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('objection_id');
            $table->index('deal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objection_feedback');
    }
};
