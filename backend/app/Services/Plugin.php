<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Plugin
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
        return DB::table('plugins')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    public static function pluck(string $column): Collection
    {
        return DB::table('plugins')->pluck($column);
    }
}
