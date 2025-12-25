<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\WebForm\WebFormApplicationService;
use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\WebForms\WebFormService;
use App\Services\WebForms\WebFormSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WebFormPublicController extends Controller
{
    public function __construct(
        protected WebFormRepositoryInterface $webFormRepository,
        protected WebFormService $webFormService,
        protected WebFormSubmissionService $submissionService,
        protected WebFormApplicationService $webFormApplicationService
    ) {}

    /**
     * Get form data for public display.
     *
     * GET /forms/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $form = $this->webFormRepository->findBySlug($slug, true, ['fields']);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        // Record view
        $this->webFormRepository->trackView($form['id']);

        return response()->json([
            'data' => $this->transformPublicFormArray($form),
        ]);
    }

    /**
     * Render form HTML for iframe embedding.
     *
     * GET /forms/{slug}/render
     */
    public function render(string $slug): Response
    {
        $form = $this->webFormRepository->findBySlug($slug, true, ['fields']);

        if (!$form) {
            return response('Form not found', 404);
        }

        // Record view
        $this->webFormRepository->trackView($form['id']);

        // Generate HTML
        $html = $this->generateFormHtmlFromArray($form);

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Submit a form.
     *
     * POST /forms/{slug}/submit
     */
    public function submit(Request $request, string $slug): JsonResponse
    {
        $form = $this->webFormRepository->findBySlug($slug, true, ['fields']);

        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        try {
            // Use service to process submission - service expects model, so we'll need to keep using it
            $formModel = $this->webFormService->getFormBySlug($slug);
            $submission = $this->submissionService->processSubmission(
                $formModel,
                $request->all(),
                $request
            );

            $thankYouConfig = $form['thank_you_config'] ?? [];

            return response()->json([
                'success' => true,
                'message' => $thankYouConfig['message'] ?? 'Thank you for your submission!',
                'redirect_url' => $thankYouConfig['redirect_url'] ?? null,
                'submission_id' => $submission->id,
                'record_id' => $submission->record_id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Form submission error', [
                'form_slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your submission. Please try again.',
            ], 500);
        }
    }

    /**
     * Get JavaScript embed code.
     *
     * GET /forms/{slug}/embed.js
     */
    public function embedScript(string $slug): Response
    {
        $form = $this->webFormRepository->findBySlug($slug, true, ['fields']);

        if (!$form) {
            return response('// Form not found', 404)
                ->header('Content-Type', 'application/javascript');
        }

        $formData = json_encode($this->transformPublicFormArray($form));
        $submitUrl = url("/forms/{$slug}/submit");

        $js = $this->generateEmbedScriptFromArray($form, $formData, $submitUrl);

        return response($js, 200)
            ->header('Content-Type', 'application/javascript')
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Transform form array for public API response.
     */
    protected function transformPublicFormArray(array $form): array
    {
        $styling = $form['styling'] ?? [];
        $settings = $form['settings'] ?? [];
        $spamProtection = $form['spam_protection'] ?? [];
        $thankYouConfig = $form['thank_you_config'] ?? [];

        return [
            'id' => $form['id'],
            'name' => $form['name'],
            'slug' => $form['slug'],
            'description' => $form['description'] ?? null,
            'styling' => $styling,
            'settings' => [
                'submit_button_text' => $settings['submit_button_text'] ?? 'Submit',
            ],
            'spam_protection' => [
                'recaptcha_enabled' => !empty($spamProtection['recaptcha_enabled']),
                'recaptcha_site_key' => $spamProtection['recaptcha_site_key'] ?? null,
                'honeypot_enabled' => !empty($spamProtection['honeypot_enabled']),
            ],
            'fields' => array_map(fn ($field) => [
                'id' => $field['id'],
                'field_type' => $field['field_type'],
                'label' => $field['label'],
                'name' => $field['name'] ?? \Illuminate\Support\Str::snake($field['label']),
                'placeholder' => $field['placeholder'] ?? null,
                'is_required' => $field['is_required'] ?? false,
                'options' => $field['options'] ?? [],
                'settings' => $field['settings'] ?? [],
            ], $form['fields'] ?? []),
            'thank_you_config' => [
                'type' => $thankYouConfig['type'] ?? 'message',
                'message' => $thankYouConfig['message'] ?? 'Thank you!',
                'redirect_url' => $thankYouConfig['redirect_url'] ?? null,
            ],
        ];
    }

    /**
     * Transform form for public API response.
     */
    protected function transformPublicForm(WebForm $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'description' => $form->description,
            'styling' => $form->styling,
            'settings' => [
                'submit_button_text' => $form->getSetting('submit_button_text', 'Submit'),
            ],
            'spam_protection' => [
                'recaptcha_enabled' => !empty($form->spam_protection['recaptcha_enabled']),
                'recaptcha_site_key' => $form->getRecaptchaSiteKey(),
                'honeypot_enabled' => !empty($form->spam_protection['honeypot_enabled']),
            ],
            'fields' => $form->fields->map(fn ($field) => [
                'id' => $field->id,
                'field_type' => $field->field_type,
                'label' => $field->label,
                'name' => $field->field_name,
                'placeholder' => $field->placeholder,
                'is_required' => $field->is_required,
                'options' => $field->options,
                'settings' => $field->settings,
            ]),
            'thank_you_config' => [
                'type' => $form->thank_you_config['type'] ?? 'message',
                'message' => $form->thank_you_config['message'] ?? 'Thank you!',
                'redirect_url' => $form->thank_you_config['redirect_url'] ?? null,
            ],
        ];
    }

    /**
     * Generate standalone form HTML for iframe embedding.
     */
    protected function generateFormHtml(WebForm $form): string
    {
        $styling = $form->styling;
        $formData = $this->transformPublicForm($form);

        $fieldsHtml = '';
        foreach ($form->fields as $field) {
            $fieldsHtml .= $this->generateFieldHtml($field);
        }

        // Add honeypot if enabled
        $honeypotHtml = '';
        if (!empty($form->spam_protection['honeypot_enabled'])) {
            $honeypotField = $form->spam_protection['honeypot_field'] ?? '_honey';
            $honeypotHtml = '<div style="position:absolute;left:-9999px;"><input type="text" name="' . htmlspecialchars($honeypotField) . '" tabindex="-1" autocomplete="off"></div>';
        }

        // reCAPTCHA script
        $recaptchaHtml = '';
        if (!empty($form->spam_protection['recaptcha_enabled']) && $form->getRecaptchaSiteKey()) {
            $siteKey = htmlspecialchars($form->getRecaptchaSiteKey());
            $recaptchaHtml = '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
            $fieldsHtml .= '<div class="form-field"><div class="g-recaptcha" data-sitekey="' . $siteKey . '"></div></div>';
        }

        $submitText = htmlspecialchars($form->getSetting('submit_button_text', 'Submit'));
        $submitUrl = url("/forms/{$form->slug}/submit");

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$form->name}</title>
    {$recaptchaHtml}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: {$styling['font_family']};
            font-size: {$styling['font_size']};
            background-color: {$styling['background_color']};
            color: {$styling['text_color']};
            padding: {$styling['padding']};
        }
        .form-container {
            max-width: {$styling['max_width']};
            margin: 0 auto;
        }
        .form-title {
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 0.5em;
        }
        .form-description {
            color: {$styling['label_color']};
            margin-bottom: 1.5em;
        }
        .form-field {
            margin-bottom: 1rem;
        }
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: {$styling['label_color']};
        }
        .form-label .required {
            color: #dc2626;
            margin-left: 0.25rem;
        }
        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid {$styling['border_color']};
            border-radius: {$styling['border_radius']};
            font-family: inherit;
            font-size: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: {$styling['primary_color']};
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-checkbox-group,
        .form-radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-checkbox-item,
        .form-radio-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-submit {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: {$styling['primary_color']};
            color: white;
            border: none;
            border-radius: {$styling['border_radius']};
            font-family: inherit;
            font-size: inherit;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .form-submit:hover {
            opacity: 0.9;
        }
        .form-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-error {
            color: #dc2626;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }
        .form-message {
            padding: 1rem;
            border-radius: {$styling['border_radius']};
            margin-bottom: 1rem;
        }
        .form-message.success {
            background-color: #dcfce7;
            color: #166534;
        }
        .form-message.error {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .hidden { display: none; }
        {$styling['custom_css']}
    </style>
</head>
<body>
    <div class="form-container">
        <div id="form-message" class="form-message hidden"></div>
        <form id="vrtx-form" action="{$submitUrl}" method="POST">
            {$honeypotHtml}
            {$fieldsHtml}
            <div class="form-field">
                <button type="submit" class="form-submit" id="submit-btn">{$submitText}</button>
            </div>
        </form>
    </div>
    <script>
        document.getElementById('vrtx-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('submit-btn');
            const messageEl = document.getElementById('form-message');

            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            messageEl.classList.add('hidden');

            // Clear previous errors
            document.querySelectorAll('.form-error').forEach(el => el.remove());

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });

                const result = await response.json();

                if (result.success) {
                    if (result.redirect_url) {
                        window.location.href = result.redirect_url;
                    } else {
                        messageEl.textContent = result.message;
                        messageEl.className = 'form-message success';
                        form.reset();
                    }
                } else {
                    if (result.errors) {
                        Object.entries(result.errors).forEach(([field, messages]) => {
                            const fieldEl = form.querySelector('[name="' + field + '"]');
                            if (fieldEl) {
                                const errorEl = document.createElement('div');
                                errorEl.className = 'form-error';
                                errorEl.textContent = messages[0];
                                fieldEl.parentNode.appendChild(errorEl);
                            }
                        });
                    } else {
                        messageEl.textContent = result.message || 'An error occurred';
                        messageEl.className = 'form-message error';
                    }
                }
            } catch (error) {
                messageEl.textContent = 'An error occurred. Please try again.';
                messageEl.className = 'form-message error';
            }

            submitBtn.disabled = false;
            submitBtn.textContent = '{$submitText}';
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Generate standalone form HTML for iframe embedding from array.
     */
    protected function generateFormHtmlFromArray(array $form): string
    {
        $styling = $form['styling'] ?? [];
        $settings = $form['settings'] ?? [];
        $spamProtection = $form['spam_protection'] ?? [];
        $thankYouConfig = $form['thank_you_config'] ?? [];

        // Set defaults for styling
        $styling['font_family'] = $styling['font_family'] ?? 'system-ui, sans-serif';
        $styling['font_size'] = $styling['font_size'] ?? '16px';
        $styling['background_color'] = $styling['background_color'] ?? '#ffffff';
        $styling['text_color'] = $styling['text_color'] ?? '#1f2937';
        $styling['padding'] = $styling['padding'] ?? '2rem';
        $styling['max_width'] = $styling['max_width'] ?? '600px';
        $styling['label_color'] = $styling['label_color'] ?? '#374151';
        $styling['border_color'] = $styling['border_color'] ?? '#d1d5db';
        $styling['border_radius'] = $styling['border_radius'] ?? '6px';
        $styling['primary_color'] = $styling['primary_color'] ?? '#2563eb';
        $styling['custom_css'] = $styling['custom_css'] ?? '';

        $fieldsHtml = '';
        foreach ($form['fields'] ?? [] as $field) {
            $fieldsHtml .= $this->generateFieldHtmlFromArray($field);
        }

        // Add honeypot if enabled
        $honeypotHtml = '';
        if (!empty($spamProtection['honeypot_enabled'])) {
            $honeypotField = $spamProtection['honeypot_field'] ?? '_honey';
            $honeypotHtml = '<div style="position:absolute;left:-9999px;"><input type="text" name="' . htmlspecialchars($honeypotField) . '" tabindex="-1" autocomplete="off"></div>';
        }

        // reCAPTCHA script
        $recaptchaHtml = '';
        if (!empty($spamProtection['recaptcha_enabled']) && !empty($spamProtection['recaptcha_site_key'])) {
            $siteKey = htmlspecialchars($spamProtection['recaptcha_site_key']);
            $recaptchaHtml = '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
            $fieldsHtml .= '<div class="form-field"><div class="g-recaptcha" data-sitekey="' . $siteKey . '"></div></div>';
        }

        $submitText = htmlspecialchars($settings['submit_button_text'] ?? 'Submit');
        $submitUrl = url("/forms/{$form['slug']}/submit");
        $formName = htmlspecialchars($form['name']);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$formName}</title>
    {$recaptchaHtml}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: {$styling['font_family']};
            font-size: {$styling['font_size']};
            background-color: {$styling['background_color']};
            color: {$styling['text_color']};
            padding: {$styling['padding']};
        }
        .form-container {
            max-width: {$styling['max_width']};
            margin: 0 auto;
        }
        .form-title {
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 0.5em;
        }
        .form-description {
            color: {$styling['label_color']};
            margin-bottom: 1.5em;
        }
        .form-field {
            margin-bottom: 1rem;
        }
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: {$styling['label_color']};
        }
        .form-label .required {
            color: #dc2626;
            margin-left: 0.25rem;
        }
        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid {$styling['border_color']};
            border-radius: {$styling['border_radius']};
            font-family: inherit;
            font-size: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: {$styling['primary_color']};
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-checkbox-group,
        .form-radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-checkbox-item,
        .form-radio-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-submit {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: {$styling['primary_color']};
            color: white;
            border: none;
            border-radius: {$styling['border_radius']};
            font-family: inherit;
            font-size: inherit;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .form-submit:hover {
            opacity: 0.9;
        }
        .form-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-error {
            color: #dc2626;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }
        .form-message {
            padding: 1rem;
            border-radius: {$styling['border_radius']};
            margin-bottom: 1rem;
        }
        .form-message.success {
            background-color: #dcfce7;
            color: #166534;
        }
        .form-message.error {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .hidden { display: none; }
        {$styling['custom_css']}
    </style>
</head>
<body>
    <div class="form-container">
        <div id="form-message" class="form-message hidden"></div>
        <form id="vrtx-form" action="{$submitUrl}" method="POST">
            {$honeypotHtml}
            {$fieldsHtml}
            <div class="form-field">
                <button type="submit" class="form-submit" id="submit-btn">{$submitText}</button>
            </div>
        </form>
    </div>
    <script>
        document.getElementById('vrtx-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('submit-btn');
            const messageEl = document.getElementById('form-message');

            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            messageEl.classList.add('hidden');

            // Clear previous errors
            document.querySelectorAll('.form-error').forEach(el => el.remove());

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });

                const result = await response.json();

                if (result.success) {
                    if (result.redirect_url) {
                        window.location.href = result.redirect_url;
                    } else {
                        messageEl.textContent = result.message;
                        messageEl.className = 'form-message success';
                        form.reset();
                    }
                } else {
                    if (result.errors) {
                        Object.entries(result.errors).forEach(([field, messages]) => {
                            const fieldEl = form.querySelector('[name="' + field + '"]');
                            if (fieldEl) {
                                const errorEl = document.createElement('div');
                                errorEl.className = 'form-error';
                                errorEl.textContent = messages[0];
                                fieldEl.parentNode.appendChild(errorEl);
                            }
                        });
                    } else {
                        messageEl.textContent = result.message || 'An error occurred';
                        messageEl.className = 'form-message error';
                    }
                }
            } catch (error) {
                messageEl.textContent = 'An error occurred. Please try again.';
                messageEl.className = 'form-message error';
            }

            submitBtn.disabled = false;
            submitBtn.textContent = '{$submitText}';
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Generate HTML for a single field from array.
     */
    protected function generateFieldHtmlFromArray(array $field): string
    {
        $name = htmlspecialchars($field['name'] ?? \Illuminate\Support\Str::snake($field['label']));
        $label = htmlspecialchars($field['label']);
        $placeholder = htmlspecialchars($field['placeholder'] ?? '');
        $required = ($field['is_required'] ?? false) ? 'required' : '';
        $requiredMark = ($field['is_required'] ?? false) ? '<span class="required">*</span>' : '';

        switch ($field['field_type']) {
            case 'textarea':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <textarea class="form-textarea" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}></textarea>
</div>
HTML;

            case 'select':
                $options = '<option value="">Select...</option>';
                foreach ($field['options'] ?? [] as $opt) {
                    $optValue = htmlspecialchars($opt['value'] ?? $opt);
                    $optLabel = htmlspecialchars($opt['label'] ?? $opt);
                    $options .= "<option value=\"{$optValue}\">{$optLabel}</option>";
                }
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <select class="form-select" id="{$name}" name="{$name}" {$required}>{$options}</select>
</div>
HTML;

            case 'radio':
                $optionsHtml = '';
                foreach ($field['options'] ?? [] as $index => $opt) {
                    $optValue = htmlspecialchars($opt['value'] ?? $opt);
                    $optLabel = htmlspecialchars($opt['label'] ?? $opt);
                    $optionsHtml .= "<label class=\"form-radio-item\"><input type=\"radio\" name=\"{$name}\" value=\"{$optValue}\" {$required}> {$optLabel}</label>";
                }
                return <<<HTML
<div class="form-field">
    <label class="form-label">{$label}{$requiredMark}</label>
    <div class="form-radio-group">{$optionsHtml}</div>
</div>
HTML;

            case 'checkbox':
                if (!empty($field['options'])) {
                    $optionsHtml = '';
                    foreach ($field['options'] as $opt) {
                        $optValue = htmlspecialchars($opt['value'] ?? $opt);
                        $optLabel = htmlspecialchars($opt['label'] ?? $opt);
                        $optionsHtml .= "<label class=\"form-checkbox-item\"><input type=\"checkbox\" name=\"{$name}[]\" value=\"{$optValue}\"> {$optLabel}</label>";
                    }
                    return <<<HTML
<div class="form-field">
    <label class="form-label">{$label}{$requiredMark}</label>
    <div class="form-checkbox-group">{$optionsHtml}</div>
</div>
HTML;
                }
                return <<<HTML
<div class="form-field">
    <label class="form-checkbox-item">
        <input type="checkbox" name="{$name}" value="true" {$required}>
        {$label}{$requiredMark}
    </label>
</div>
HTML;

            case 'hidden':
                return "<input type=\"hidden\" name=\"{$name}\" value=\"{$placeholder}\">";

            case 'date':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="date" class="form-input" id="{$name}" name="{$name}" {$required}>
</div>
HTML;

            case 'datetime':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="datetime-local" class="form-input" id="{$name}" name="{$name}" {$required}>
</div>
HTML;

            case 'number':
            case 'currency':
                $step = $field['field_type'] === 'currency' ? 'step="0.01"' : '';
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="number" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$step} {$required}>
</div>
HTML;

            case 'file':
                $accept = '';
                if (!empty($field['validation_rules']['allowed_types'])) {
                    $accept = 'accept=".' . implode(',.', $field['validation_rules']['allowed_types']) . '"';
                }
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="file" class="form-input" id="{$name}" name="{$name}" {$accept} {$required}>
</div>
HTML;

            case 'email':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="email" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}>
</div>
HTML;

            case 'phone':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="tel" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}>
</div>
HTML;

            case 'url':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="url" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}>
</div>
HTML;

            default: // text
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="text" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}>
</div>
HTML;
        }
    }

    /**
     * Generate HTML for a single field.
     */
    protected function generateFieldHtml($field): string
    {
        $name = htmlspecialchars($field->field_name);
        $label = htmlspecialchars($field->label);
        $placeholder = htmlspecialchars($field->placeholder ?? '');
        $required = $field->is_required ? 'required' : '';
        $requiredMark = $field->is_required ? '<span class="required">*</span>' : '';

        switch ($field->field_type) {
            case 'textarea':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <textarea class="form-textarea" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}></textarea>
</div>
HTML;

            case 'select':
                $options = '<option value="">Select...</option>';
                foreach ($field->options ?? [] as $opt) {
                    $optValue = htmlspecialchars($opt['value'] ?? $opt);
                    $optLabel = htmlspecialchars($opt['label'] ?? $opt);
                    $options .= "<option value=\"{$optValue}\">{$optLabel}</option>";
                }
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <select class="form-select" id="{$name}" name="{$name}" {$required}>{$options}</select>
</div>
HTML;

            case 'radio':
                $optionsHtml = '';
                foreach ($field->options ?? [] as $index => $opt) {
                    $optValue = htmlspecialchars($opt['value'] ?? $opt);
                    $optLabel = htmlspecialchars($opt['label'] ?? $opt);
                    $optionsHtml .= "<label class=\"form-radio-item\"><input type=\"radio\" name=\"{$name}\" value=\"{$optValue}\" {$required}> {$optLabel}</label>";
                }
                return <<<HTML
<div class="form-field">
    <label class="form-label">{$label}{$requiredMark}</label>
    <div class="form-radio-group">{$optionsHtml}</div>
</div>
HTML;

            case 'checkbox':
                if (!empty($field->options)) {
                    $optionsHtml = '';
                    foreach ($field->options as $opt) {
                        $optValue = htmlspecialchars($opt['value'] ?? $opt);
                        $optLabel = htmlspecialchars($opt['label'] ?? $opt);
                        $optionsHtml .= "<label class=\"form-checkbox-item\"><input type=\"checkbox\" name=\"{$name}[]\" value=\"{$optValue}\"> {$optLabel}</label>";
                    }
                    return <<<HTML
<div class="form-field">
    <label class="form-label">{$label}{$requiredMark}</label>
    <div class="form-checkbox-group">{$optionsHtml}</div>
</div>
HTML;
                }
                return <<<HTML
<div class="form-field">
    <label class="form-checkbox-item">
        <input type="checkbox" name="{$name}" value="true" {$required}>
        {$label}{$requiredMark}
    </label>
</div>
HTML;

            case 'hidden':
                return "<input type=\"hidden\" name=\"{$name}\" value=\"{$placeholder}\">";

            case 'date':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="date" class="form-input" id="{$name}" name="{$name}" {$required}>
</div>
HTML;

            case 'datetime':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="datetime-local" class="form-input" id="{$name}" name="{$name}" {$required}>
</div>
HTML;

            case 'number':
            case 'currency':
                $step = $field->field_type === 'currency' ? 'step="0.01"' : '';
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="number" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$step} {$required}>
</div>
HTML;

            case 'file':
                $accept = '';
                if (!empty($field->validation_rules['allowed_types'])) {
                    $accept = 'accept=".' . implode(',.', $field->validation_rules['allowed_types']) . '"';
                }
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="file" class="form-input" id="{$name}" name="{$name}" {$accept} {$required}>
</div>
HTML;

            case 'email':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="email" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}>
</div>
HTML;

            case 'phone':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="tel" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}>
</div>
HTML;

            case 'url':
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="url" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}>
</div>
HTML;

            default: // text
                return <<<HTML
<div class="form-field">
    <label class="form-label" for="{$name}">{$label}{$requiredMark}</label>
    <input type="text" class="form-input" id="{$name}" name="{$name}" placeholder="{$placeholder}" {$required}>
</div>
HTML;
        }
    }

    /**
     * Generate JavaScript embed code from array.
     */
    protected function generateEmbedScriptFromArray(array $form, string $formData, string $submitUrl): string
    {
        $containerId = "vrtx-form-{$form['slug']}";

        return <<<JS
(function() {
    const formData = {$formData};
    const submitUrl = '{$submitUrl}';
    const containerId = '{$containerId}';

    function init() {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('VRTX Form: Container not found:', containerId);
            return;
        }

        const styling = formData.styling || {};
        const fields = formData.fields || [];

        let fieldsHtml = '';
        fields.forEach(function(field) {
            fieldsHtml += generateFieldHtml(field);
        });

        const formHtml = `
            <style>
                #\${containerId} { font-family: \${styling.font_family || 'system-ui, sans-serif'}; }
                #\${containerId} .vrtx-field { margin-bottom: 1rem; }
                #\${containerId} .vrtx-label { display: block; font-weight: 500; margin-bottom: 0.25rem; }
                #\${containerId} .vrtx-input { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; }
                #\${containerId} .vrtx-submit { padding: 0.75rem 1.5rem; background: \${styling.primary_color || '#2563eb'}; color: white; border: none; border-radius: 6px; cursor: pointer; }
                #\${containerId} .vrtx-message { padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
                #\${containerId} .vrtx-success { background: #dcfce7; color: #166534; }
                #\${containerId} .vrtx-error { background: #fee2e2; color: #991b1b; }
            </style>
            <div id="\${containerId}-message" style="display:none;"></div>
            <form id="\${containerId}-form">
                \${fieldsHtml}
                <div class="vrtx-field">
                    <button type="submit" class="vrtx-submit">\${formData.settings?.submit_button_text || 'Submit'}</button>
                </div>
            </form>
        `;

        container.innerHTML = formHtml;

        document.getElementById(containerId + '-form').addEventListener('submit', handleSubmit);
    }

    function generateFieldHtml(field) {
        const required = field.is_required ? 'required' : '';
        const requiredMark = field.is_required ? '<span style="color:#dc2626">*</span>' : '';

        switch(field.field_type) {
            case 'textarea':
                return '<div class="vrtx-field"><label class="vrtx-label">' + field.label + requiredMark + '</label><textarea class="vrtx-input" name="' + field.name + '" placeholder="' + (field.placeholder || '') + '" ' + required + '></textarea></div>';
            case 'select':
                let options = '<option value="">Select...</option>';
                (field.options || []).forEach(function(opt) {
                    options += '<option value="' + (opt.value || opt) + '">' + (opt.label || opt) + '</option>';
                });
                return '<div class="vrtx-field"><label class="vrtx-label">' + field.label + requiredMark + '</label><select class="vrtx-input" name="' + field.name + '" ' + required + '>' + options + '</select></div>';
            default:
                const inputType = field.field_type === 'email' ? 'email' : (field.field_type === 'phone' ? 'tel' : 'text');
                return '<div class="vrtx-field"><label class="vrtx-label">' + field.label + requiredMark + '</label><input type="' + inputType + '" class="vrtx-input" name="' + field.name + '" placeholder="' + (field.placeholder || '') + '" ' + required + '></div>';
        }
    }

    async function handleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const messageEl = document.getElementById(containerId + '-message');
        const formDataObj = new FormData(form);
        const data = Object.fromEntries(formDataObj.entries());

        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                } else {
                    messageEl.textContent = result.message;
                    messageEl.className = 'vrtx-message vrtx-success';
                    messageEl.style.display = 'block';
                    form.reset();
                }
            } else {
                messageEl.textContent = result.message || 'An error occurred';
                messageEl.className = 'vrtx-message vrtx-error';
                messageEl.style.display = 'block';
            }
        } catch(err) {
            messageEl.textContent = 'An error occurred';
            messageEl.className = 'vrtx-message vrtx-error';
            messageEl.style.display = 'block';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS;
    }

    /**
     * Generate JavaScript embed code.
     */
    protected function generateEmbedScript(WebForm $form, string $formData, string $submitUrl): string
    {
        $containerId = "vrtx-form-{$form->slug}";

        return <<<JS
(function() {
    const formData = {$formData};
    const submitUrl = '{$submitUrl}';
    const containerId = '{$containerId}';

    function init() {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('VRTX Form: Container not found:', containerId);
            return;
        }

        const styling = formData.styling || {};
        const fields = formData.fields || [];

        let fieldsHtml = '';
        fields.forEach(function(field) {
            fieldsHtml += generateFieldHtml(field);
        });

        const formHtml = `
            <style>
                #${containerId} { font-family: \${styling.font_family || 'system-ui, sans-serif'}; }
                #${containerId} .vrtx-field { margin-bottom: 1rem; }
                #${containerId} .vrtx-label { display: block; font-weight: 500; margin-bottom: 0.25rem; }
                #${containerId} .vrtx-input { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; }
                #${containerId} .vrtx-submit { padding: 0.75rem 1.5rem; background: \${styling.primary_color || '#2563eb'}; color: white; border: none; border-radius: 6px; cursor: pointer; }
                #${containerId} .vrtx-message { padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
                #${containerId} .vrtx-success { background: #dcfce7; color: #166534; }
                #${containerId} .vrtx-error { background: #fee2e2; color: #991b1b; }
            </style>
            <div id="${containerId}-message" style="display:none;"></div>
            <form id="${containerId}-form">
                \${fieldsHtml}
                <div class="vrtx-field">
                    <button type="submit" class="vrtx-submit">\${formData.settings?.submit_button_text || 'Submit'}</button>
                </div>
            </form>
        `;

        container.innerHTML = formHtml;

        document.getElementById(containerId + '-form').addEventListener('submit', handleSubmit);
    }

    function generateFieldHtml(field) {
        const required = field.is_required ? 'required' : '';
        const requiredMark = field.is_required ? '<span style="color:#dc2626">*</span>' : '';

        switch(field.field_type) {
            case 'textarea':
                return '<div class="vrtx-field"><label class="vrtx-label">' + field.label + requiredMark + '</label><textarea class="vrtx-input" name="' + field.name + '" placeholder="' + (field.placeholder || '') + '" ' + required + '></textarea></div>';
            case 'select':
                let options = '<option value="">Select...</option>';
                (field.options || []).forEach(function(opt) {
                    options += '<option value="' + (opt.value || opt) + '">' + (opt.label || opt) + '</option>';
                });
                return '<div class="vrtx-field"><label class="vrtx-label">' + field.label + requiredMark + '</label><select class="vrtx-input" name="' + field.name + '" ' + required + '>' + options + '</select></div>';
            default:
                const inputType = field.field_type === 'email' ? 'email' : (field.field_type === 'phone' ? 'tel' : 'text');
                return '<div class="vrtx-field"><label class="vrtx-label">' + field.label + requiredMark + '</label><input type="' + inputType + '" class="vrtx-input" name="' + field.name + '" placeholder="' + (field.placeholder || '') + '" ' + required + '></div>';
        }
    }

    async function handleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const messageEl = document.getElementById(containerId + '-message');
        const formDataObj = new FormData(form);
        const data = Object.fromEntries(formDataObj.entries());

        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                if (result.redirect_url) {
                    window.location.href = result.redirect_url;
                } else {
                    messageEl.textContent = result.message;
                    messageEl.className = 'vrtx-message vrtx-success';
                    messageEl.style.display = 'block';
                    form.reset();
                }
            } else {
                messageEl.textContent = result.message || 'An error occurred';
                messageEl.className = 'vrtx-message vrtx-error';
                messageEl.style.display = 'block';
            }
        } catch(err) {
            messageEl.textContent = 'An error occurred';
            messageEl.className = 'vrtx-message vrtx-error';
            messageEl.style.display = 'block';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS;
    }
}
