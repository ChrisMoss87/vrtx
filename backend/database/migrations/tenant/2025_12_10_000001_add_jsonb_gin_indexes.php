<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add GIN indexes on JSONB columns for efficient JSON querying.
 * Only applies to columns that are actually JSONB type.
 */
return new class extends Migration
{
    /**
     * Check if a column is JSONB type (PostgreSQL specific)
     */
    private function isJsonbColumn(string $table, string $column): bool
    {
        $result = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_name = ? AND column_name = ?
        ", [$table, $column]);

        return $result && $result->data_type === 'jsonb';
    }

    /**
     * Safely create a GIN index on a JSONB column
     */
    private function createGinIndex(string $table, string $column, string $indexName): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column) && $this->isJsonbColumn($table, $column)) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} USING GIN ({$column} jsonb_path_ops)");
        }
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only apply if using PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // GIN index on module_records.data for efficient JSON path queries
        $this->createGinIndex('module_records', 'data', 'idx_records_data_gin');

        // GIN index on module_fields.settings for field option queries
        $this->createGinIndex('module_fields', 'settings', 'idx_fields_settings_gin');

        // GIN index on workflows.trigger_config for condition matching
        $this->createGinIndex('workflows', 'trigger_config', 'idx_workflows_trigger_gin');

        // GIN index on workflow_steps.config for action configs
        $this->createGinIndex('workflow_steps', 'config', 'idx_wf_steps_config_gin');

        // GIN index on modules.settings
        $this->createGinIndex('modules', 'settings', 'idx_modules_settings_gin');

        // GIN index on dashboards.settings
        $this->createGinIndex('dashboards', 'settings', 'idx_dashboards_settings_gin');

        // GIN index on dashboard_widgets.config
        $this->createGinIndex('dashboard_widgets', 'config', 'idx_widgets_config_gin');

        // GIN index on reports.config
        $this->createGinIndex('reports', 'config', 'idx_reports_config_gin');

        // GIN index on module_views.config
        $this->createGinIndex('module_views', 'config', 'idx_views_config_gin');

        // GIN index on email_messages.metadata
        $this->createGinIndex('email_messages', 'metadata', 'idx_emails_metadata_gin');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only apply if using PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_records_data_gin');
        DB::statement('DROP INDEX IF EXISTS idx_fields_settings_gin');
        DB::statement('DROP INDEX IF EXISTS idx_workflows_trigger_gin');
        DB::statement('DROP INDEX IF EXISTS idx_wf_steps_config_gin');
        DB::statement('DROP INDEX IF EXISTS idx_modules_settings_gin');
        DB::statement('DROP INDEX IF EXISTS idx_dashboards_settings_gin');
        DB::statement('DROP INDEX IF EXISTS idx_widgets_config_gin');
        DB::statement('DROP INDEX IF EXISTS idx_reports_config_gin');
        DB::statement('DROP INDEX IF EXISTS idx_views_config_gin');
        DB::statement('DROP INDEX IF EXISTS idx_emails_metadata_gin');
    }
};
