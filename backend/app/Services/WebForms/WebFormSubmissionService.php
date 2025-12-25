<?php

declare(strict_types=1);

namespace App\Services\WebForms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WebFormSubmissionService
{
    /**
     * Process a form submission.
     */
    public function processSubmission(WebForm $form, array $data, Request $request): WebFormSubmission
    {
        // Extract UTM params
        $utmParams = $this->extractUtmParams($data);

        // Remove UTM params from submission data
        $submissionData = array_filter($data, fn($key) => !str_starts_with($key, 'utm_'), ARRAY_FILTER_USE_KEY);

        // Create initial submission record
        $submission = DB::table('web_form_submissions')->insertGetId([
            'web_form_id' => $form->id,
            'submission_data' => $submissionData,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('Referer'),
            'utm_params' => $utmParams,
            'status' => WebFormSubmission::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        try {
            // Check for spam
            if ($form->hasSpamProtection()) {
                $this->validateSpamProtection($form, $data, $request);
            }

            // Validate submission data
            $validatedData = $this->validateSubmission($form, $submissionData);

            // Create module record
            $record = $this->createModuleRecord($form, $validatedData);

            // Update submission as processed
            $submission->update([
                'record_id' => $record->id,
                'status' => WebFormSubmission::STATUS_PROCESSED,
            ]);

            // Update analytics
            WebFormAnalytics::incrementSubmissions($form->id, true);

            // Trigger any post-submission actions
            $this->triggerPostSubmissionActions($form, $submission, $record);

        } catch (ValidationException $e) {
            $submission->update([
                'status' => WebFormSubmission::STATUS_FAILED,
                'error_message' => json_encode($e->errors()),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Web form submission failed', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),
            ]);

            $submission->update([
                'status' => WebFormSubmission::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $submission;
    }

    /**
     * Validate spam protection (reCAPTCHA).
     */
    protected function validateSpamProtection(WebForm $form, array $data, Request $request): void
    {
        $spamConfig = $form->spam_protection;

        if (!empty($spamConfig['recaptcha_enabled'])) {
            $recaptchaToken = $data['g-recaptcha-response'] ?? $data['recaptcha_token'] ?? null;

            if (empty($recaptchaToken)) {
                WebFormAnalytics::incrementSpamBlocked($form->id);
                throw ValidationException::withMessages([
                    'recaptcha' => ['Please complete the reCAPTCHA verification.'],
                ]);
            }

            $secretKey = $spamConfig['recaptcha_secret_key'] ?? config('services.recaptcha.secret');

            if ($secretKey) {
                $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secretKey,
                    'response' => $recaptchaToken,
                    'remoteip' => $request->ip(),
                ]);

                $result = $response->json();

                if (!($result['success'] ?? false)) {
                    WebFormAnalytics::incrementSpamBlocked($form->id);
                    throw ValidationException::withMessages([
                        'recaptcha' => ['reCAPTCHA verification failed. Please try again.'],
                    ]);
                }

                // Check score for reCAPTCHA v3
                if (isset($result['score']) && $result['score'] < ($spamConfig['min_score'] ?? 0.5)) {
                    WebFormAnalytics::incrementSpamBlocked($form->id);
                    throw ValidationException::withMessages([
                        'recaptcha' => ['Suspicious activity detected.'],
                    ]);
                }
            }
        }

        // Honeypot field check
        if (!empty($spamConfig['honeypot_enabled'])) {
            $honeypotField = $spamConfig['honeypot_field'] ?? '_honey';
            if (!empty($data[$honeypotField])) {
                WebFormAnalytics::incrementSpamBlocked($form->id);
                throw ValidationException::withMessages([
                    'spam' => ['Submission blocked.'],
                ]);
            }
        }
    }

    /**
     * Validate submission data against form fields.
     */
    protected function validateSubmission(WebForm $form, array $data): array
    {
        $rules = [];
        $messages = [];
        $attributes = [];

        foreach ($form->fields as $field) {
            $fieldName = $field->field_name;
            $rules[$fieldName] = $field->getValidationRulesArray();
            $attributes[$fieldName] = $field->label;
        }

        $validator = Validator::make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Create a module record from the submission.
     */
    protected function createModuleRecord(WebForm $form, array $validatedData): ModuleRecord
    {
        $module = $form->module;
        $recordData = [];

        // Map form field values to module fields
        foreach ($form->fields as $field) {
            if ($field->module_field_id && $field->moduleField) {
                $fieldName = $field->field_name;
                $moduleFieldApiName = $field->moduleField->api_name;

                if (isset($validatedData[$fieldName])) {
                    $recordData[$moduleFieldApiName] = $this->transformFieldValue(
                        $field,
                        $validatedData[$fieldName]
                    );
                }
            }
        }

        // Add metadata
        $recordData['_source'] = 'web_form';
        $recordData['_form_id'] = $form->id;
        $recordData['_form_name'] = $form->name;

        // Create the record
        $record = DB::table('module_records')->insertGetId([
            'module_id' => $module->id,
            'data' => $recordData,
            'owner_id' => $form->assign_to_user_id,
        ]);

        return $record;
    }

    /**
     * Transform field value based on field type.
     */
    protected function transformFieldValue($field, mixed $value): mixed
    {
        switch ($field->field_type) {
            case 'multi_select':
                return is_array($value) ? $value : [$value];

            case 'checkbox':
                if (is_string($value)) {
                    return $value === 'true' || $value === '1' || $value === 'on';
                }
                return (bool) $value;

            case 'number':
            case 'currency':
                return is_numeric($value) ? (float) $value : null;

            case 'date':
            case 'datetime':
                return $value ?: null;

            default:
                return $value;
        }
    }

    /**
     * Trigger post-submission actions.
     */
    protected function triggerPostSubmissionActions(
        WebForm $form,
        WebFormSubmission $submission,
        ModuleRecord $record
    ): void {
        $settings = $form->settings;

        // Auto-responder email
        if (!empty($settings['auto_responder_enabled'])) {
            // TODO: Send auto-responder email
            // This would integrate with the email system
        }

        // Notification email
        if (!empty($settings['notification_email'])) {
            // TODO: Send notification to admin
        }

        // Webhook
        if (!empty($settings['webhook_url'])) {
            $this->sendWebhook($settings['webhook_url'], $form, $submission, $record);
        }
    }

    /**
     * Send webhook notification.
     */
    protected function sendWebhook(
        string $url,
        WebForm $form,
        WebFormSubmission $submission,
        ModuleRecord $record
    ): void {
        try {
            Http::timeout(10)->post($url, [
                'event' => 'form_submission',
                'form_id' => $form->id,
                'form_name' => $form->name,
                'submission_id' => $submission->id,
                'record_id' => $record->id,
                'data' => $submission->submission_data,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Web form webhook failed', [
                'form_id' => $form->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract UTM parameters from submission data.
     */
    protected function extractUtmParams(array $data): array
    {
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $utmParams = [];

        foreach ($utmKeys as $key) {
            if (!empty($data[$key])) {
                $utmParams[$key] = $data[$key];
            }
        }

        return $utmParams;
    }

    /**
     * Record a form view for analytics.
     */
    public function recordFormView(WebForm $form): void
    {
        WebFormAnalytics::incrementViews($form->id);
    }

    /**
     * Get validation rules for public display.
     */
    public function getPublicValidationRules(WebForm $form): array
    {
        $rules = [];

        foreach ($form->fields as $field) {
            $rules[$field->field_name] = [
                'required' => $field->is_required,
                'type' => $field->field_type,
                'label' => $field->label,
            ];

            if ($field->validation_rules) {
                $rules[$field->field_name] = array_merge(
                    $rules[$field->field_name],
                    $field->validation_rules
                );
            }
        }

        return $rules;
    }
}
