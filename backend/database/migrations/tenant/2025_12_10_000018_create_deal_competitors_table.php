<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_competitors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id');
            $table->foreignId('competitor_id')->constrained()->onDelete('cascade');
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->string('outcome', 20)->nullable(); // won, lost, unknown
            $table->timestamps();

            $table->unique(['deal_id', 'competitor_id']);
            $table->index('deal_id');
            $table->index(['competitor_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_competitors');
    }
};
