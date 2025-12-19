<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battlecard_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->onDelete('cascade');
            $table->string('section_type', 50); // strengths, weaknesses, counters, pricing, resources
            $table->text('content');
            $table->integer('display_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['competitor_id', 'section_type']);
            $table->index(['competitor_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battlecard_sections');
    }
};
