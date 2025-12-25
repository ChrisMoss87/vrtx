<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create the ai.sidebar feature flag as disabled by default
        FeatureFlag::firstOrCreate(
            ['feature_key' => 'ai.sidebar'],
            [
                'plugin_slug' => null,
                'plan_required' => null,
                'is_enabled' => false,
                'config' => null,
            ]
        );
    }

    public function down(): void
    {
        DB::table('feature_flags')->where('feature_key', 'ai.sidebar')->delete();
    }
};
