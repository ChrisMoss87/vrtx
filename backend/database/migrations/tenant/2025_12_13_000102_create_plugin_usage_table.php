<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_usage', function (Blueprint $table) {
            $table->id();
            $table->string('plugin_slug', 50);
            $table->string('metric', 50); // api_calls, storage_mb, records, messages, emails_sent
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('quantity')->default(0);
            $table->bigInteger('limit_quantity')->nullable(); // NULL = unlimited
            $table->decimal('overage_rate', 10, 4)->nullable(); // Price per unit over limit
            $table->timestamps();

            $table->unique(['plugin_slug', 'metric', 'period_start']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_usage');
    }
};
