<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('team_id')->nullable(); // No FK constraint - teams table may not exist
            $table->string('permission')->default('view'); // view, edit
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // A report can only be shared once with each user or team
            $table->unique(['report_id', 'user_id']);
            $table->unique(['report_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_shares');
    }
};
