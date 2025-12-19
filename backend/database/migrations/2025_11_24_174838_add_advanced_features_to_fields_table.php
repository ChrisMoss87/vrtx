<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

return new class extends Migration
{
    use BelongsToTenant;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            // Only add columns if they don't exist
            if (!Schema::hasColumn('fields', 'conditional_visibility')) {
                $table->jsonb('conditional_visibility')->nullable()->after('settings');
            }

            if (!Schema::hasColumn('fields', 'field_dependency')) {
                $table->jsonb('field_dependency')->nullable()->after('conditional_visibility');
            }

            if (!Schema::hasColumn('fields', 'formula_definition')) {
                $table->jsonb('formula_definition')->nullable()->after('field_dependency');
            }

            if (!Schema::hasColumn('fields', 'lookup_settings')) {
                $table->jsonb('lookup_settings')->nullable()->after('formula_definition');
            }

            if (!Schema::hasColumn('fields', 'placeholder')) {
                $table->string('placeholder')->nullable()->after('help_text');
            }
        });

        // Add indexes
        Schema::table('fields', function (Blueprint $table) {
            if (!Schema::hasIndex('fields', 'fields_type_index')) {
                $table->index('type');
            }
        });

        // GIN index for JSON search (PostgreSQL)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS fields_conditional_visibility_gin ON fields USING GIN (conditional_visibility)');
            DB::statement('CREATE INDEX IF NOT EXISTS fields_lookup_settings_gin ON fields USING GIN (lookup_settings)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop GIN indexes first (PostgreSQL)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS fields_conditional_visibility_gin');
            DB::statement('DROP INDEX IF EXISTS fields_lookup_settings_gin');
        }

        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn([
                'conditional_visibility',
                'field_dependency',
                'formula_definition',
                'lookup_settings',
                'placeholder'
            ]);

            $table->dropIndex(['type']);
        });
    }
};
