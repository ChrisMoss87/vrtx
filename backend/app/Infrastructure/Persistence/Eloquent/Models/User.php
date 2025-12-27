<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Eloquent model for Laravel authentication.
 *
 * This model is required by Laravel's auth system and Sanctum for API tokens.
 * For domain logic, use App\Domain\User\Entities\User and UserRepositoryInterface.
 *
 * Note: Spatie HasRoles trait has been removed. Use AuthorizationApplicationService
 * or CachedAuthorizationService for permission checks. Roles are accessed via
 * the custom user_roles pivot table with efficient JOIN queries.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

    /**
     * Get a specific preference value.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Set a specific preference value.
     */
    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
        $this->save();
    }

    /**
     * Get the roles associated with the user.
     *
     * Uses the custom user_roles pivot table instead of Spatie's polymorphic model_has_roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id',
        )->withPivot(['assigned_at', 'assigned_by']);
    }
}
