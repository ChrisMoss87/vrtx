<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Updates dashboard_widgets to support full grid positioning (x, y, w, h).
     * The existing `size` column already has w/h, we add x/y for grid coordinates.
     */
    public function up(): void
    {
        // Rename size to grid_position for clarity
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->renameColumn('size', 'grid_position');
        });

        // Update existing records to include x, y coordinates based on position
        // Widgets are laid out in a 12-column grid, 3 widgets per row (each w=4)
        DB::statement("
            UPDATE dashboard_widgets
            SET grid_position = jsonb_set(
                jsonb_set(
                    grid_position,
                    '{x}',
                    to_jsonb((position % 3) * 4)
                ),
                '{y}',
                to_jsonb((position / 3) * 4)
            )
        ");

        // Drop the position column as it's now redundant
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->dropIndex(['dashboard_id', 'position']);
            $table->dropColumn('position');
        });

        // Add new index for dashboard ordering by y, x
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->index('dashboard_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add position column back
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('config');
        });

        // Calculate position from grid_position (y * 3 + x / 4)
        DB::statement("
            UPDATE dashboard_widgets
            SET position = COALESCE(
                ((grid_position->>'y')::int / 4) * 3 + ((grid_position->>'x')::int / 4),
                0
            )
        ");

        // Rename grid_position back to size
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->renameColumn('grid_position', 'size');
        });

        // Recreate original index
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->dropIndex(['dashboard_id']);
            $table->index(['dashboard_id', 'position']);
        });
    }
};
