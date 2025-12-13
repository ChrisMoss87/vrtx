<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website', 500)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->text('market_position')->nullable();
            $table->text('pricing_info')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
    }
};
