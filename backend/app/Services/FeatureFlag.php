<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class FeatureFlag
{
    public static function pluck(string $column): Collection
    {
        return DB::table('feature_flags')->pluck($column);
    }
}
