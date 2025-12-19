<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    public const FREQUENCY_IMMEDIATE = 'immediate';
    public const FREQUENCY_HOURLY = 'hourly';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCIES = [
        self::FREQUENCY_IMMEDIATE,
        self::FREQUENCY_HOURLY,
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
    ];

    protected $fillable = [
        'user_id',
        'category',
        'in_app',
        'email',
        'push',
        'email_frequency',
    ];

    protected $casts = [
        'in_app' => 'boolean',
        'email' => 'boolean',
        'push' => 'boolean',
    ];

    protected $attributes = [
        'in_app' => true,
        'email' => true,
        'push' => false,
        'email_frequency' => self::FREQUENCY_IMMEDIATE,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a channel is enabled for this preference
     */
    public function isChannelEnabled(string $channel): bool
    {
        return match ($channel) {
            'in_app' => $this->in_app,
            'email' => $this->email,
            'push' => $this->push,
            default => false,
        };
    }

    /**
     * Get default preferences for all categories
     */
    public static function getDefaults(): array
    {
        return [
            Notification::CATEGORY_APPROVALS => [
                'in_app' => true,
                'email' => true,
                'push' => true,
                'email_frequency' => self::FREQUENCY_IMMEDIATE,
            ],
            Notification::CATEGORY_ASSIGNMENTS => [
                'in_app' => true,
                'email' => true,
                'push' => false,
                'email_frequency' => self::FREQUENCY_IMMEDIATE,
            ],
            Notification::CATEGORY_MENTIONS => [
                'in_app' => true,
                'email' => true,
                'push' => true,
                'email_frequency' => self::FREQUENCY_IMMEDIATE,
            ],
            Notification::CATEGORY_UPDATES => [
                'in_app' => true,
                'email' => false,
                'push' => false,
                'email_frequency' => self::FREQUENCY_DAILY,
            ],
            Notification::CATEGORY_REMINDERS => [
                'in_app' => true,
                'email' => true,
                'push' => true,
                'email_frequency' => self::FREQUENCY_IMMEDIATE,
            ],
            Notification::CATEGORY_DEALS => [
                'in_app' => true,
                'email' => true,
                'push' => false,
                'email_frequency' => self::FREQUENCY_IMMEDIATE,
            ],
            Notification::CATEGORY_TASKS => [
                'in_app' => true,
                'email' => true,
                'push' => false,
                'email_frequency' => self::FREQUENCY_IMMEDIATE,
            ],
            Notification::CATEGORY_SYSTEM => [
                'in_app' => true,
                'email' => true,
                'push' => false,
                'email_frequency' => self::FREQUENCY_IMMEDIATE,
            ],
        ];
    }

    /**
     * Get category display info
     */
    public static function getCategoryInfo(): array
    {
        return [
            Notification::CATEGORY_APPROVALS => [
                'label' => 'Approvals',
                'description' => 'Approval requests, completions, and escalations',
                'icon' => 'check-circle',
            ],
            Notification::CATEGORY_ASSIGNMENTS => [
                'label' => 'Assignments',
                'description' => 'When records are assigned to you or reassigned',
                'icon' => 'user-plus',
            ],
            Notification::CATEGORY_MENTIONS => [
                'label' => 'Mentions',
                'description' => 'When someone @mentions you in comments or notes',
                'icon' => 'at-sign',
            ],
            Notification::CATEGORY_UPDATES => [
                'label' => 'Record Updates',
                'description' => 'Changes to records you own or follow',
                'icon' => 'edit',
            ],
            Notification::CATEGORY_REMINDERS => [
                'label' => 'Reminders',
                'description' => 'Task reminders and follow-up alerts',
                'icon' => 'bell',
            ],
            Notification::CATEGORY_DEALS => [
                'label' => 'Deals',
                'description' => 'Deal wins, losses, and stage changes',
                'icon' => 'dollar-sign',
            ],
            Notification::CATEGORY_TASKS => [
                'label' => 'Tasks',
                'description' => 'Task assignments, completions, and overdue alerts',
                'icon' => 'clipboard',
            ],
            Notification::CATEGORY_SYSTEM => [
                'label' => 'System',
                'description' => 'System announcements and maintenance notices',
                'icon' => 'settings',
            ],
        ];
    }
}
