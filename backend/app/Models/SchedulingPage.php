<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SchedulingPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'description',
        'is_active',
        'timezone',
        'branding',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'branding' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = static::generateUniqueSlug($page->name);
            }
        });
    }

    /**
     * Generate a unique slug for the scheduling page.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    /**
     * Get the user who owns this scheduling page.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the meeting types for this page.
     */
    public function meetingTypes(): HasMany
    {
        return $this->hasMany(MeetingType::class)->orderBy('display_order');
    }

    /**
     * Get only active meeting types.
     */
    public function activeMeetingTypes(): HasMany
    {
        return $this->meetingTypes()->where('is_active', true);
    }

    /**
     * Get the public URL for this scheduling page.
     */
    public function getPublicUrlAttribute(): string
    {
        return url("/schedule/{$this->slug}");
    }

    /**
     * Scope for active pages.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
