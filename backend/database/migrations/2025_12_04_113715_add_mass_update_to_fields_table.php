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
        Schema::table('fields', function (Blueprint $table) {
            // Add is_mass_updatable flag - defaults to true for most fields
            // Formula fields and certain system fields should default to false
            $table->boolean('is_mass_updatable')->default(true)->after('is_sortable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('is_mass_updatable');
        });
    }
};
