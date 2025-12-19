<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->decimal('degree_centrality', 10, 6)->nullable();
            $table->decimal('betweenness_centrality', 10, 6)->nullable();
            $table->decimal('closeness_centrality', 10, 6)->nullable();
            $table->integer('cluster_id')->nullable();
            $table->decimal('total_connected_revenue', 15, 2)->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            // Unique constraint
            $table->unique(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_metrics');
    }
};
