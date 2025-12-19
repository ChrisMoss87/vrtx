<?php

use App\Models\FeatureFlag;
use Illuminate\Database\Migrations\Migration;

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
        FeatureFlag::where('feature_key', 'ai.sidebar')->delete();
    }
};
