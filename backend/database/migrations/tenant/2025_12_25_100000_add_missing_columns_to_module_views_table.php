<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_views', function (Blueprint $table) {
            if (!Schema::hasColumn('module_views', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('is_shared');
            }
            if (!Schema::hasColumn('module_views', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
        });

        // Migrate existing user_id to created_by if not already set
        DB::statement('UPDATE module_views SET created_by = user_id WHERE created_by IS NULL AND user_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('module_views', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['is_system', 'created_by']);
        });
    }
};
