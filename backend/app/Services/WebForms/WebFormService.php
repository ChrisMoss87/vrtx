<?php

declare(strict_types=1);

namespace App\Services\WebForms;

use App\Models\Module;
use App\Models\WebForm;
use App\Models\WebFormAnalytics;
use App\Models\WebFormField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WebFormService
{
    /**
     * Get all web forms with optional filtering.
     */
    public function listForms(array $filters = []): Collection
    {
        $query = WebForm::with(['module', 'creator', 'fields']);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', '%' . $filters['search'] . '%')
                    ->orWhere('slug', 'ilike', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a form by ID with all relations.
     */
    public function getForm(int $id): ?WebForm
    {
        return WebForm::with(['module.fields', 'creator', 'fields.moduleField', 'assignee'])
            ->find($id);
    }

    /**
     * Get a form by slug (for public access).
     */
    public function getFormBySlug(string $slug): ?WebForm
    {
        return WebForm::with(['module', 'fields.moduleField'])
            ->where('slug', $slug)
            ->active()
            ->first();
    }

    /**
     * Create a new web form.
     */
    public function createForm(array $data): WebForm
    {
        return DB::transaction(function () use ($data) {
            $form = WebForm::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'] ?? null,
                'module_id' => $data['module_id'],
                'is_active' => $data['is_active'] ?? true,
                'settings' => $data['settings'] ?? [],
                'styling' => $data['styling'] ?? $this->getDefaultStyling(),
                'thank_you_config' => $data['thank_you_config'] ?? $this->getDefaultThankYouConfig(),
                'spam_protection' => $data['spam_protection'] ?? [],
                'created_by' => auth()->id(),
                'assign_to_user_id' => $data['assign_to_user_id'] ?? null,
            ]);

            // Create fields if provided
            if (!empty($data['fields'])) {
                $this->syncFields($form, $data['fields']);
            }

            return $form->load(['module', 'fields', 'creator']);
        });
    }

    /**
     * Update an existing web form.
     */
    public function updateForm(WebForm $form, array $data): WebForm
    {
        return DB::transaction(function () use ($form, $data) {
            $form->update([
                'name' => $data['name'] ?? $form->name,
                'slug' => $data['slug'] ?? $form->slug,
                'description' => $data['description'] ?? $form->description,
                'module_id' => $data['module_id'] ?? $form->module_id,
                'is_active' => $data['is_active'] ?? $form->is_active,
                'settings' => $data['settings'] ?? $form->settings,
                'styling' => $data['styling'] ?? $form->styling,
                'thank_you_config' => $data['thank_you_config'] ?? $form->thank_you_config,
                'spam_protection' => $data['spam_protection'] ?? $form->spam_protection,
                'assign_to_user_id' => $data['assign_to_user_id'] ?? $form->assign_to_user_id,
            ]);

            // Sync fields if provided
            if (isset($data['fields'])) {
                $this->syncFields($form, $data['fields']);
            }

            return $form->fresh(['module', 'fields', 'creator']);
        });
    }

    /**
     * Delete a web form.
     */
    public function deleteForm(WebForm $form): bool
    {
        return $form->delete();
    }

    /**
     * Sync form fields.
     */
    public function syncFields(WebForm $form, array $fields): void
    {
        // Delete existing fields
        $form->fields()->delete();

        // Create new fields
        foreach ($fields as $index => $fieldData) {
            $form->fields()->create([
                'field_type' => $fieldData['field_type'],
                'label' => $fieldData['label'],
                'name' => $fieldData['name'] ?? null,
                'placeholder' => $fieldData['placeholder'] ?? null,
                'is_required' => $fieldData['is_required'] ?? false,
                'module_field_id' => $fieldData['module_field_id'] ?? null,
                'options' => $fieldData['options'] ?? null,
                'validation_rules' => $fieldData['validation_rules'] ?? null,
                'display_order' => $fieldData['display_order'] ?? $index,
                'settings' => $fieldData['settings'] ?? [],
            ]);
        }
    }

    /**
     * Duplicate a form.
     */
    public function duplicateForm(WebForm $form, ?string $newName = null): WebForm
    {
        return DB::transaction(function () use ($form, $newName) {
            $newForm = $form->replicate();
            $newForm->name = $newName ?? $form->name . ' (Copy)';
            $newForm->slug = WebForm::generateUniqueSlug($newForm->name);
            $newForm->created_by = auth()->id();
            $newForm->save();

            // Duplicate fields
            foreach ($form->fields as $field) {
                $newField = $field->replicate();
                $newField->web_form_id = $newForm->id;
                $newField->save();
            }

            return $newForm->load(['module', 'fields', 'creator']);
        });
    }

    /**
     * Toggle form active status.
     */
    public function toggleActive(WebForm $form): WebForm
    {
        $form->update(['is_active' => !$form->is_active]);
        return $form;
    }

    /**
     * Get form analytics.
     */
    public function getAnalytics(WebForm $form, string $startDate, string $endDate): array
    {
        return WebFormAnalytics::getSummary($form->id, $startDate, $endDate);
    }

    /**
     * Get available modules for forms.
     */
    public function getAvailableModules(): Collection
    {
        return Module::active()
            ->with(['fields' => fn($q) => $q->orderBy('display_order')])
            ->ordered()
            ->get();
    }

    /**
     * Get available field types.
     */
    public function getFieldTypes(): array
    {
        return WebFormField::FIELD_TYPES;
    }

    /**
     * Get default styling for new forms.
     */
    protected function getDefaultStyling(): array
    {
        return [
            'background_color' => '#ffffff',
            'text_color' => '#1f2937',
            'label_color' => '#374151',
            'primary_color' => '#2563eb',
            'border_color' => '#d1d5db',
            'border_radius' => '8px',
            'font_family' => 'Inter, system-ui, sans-serif',
            'font_size' => '14px',
            'padding' => '24px',
            'max_width' => '600px',
            'custom_css' => '',
        ];
    }

    /**
     * Get default thank you config.
     */
    protected function getDefaultThankYouConfig(): array
    {
        return [
            'type' => 'message', // message or redirect
            'message' => 'Thank you for your submission!',
            'redirect_url' => null,
        ];
    }

    /**
     * Generate embed code for a form.
     */
    public function getEmbedCode(WebForm $form, string $type = 'iframe'): string
    {
        if ($type === 'iframe') {
            return $form->iframe_embed_code;
        }

        return $form->js_embed_code;
    }

    /**
     * Get form submissions with pagination.
     */
    public function getSubmissions(WebForm $form, array $filters = [], int $perPage = 20)
    {
        $query = $form->submissions()->with('record');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('submitted_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('submitted_at', '<=', $filters['end_date']);
        }

        return $query->orderByDesc('submitted_at')->paginate($perPage);
    }
}
