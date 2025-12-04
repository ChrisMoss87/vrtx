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
        Schema::table('modules', function (Blueprint $table) {
            $table->jsonb('default_filters')->nullable()->after('settings');
            $table->jsonb('default_sorting')->nullable()->after('default_filters');
            $table->jsonb('default_column_visibility')->nullable()->after('default_sorting');
            $table->integer('default_page_size')->default(50)->after('default_column_visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['default_filters', 'default_sorting', 'default_column_visibility', 'default_page_size']);
        });
    }
};
