<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationEntityMapping extends Model
{
    protected $fillable = [
        'connection_id',
        'crm_entity',
        'crm_record_id',
        'external_entity',
        'external_id',
        'metadata',
        'last_synced_at',
        'sync_hash',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function scopeForCrmRecord($query, string $entity, int $recordId)
    {
        return $query->where('crm_entity', $entity)
            ->where('crm_record_id', $recordId);
    }

    public function scopeForExternalRecord($query, string $entity, string $externalId)
    {
        return $query->where('external_entity', $entity)
            ->where('external_id', $externalId);
    }

    public function updateSyncHash(array $data): void
    {
        $hash = md5(json_encode($data));
        $this->update([
            'sync_hash' => $hash,
            'last_synced_at' => now(),
        ]);
    }

    public function hasChanged(array $data): bool
    {
        $hash = md5(json_encode($data));
        return $this->sync_hash !== $hash;
    }
}
