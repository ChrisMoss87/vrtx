<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PluginUsage
{
    public int $quantity = 0;
    public ?int $limit = null;

    public static function getOrCreateForPeriod(string $pluginSlug, string $metric, ?int $limit): self
    {
        $instance = new self();
        $instance->limit = $limit;

        $usage = DB::table('plugin_usages')
            ->where('plugin_slug', $pluginSlug)
            ->where('metric', $metric)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        if ($usage) {
            $instance->quantity = $usage->quantity;
        }

        return $instance;
    }

    public function incrementUsage(int $amount = 1): void
    {
        $this->quantity += $amount;
    }
}
