<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CampaignAudience extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'campaign_id',
        'name',
        'description',
        'module_id',
        'segment_rules',
        'contact_count',
        'is_dynamic',
        'last_refreshed_at',
    ];

    protected $casts = [
        'segment_rules' => 'array',
        'is_dynamic' => 'boolean',
        'last_refreshed_at' => 'datetime',
    ];

    protected $attributes = [
        'segment_rules' => '[]',
        'contact_count' => 0,
        'is_dynamic' => true,
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(CampaignAudienceMember::class);
    }

    /**
     * Refresh the audience count based on segment rules
     */
    public function refreshCount(): int
    {
        if (!$this->is_dynamic) {
            $this->contact_count = $this->members()->count();
        } else {
            // Count records matching segment rules
            $query = ModuleRecord::where('module_id', $this->module_id);
            $this->applySegmentRules($query);
            $this->contact_count = $query->count();
        }

        $this->last_refreshed_at = now();
        $this->save();

        return $this->contact_count;
    }

    /**
     * Get records matching this audience's segment rules
     */
    public function getMatchingRecords()
    {
        if (!$this->is_dynamic) {
            return ModuleRecord::whereIn('id', $this->members()->pluck('record_id'));
        }

        $query = ModuleRecord::where('module_id', $this->module_id);
        $this->applySegmentRules($query);
        return $query;
    }

    /**
     * Apply segment rules to a query
     */
    protected function applySegmentRules($query): void
    {
        foreach ($this->segment_rules as $rule) {
            $field = $rule['field'] ?? null;
            $operator = $rule['operator'] ?? 'equals';
            $value = $rule['value'] ?? null;

            if (!$field) continue;

            switch ($operator) {
                case 'equals':
                    $query->whereRaw("data->>? = ?", [$field, $value]);
                    break;
                case 'not_equals':
                    $query->whereRaw("data->>? != ?", [$field, $value]);
                    break;
                case 'contains':
                    $query->whereRaw("data->>? ILIKE ?", [$field, "%{$value}%"]);
                    break;
                case 'starts_with':
                    $query->whereRaw("data->>? ILIKE ?", [$field, "{$value}%"]);
                    break;
                case 'ends_with':
                    $query->whereRaw("data->>? ILIKE ?", [$field, "%{$value}"]);
                    break;
                case 'is_empty':
                    $query->where(function ($q) use ($field) {
                        $q->whereRaw("data->>? IS NULL", [$field])
                          ->orWhereRaw("data->>? = ''", [$field]);
                    });
                    break;
                case 'is_not_empty':
                    $query->whereRaw("data->>? IS NOT NULL", [$field])
                          ->whereRaw("data->>? != ''", [$field]);
                    break;
                case 'in':
                    if (is_array($value)) {
                        $query->whereRaw("data->>? = ANY(?)", [$field, '{' . implode(',', $value) . '}']);
                    }
                    break;
                case 'greater_than':
                    $query->whereRaw("(data->>?)::numeric > ?", [$field, $value]);
                    break;
                case 'less_than':
                    $query->whereRaw("(data->>?)::numeric < ?", [$field, $value]);
                    break;
            }
        }
    }
}
