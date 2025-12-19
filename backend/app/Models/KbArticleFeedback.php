<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticleFeedback extends Model
{
    protected $table = 'kb_article_feedback';

    protected $fillable = [
        'article_id',
        'is_helpful',
        'comment',
        'user_id',
        'portal_user_id',
        'ip_address',
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }
}
