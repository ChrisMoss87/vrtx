<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create the ai.sidebar feature flag as disabled by default
        $exists = DB::table('feature_flags')
            ->where('feature_key', 'ai.sidebar')
            ->exists();

        if (!$exists) {
            DB::table('feature_flags')->insert([
                'feature_key' => 'ai.sidebar',
                'plugin_slug' => null,
                'plan_required' => null,
                'is_enabled' => false,
                'config' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('feature_flags')->where('feature_key', 'ai.sidebar')->delete();
    }
};
