<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WebForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'module_id',
        'is_active',
        'settings',
        'styling',
        'thank_you_config',
        'spam_protection',
        'created_by',
        'assign_to_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'styling' => 'array',
        'thank_you_config' => 'array',
        'spam_protection' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'settings' => '{}',
        'styling' => '{}',
        'thank_you_config' => '{}',
        'spam_protection' => '{}',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WebForm $form) {
            if (empty($form->slug)) {
                $form->slug = static::generateUniqueSlug($form->name);
            }
        });
    }

    /**
     * Generate a unique slug for the form.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Get the module this form creates records in.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this form.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user submissions are assigned to.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_to_user_id');
    }

    /**
     * Get the form fields.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(WebFormField::class)->orderBy('display_order');
    }

    /**
     * Get the form submissions.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(WebFormSubmission::class);
    }

    /**
     * Get the form analytics.
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(WebFormAnalytics::class);
    }

    /**
     * Scope to only active forms.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find a form by slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Get the public URL for this form.
     */
    public function getPublicUrlAttribute(): string
    {
        return url("/forms/{$this->slug}");
    }

    /**
     * Get the embed iframe code.
     */
    public function getIframeEmbedCodeAttribute(): string
    {
        $url = $this->public_url;
        return "<iframe src=\"{$url}\" width=\"100%\" height=\"500\" frameborder=\"0\"></iframe>";
    }

    /**
     * Get the JavaScript embed code.
     */
    public function getJsEmbedCodeAttribute(): string
    {
        $url = $this->public_url;
        return "<div id=\"vrtx-form-{$this->slug}\"></div>\n<script src=\"{$url}/embed.js\"></script>";
    }

    /**
     * Get setting value with default.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Get styling value with default.
     */
    public function getStyling(string $key, mixed $default = null): mixed
    {
        return data_get($this->styling, $key, $default);
    }

    /**
     * Check if spam protection is enabled.
     */
    public function hasSpamProtection(): bool
    {
        return !empty($this->spam_protection['enabled']);
    }

    /**
     * Get reCAPTCHA site key if configured.
     */
    public function getRecaptchaSiteKey(): ?string
    {
        return $this->spam_protection['recaptcha_site_key'] ?? null;
    }
}
