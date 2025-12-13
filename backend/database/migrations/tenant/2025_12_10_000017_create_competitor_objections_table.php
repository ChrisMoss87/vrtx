<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_objections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->onDelete('cascade');
            $table->text('objection');
            $table->text('counter_script');
            $table->decimal('effectiveness_score', 5, 2)->nullable();
            $table->integer('use_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('competitor_id');
            $table->index('effectiveness_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_objections');
    }
};
