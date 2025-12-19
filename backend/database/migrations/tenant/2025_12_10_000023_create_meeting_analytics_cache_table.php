<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50); // deal, account, user
            $table->unsignedBigInteger('entity_id');
            $table->string('period', 20); // week, month, quarter
            $table->date('period_start');
            $table->integer('total_meetings')->default(0);
            $table->integer('total_duration_minutes')->default(0);
            $table->integer('unique_stakeholders')->default(0);
            $table->decimal('meetings_per_week', 5, 2)->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'period', 'period_start'], 'meeting_analytics_unique');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_analytics_cache');
    }
};
