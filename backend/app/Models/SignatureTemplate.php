<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'signers',
        'fields',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'signers' => 'array',
        'fields' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function applyToRequest(SignatureRequest $request): void
    {
        // Apply default signers
        if (!empty($this->signers)) {
            foreach ($this->signers as $index => $signerData) {
                $request->signers()->create([
                    'email' => $signerData['email'] ?? '',
                    'name' => $signerData['name'] ?? '',
                    'role' => $signerData['role'] ?? SignatureSigner::ROLE_SIGNER,
                    'sign_order' => $index + 1,
                ]);
            }
        }

        // Apply default fields
        if (!empty($this->fields)) {
            foreach ($this->fields as $fieldData) {
                $request->fields()->create([
                    'signer_id' => null, // Will be linked when signers are assigned
                    'field_type' => $fieldData['field_type'] ?? SignatureField::TYPE_SIGNATURE,
                    'page_number' => $fieldData['page_number'] ?? 1,
                    'x_position' => $fieldData['x_position'] ?? 100,
                    'y_position' => $fieldData['y_position'] ?? 100,
                    'width' => $fieldData['width'] ?? 200,
                    'height' => $fieldData['height'] ?? 50,
                    'required' => $fieldData['required'] ?? true,
                    'label' => $fieldData['label'] ?? null,
                ]);
            }
        }
    }
}
