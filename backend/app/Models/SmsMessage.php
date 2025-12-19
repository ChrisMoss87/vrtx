<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'template_id',
        'direction',
        'from_number',
        'to_number',
        'content',
        'status',
        'provider_message_id',
        'error_code',
        'error_message',
        'segment_count',
        'cost',
        'module_record_id',
        'module_api_name',
        'campaign_id',
        'sent_by',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SmsConnection::class, 'connection_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'template_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SmsCampaign::class, 'campaign_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function moduleRecord(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'module_record_id');
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForPhone($query, string $phone)
    {
        return $query->where(function ($q) use ($phone) {
            $q->where('to_number', $phone)
              ->orWhere('from_number', $phone);
        });
    }

    public function scopeForRecord($query, string $moduleApiName, int $recordId)
    {
        return $query->where('module_api_name', $moduleApiName)
                     ->where('module_record_id', $recordId);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'undelivered']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'queued', 'sent']);
    }

    public function markAsSent(string $providerId): void
    {
        $this->update([
            'status' => 'sent',
            'provider_message_id' => $providerId,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorCode, string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }
}
