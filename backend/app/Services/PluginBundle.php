<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PluginBundle
{
    public static function active(): self
    {
        return new self();
    }

    public function orderBy(string $column): self
    {
        return $this;
    }

    public function get(): Collection
    {
        return DB::table('plugin_bundles')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }
}
