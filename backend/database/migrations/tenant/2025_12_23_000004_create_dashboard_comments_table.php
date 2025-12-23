<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('widget_id')->nullable()->constrained('dashboard_widgets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('dashboard_comments')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->index(['dashboard_id', 'widget_id']);
            $table->index(['dashboard_id', 'created_at']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_comments');
    }
};
