<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatWidget extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'widget_key',
        'is_active',
        'settings',
        'styling',
        'routing_rules',
        'business_hours',
        'allowed_domains',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'styling' => 'array',
        'routing_rules' => 'array',
        'business_hours' => 'array',
        'allowed_domains' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatWidget $widget) {
            if (empty($widget->widget_key)) {
                $widget->widget_key = Str::random(32);
            }
        });
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(ChatVisitor::class, 'widget_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'widget_id');
    }

    public function getDefaultSettings(): array
    {
        return [
            'position' => 'bottom-right',
            'greeting_message' => 'Hi! How can we help you today?',
            'offline_message' => "We're currently offline. Leave a message and we'll get back to you.",
            'require_email' => true,
            'require_name' => true,
            'show_avatar' => true,
            'sound_enabled' => true,
            'auto_open_delay' => 0, // 0 = don't auto open
        ];
    }

    public function getDefaultStyling(): array
    {
        return [
            'primary_color' => '#3B82F6',
            'text_color' => '#FFFFFF',
            'background_color' => '#FFFFFF',
            'launcher_icon' => 'chat',
            'header_text' => 'Chat with us',
            'border_radius' => 12,
        ];
    }

    public function isOnline(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check business hours
        if (!empty($this->business_hours)) {
            $now = now();
            $dayOfWeek = strtolower($now->format('l'));
            $currentTime = $now->format('H:i');

            $hours = $this->business_hours[$dayOfWeek] ?? null;
            if (!$hours || empty($hours['enabled'])) {
                return false;
            }

            if ($currentTime < $hours['start'] || $currentTime > $hours['end']) {
                return false;
            }
        }

        // Check if any agent is online
        return ChatAgentStatus::where('status', 'online')
            ->where('active_conversations', '<', \DB::raw('max_conversations'))
            ->exists();
    }

    public function isDomainAllowed(?string $domain): bool
    {
        if (empty($this->allowed_domains)) {
            return true;
        }

        if (!$domain) {
            return false;
        }

        foreach ($this->allowed_domains as $allowed) {
            if ($allowed === '*') {
                return true;
            }
            if (str_ends_with($domain, $allowed)) {
                return true;
            }
        }

        return false;
    }

    public function getEmbedCode(): string
    {
        $key = $this->widget_key;
        return <<<HTML
<script>
(function(w,d,s,o,f,js,fjs){
w['VRTXChat']=o;w[o]=w[o]||function(){(w[o].q=w[o].q||[]).push(arguments)};
js=d.createElement(s),fjs=d.getElementsByTagName(s)[0];
js.id=o;js.src=f;js.async=1;fjs.parentNode.insertBefore(js,fjs);
}(window,document,'script','vrtxChat','/chat-widget.js'));
vrtxChat('init', '{$key}');
</script>
HTML;
    }
}
