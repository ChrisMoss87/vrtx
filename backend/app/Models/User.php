<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The relationships that should always be loaded.
     *
     * @var array<string>
     */
    protected $with = ['roles'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    /**
     * Get the scheduling pages for this user.
     */
    public function schedulingPages(): HasMany
    {
        return $this->hasMany(SchedulingPage::class);
    }

    /**
     * Get the availability rules for this user.
     */
    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    /**
     * Get the scheduling overrides for this user.
     */
    public function schedulingOverrides(): HasMany
    {
        return $this->hasMany(SchedulingOverride::class);
    }

    /**
     * Get the calendar connections for this user.
     */
    public function calendarConnections(): HasMany
    {
        return $this->hasMany(CalendarConnection::class);
    }

    /**
     * Get the scheduled meetings where this user is the host.
     */
    public function scheduledMeetings(): HasMany
    {
        return $this->hasMany(ScheduledMeeting::class, 'host_user_id');
    }
}
