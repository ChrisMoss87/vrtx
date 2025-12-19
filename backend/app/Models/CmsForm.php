<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsForm extends Model
{
    use SoftDeletes;

    public const ACTION_CREATE_LEAD = 'create_lead';
    public const ACTION_CREATE_CONTACT = 'create_contact';
    public const ACTION_UPDATE_CONTACT = 'update_contact';
    public const ACTION_WEBHOOK = 'webhook';
    public const ACTION_EMAIL = 'email';
    public const ACTION_CUSTOM = 'custom';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'fields',
        'settings',
        'submit_action',
        'target_module_id',
        'field_mapping',
        'submit_button_text',
        'success_message',
        'redirect_url',
        'notification_emails',
        'notification_template_id',
        'submission_count',
        'view_count',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'fields' => 'array',
        'settings' => 'array',
        'field_mapping' => 'array',
        'notification_emails' => 'array',
        'submission_count' => 'integer',
        'view_count' => 'integer',
        'is_active' => 'boolean',
        'target_module_id' => 'integer',
        'notification_template_id' => 'integer',
        'created_by' => 'integer',
    ];

    protected $attributes = [
        'submit_action' => self::ACTION_CREATE_LEAD,
        'submit_button_text' => 'Submit',
        'submission_count' => 0,
        'view_count' => 0,
        'is_active' => true,
    ];

    public function targetModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'target_module_id');
    }

    public function notificationTemplate(): BelongsTo
    {
        return $this->belongsTo(CmsTemplate::class, 'notification_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CmsFormSubmission::class, 'form_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function incrementSubmissionCount(): void
    {
        $this->increment('submission_count');
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function getConversionRate(): float
    {
        if ($this->view_count === 0) {
            return 0.0;
        }
        return round(($this->submission_count / $this->view_count) * 100, 2);
    }

    public function duplicate(?int $userId = null): self
    {
        $copy = $this->replicate(['submission_count', 'view_count']);
        $copy->name = $this->name . ' (Copy)';
        $copy->slug = $this->slug . '-copy-' . time();
        $copy->created_by = $userId ?? $this->created_by;
        $copy->save();

        return $copy;
    }

    public function getEmbedCode(): string
    {
        $url = config('app.url') . "/api/v1/cms/forms/{$this->slug}/embed";
        return "<iframe src=\"{$url}\" frameborder=\"0\" style=\"width: 100%; min-height: 400px;\"></iframe>";
    }

    public function requiresModule(): bool
    {
        return in_array($this->submit_action, [
            self::ACTION_CREATE_LEAD,
            self::ACTION_CREATE_CONTACT,
            self::ACTION_UPDATE_CONTACT,
        ]);
    }
}
