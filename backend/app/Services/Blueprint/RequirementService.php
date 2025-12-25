<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates during-phase requirements for blueprint transitions.
 */
class RequirementService
{
    /**
     * Get all requirements for a transition.
     */
    public function getRequirements(BlueprintTransition $transition): Collection
    {
        return $transition->requirements()
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Validate submitted requirements data.
     */
    public function validate(BlueprintTransition $transition, array $data): array
    {
        $requirements = $this->getRequirements($transition);
        $errors = [];
        $valid = true;

        foreach ($requirements as $requirement) {
            if ($requirement->is_required) {
                $validationResult = $this->validateRequirement($requirement, $data);
                if (!$validationResult['valid']) {
                    $valid = false;
                    $errors[] = $validationResult['error'];
                }
            }
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Validate a single requirement.
     */
    protected function validateRequirement(BlueprintTransitionRequirement $requirement, array $data): array
    {
        return match ($requirement->type) {
            BlueprintTransitionRequirement::TYPE_MANDATORY_FIELD => $this->validateMandatoryField($requirement, $data),
            BlueprintTransitionRequirement::TYPE_ATTACHMENT => $this->validateAttachment($requirement, $data),
            BlueprintTransitionRequirement::TYPE_NOTE => $this->validateNote($requirement, $data),
            BlueprintTransitionRequirement::TYPE_CHECKLIST => $this->validateChecklist($requirement, $data),
            default => ['valid' => true],
        };
    }

    /**
     * Validate a mandatory field requirement.
     */
    protected function validateMandatoryField(BlueprintTransitionRequirement $requirement, array $data): array
    {
        $field = $requirement->field;
        if (!$field) {
            return ['valid' => true];
        }

        $fieldName = $field->api_name;
        $value = $data['fields'][$fieldName] ?? $data[$fieldName] ?? null;

        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return [
                'valid' => false,
                'error' => $requirement->label ?? "Field '{$field->label}' is required",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate an attachment requirement.
     */
    protected function validateAttachment(BlueprintTransitionRequirement $requirement, array $data): array
    {
        $attachments = $data['attachments'] ?? [];

        // Check if any attachment is provided
        if (empty($attachments)) {
            return [
                'valid' => false,
                'error' => $requirement->label ?? 'An attachment is required',
            ];
        }

        // Validate file types if specified
        $allowedTypes = $requirement->getAllowedFileTypes();
        if (!empty($allowedTypes)) {
            foreach ($attachments as $attachment) {
                $extension = pathinfo($attachment['name'] ?? '', PATHINFO_EXTENSION);
                if (!in_array(strtolower($extension), array_map('strtolower', $allowedTypes))) {
                    return [
                        'valid' => false,
                        'error' => "Invalid file type. Allowed types: " . implode(', ', $allowedTypes),
                    ];
                }
            }
        }

        // Validate file size if specified
        $maxSize = $requirement->getMaxFileSize();
        if ($maxSize !== null) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['size']) && $attachment['size'] > $maxSize) {
                    return [
                        'valid' => false,
                        'error' => "File size exceeds maximum allowed size of " . $this->formatBytes($maxSize),
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate a note requirement.
     */
    protected function validateNote(BlueprintTransitionRequirement $requirement, array $data): array
    {
        $note = $data['note'] ?? '';

        if (empty(trim($note))) {
            return [
                'valid' => false,
                'error' => $requirement->label ?? 'A note is required',
            ];
        }

        // Check minimum length
        $minLength = $requirement->getMinNoteLength();
        if ($minLength > 0 && strlen(trim($note)) < $minLength) {
            return [
                'valid' => false,
                'error' => "Note must be at least {$minLength} characters",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate a checklist requirement.
     */
    protected function validateChecklist(BlueprintTransitionRequirement $requirement, array $data): array
    {
        $checklistItems = $requirement->getChecklistItems();
        $submittedItems = $data['checklist'] ?? [];

        if (empty($checklistItems)) {
            return ['valid' => true];
        }

        // Check if all required items are checked
        foreach ($checklistItems as $index => $item) {
            $isRequired = $item['required'] ?? true;
            $isChecked = $submittedItems[$index] ?? $submittedItems[$item['id'] ?? $index] ?? false;

            if ($isRequired && !$isChecked) {
                return [
                    'valid' => false,
                    'error' => "Checklist item '{$item['label']}' must be completed",
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Format bytes to human readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get available requirement types.
     */
    public function getRequirementTypes(): array
    {
        return BlueprintTransitionRequirement::getTypes();
    }

    /**
     * Format requirements for API response.
     */
    public function formatRequirements(Collection $requirements): array
    {
        return $requirements->map(function (BlueprintTransitionRequirement $requirement) {
            $formatted = [
                'id' => $requirement->id,
                'type' => $requirement->type,
                'label' => $requirement->label,
                'description' => $requirement->description,
                'is_required' => $requirement->is_required,
                'display_order' => $requirement->display_order,
            ];

            // Add type-specific data
            switch ($requirement->type) {
                case BlueprintTransitionRequirement::TYPE_MANDATORY_FIELD:
                    $field = $requirement->field;
                    if ($field) {
                        $formatted['field'] = [
                            'id' => $field->id,
                            'api_name' => $field->api_name,
                            'label' => $field->label,
                            'type' => $field->type,
                        ];
                    }
                    break;

                case BlueprintTransitionRequirement::TYPE_ATTACHMENT:
                    $formatted['allowed_types'] = $requirement->getAllowedFileTypes();
                    $formatted['max_size'] = $requirement->getMaxFileSize();
                    break;

                case BlueprintTransitionRequirement::TYPE_NOTE:
                    $formatted['min_length'] = $requirement->getMinNoteLength();
                    break;

                case BlueprintTransitionRequirement::TYPE_CHECKLIST:
                    $formatted['items'] = $requirement->getChecklistItems();
                    break;
            }

            return $formatted;
        })->toArray();
    }
}
