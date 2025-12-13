<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DealRoom extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'deal_record_id',
        'name',
        'slug',
        'description',
        'status',
        'branding',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'branding' => 'array',
        'settings' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($room) {
            if (empty($room->slug)) {
                $room->slug = self::generateUniqueSlug($room->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug . '-' . Str::random(6);

        while (self::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . Str::random(6);
        }

        return $slug;
    }

    public function dealRecord(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'deal_record_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(DealRoomMember::class, 'room_id');
    }

    public function internalMembers(): HasMany
    {
        return $this->members()->whereNotNull('user_id');
    }

    public function externalMembers(): HasMany
    {
        return $this->members()->whereNull('user_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(DealRoomActionItem::class, 'room_id')->orderBy('display_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DealRoomDocument::class, 'room_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DealRoomMessage::class, 'room_id')->orderBy('created_at', 'desc');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DealRoomActivity::class, 'room_id')->orderBy('created_at', 'desc');
    }

    public function getActionPlanProgress(): array
    {
        $items = $this->actionItems;
        $total = $items->count();
        $completed = $items->where('status', 'completed')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    public function logActivity(string $type, ?int $memberId = null, array $data = []): DealRoomActivity
    {
        return $this->activities()->create([
            'member_id' => $memberId,
            'activity_type' => $type,
            'activity_data' => $data,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }
}
