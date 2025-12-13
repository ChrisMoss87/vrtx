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
        Schema::table('module_views', function (Blueprint $table) {
            // View type: table, kanban, calendar (future), etc.
            $table->string('view_type')->default('table')->after('description');

            // Kanban-specific configuration
            // {
            //   "group_by_field": "status",      // Field API name to group by
            //   "value_field": "amount",          // Field to sum for totals (optional)
            //   "title_field": "name",            // Field for card title
            //   "subtitle_field": "owner_name",   // Field for card subtitle (optional)
            //   "card_fields": ["email", "phone"], // Additional fields to show on cards
            //   "collapsed_columns": [],          // Columns that are collapsed by default
            //   "column_settings": {}             // Per-column settings like color overrides
            // }
            $table->jsonb('kanban_config')->nullable()->after('view_type');

            // Index for filtering by view type
            $table->index(['module_id', 'view_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_views', function (Blueprint $table) {
            $table->dropIndex(['module_id', 'view_type']);
            $table->dropColumn('kanban_config');
            $table->dropColumn('view_type');
        });
    }
};
