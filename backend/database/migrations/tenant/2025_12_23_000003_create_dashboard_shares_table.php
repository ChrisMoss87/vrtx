<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('permission')->default('view'); // view, edit
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['dashboard_id', 'user_id']);
            $table->unique(['dashboard_id', 'team_id']);
            $table->index(['user_id', 'permission']);
            $table->index(['team_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_shares');
    }
};
