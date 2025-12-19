<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalActivityLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'portal_user_id',
        'action',
        'resource_type',
        'resource_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public static function getActionLabels(): array
    {
        return [
            'login' => 'Logged in',
            'logout' => 'Logged out',
            'view_deal' => 'Viewed deal',
            'view_invoice' => 'Viewed invoice',
            'view_quote' => 'Viewed quote',
            'download_document' => 'Downloaded document',
            'sign_document' => 'Signed document',
            'submit_ticket' => 'Submitted ticket',
            'reply_ticket' => 'Replied to ticket',
            'update_profile' => 'Updated profile',
            'change_password' => 'Changed password',
        ];
    }
}
