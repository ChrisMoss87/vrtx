<?php

declare(strict_types=1);

namespace App\Services\Cadence;

use App\Models\Cadence;
use App\Models\CadenceEnrollment;
use App\Models\CadenceMetric;
use App\Models\CadenceStep;
use App\Models\CadenceStepExecution;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\ModuleRecord;
use App\Models\SmsConnection;
use App\Models\Task;
use App\Services\Email\EmailService;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for executing cadence steps.
 *
 * This service handles the actual execution of cadence steps when enrollments
 * are due, including sending emails, creating tasks, and advancing enrollments.
 */
class CadenceExecutionService
{
    public function __construct(
        protected EmailService $emailService,
        protected SmsService $smsService
    ) {}
    /**
     * Process all due cadence step executions.
     */
    public function processDueSteps(): array
    {
        $stats = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // Find all active enrollments that are due for their next step
        $dueEnrollments = CadenceEnrollment::query()
            ->with(['cadence', 'currentStep', 'cadence.steps'])
            ->where('status', CadenceEnrollment::STATUS_ACTIVE)
            ->where('next_step_at', '<=', now())
            ->whereHas('cadence', fn($q) => $q->where('status', Cadence::STATUS_ACTIVE))
            ->limit(100) // Process in batches
            ->get();

        foreach ($dueEnrollments as $enrollment) {
            $stats['processed']++;

            try {
                $result = $this->processEnrollmentStep($enrollment);

                if ($result === 'success') {
                    $stats['succeeded']++;
                } elseif ($result === 'skipped') {
                    $stats['skipped']++;
                } else {
                    $stats['failed']++;
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error('Cadence step execution failed', [
                    'enrollment_id' => $enrollment->id,
                    'cadence_id' => $enrollment->cadence_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Process a single enrollment's current step.
     */
    public function processEnrollmentStep(CadenceEnrollment $enrollment): string
    {
        $step = $enrollment->currentStep;

        if (!$step || !$step->is_active) {
            // Try to advance to the next step
            $nextStep = $this->getNextStep($enrollment);
            if ($nextStep) {
                $this->scheduleNextStep($enrollment, $nextStep);
                return 'skipped';
            }

            // No more steps - complete the enrollment
            $enrollment->complete('All steps completed');
            return 'success';
        }

        // Handle A/B testing - select variant if applicable
        if ($step->is_ab_test && $step->abVariants()->exists()) {
            $step = $this->selectAbVariant($step);
        }

        // Create step execution record
        $execution = CadenceStepExecution::create([
            'enrollment_id' => $enrollment->id,
            'step_id' => $step->id,
            'scheduled_at' => $enrollment->next_step_at,
            'status' => CadenceStepExecution::STATUS_EXECUTING,
        ]);

        try {
            // Execute the step based on channel
            $result = $this->executeStep($step, $enrollment, $execution);

            if ($result['success']) {
                $execution->markAsCompleted(
                    $result['result'] ?? CadenceStepExecution::RESULT_COMPLETED,
                    $result['metadata'] ?? []
                );

                // Record metrics
                $this->recordMetric($enrollment->cadence_id, $step->channel, 'executed');

                // Advance to next step
                $this->advanceEnrollment($enrollment, $step);

                return 'success';
            } else {
                $execution->markAsFailed(
                    $result['error'] ?? 'Unknown error',
                    $result['metadata'] ?? []
                );

                // Record failure metric
                $this->recordMetric($enrollment->cadence_id, $step->channel, 'failed');

                // Still advance the enrollment (don't get stuck on failures)
                $this->advanceEnrollment($enrollment, $step);

                return 'failed';
            }
        } catch (\Exception $e) {
            $execution->markAsFailed($e->getMessage());
            $this->recordMetric($enrollment->cadence_id, $step->channel, 'failed');

            // Advance anyway
            $this->advanceEnrollment($enrollment, $step);

            throw $e;
        }
    }

    /**
     * Execute a step based on its channel type.
     */
    protected function executeStep(CadenceStep $step, CadenceEnrollment $enrollment, CadenceStepExecution $execution): array
    {
        return match ($step->channel) {
            CadenceStep::CHANNEL_EMAIL => $this->executeEmailStep($step, $enrollment),
            CadenceStep::CHANNEL_CALL => $this->executeCallStep($step, $enrollment),
            CadenceStep::CHANNEL_SMS => $this->executeSmsStep($step, $enrollment),
            CadenceStep::CHANNEL_LINKEDIN => $this->executeLinkedInStep($step, $enrollment),
            CadenceStep::CHANNEL_TASK => $this->executeTaskStep($step, $enrollment),
            CadenceStep::CHANNEL_WAIT => $this->executeWaitStep($step, $enrollment),
            default => ['success' => false, 'error' => "Unknown channel: {$step->channel}"],
        };
    }

    /**
     * Execute an email step.
     */
    protected function executeEmailStep(CadenceStep $step, CadenceEnrollment $enrollment): array
    {
        // Get the record data for personalization
        $record = ModuleRecord::find($enrollment->record_id);

        if (!$record) {
            return ['success' => false, 'error' => 'Record not found'];
        }

        // Get email from record data
        $recordData = $record->data ?? [];
        $email = $recordData['email'] ?? $recordData['Email'] ?? $recordData['email_address'] ?? null;

        if (!$email) {
            return ['success' => false, 'error' => 'No email address found on record'];
        }

        // Build email content with personalization
        $subject = $this->personalizeContent($step->subject ?? '', $recordData);
        $content = $this->personalizeContent($step->content ?? '', $recordData);

        // If using a template, load it
        if ($step->template_id && $step->template) {
            $subject = $subject ?: $this->personalizeContent($step->template->subject ?? '', $recordData);
            $content = $content ?: $this->personalizeContent($step->template->content ?? '', $recordData);
        }

        try {
            // Get the email account for sending
            // Priority: cadence owner's default account > system default account
            $emailAccount = $this->getEmailAccountForCadence($enrollment->cadence);

            if (!$emailAccount) {
                return ['success' => false, 'error' => 'No email account configured for sending'];
            }

            // Create the email message
            $emailMessage = EmailMessage::create([
                'account_id' => $emailAccount->id,
                'user_id' => $enrollment->cadence->owner_id ?? $enrollment->enrolled_by,
                'direction' => EmailMessage::DIRECTION_OUTBOUND,
                'status' => EmailMessage::STATUS_DRAFT,
                'from_email' => $emailAccount->email_address,
                'from_name' => $emailAccount->name,
                'to_emails' => [$email],
                'subject' => $subject,
                'body_html' => $content,
                'body_text' => strip_tags($content),
                'linked_record_type' => ModuleRecord::class,
                'linked_record_id' => $enrollment->record_id,
                'metadata' => [
                    'cadence_id' => $enrollment->cadence_id,
                    'enrollment_id' => $enrollment->id,
                    'step_id' => $step->id,
                    'source' => 'cadence',
                ],
            ]);

            // Send the email
            $sent = $this->emailService->send($emailMessage);

            if ($sent) {
                Log::info('Cadence email sent successfully', [
                    'enrollment_id' => $enrollment->id,
                    'record_id' => $enrollment->record_id,
                    'to' => $email,
                    'subject' => $subject,
                    'email_message_id' => $emailMessage->id,
                ]);

                return [
                    'success' => true,
                    'result' => CadenceStepExecution::RESULT_SENT,
                    'metadata' => [
                        'to' => $email,
                        'subject' => $subject,
                        'email_message_id' => $emailMessage->id,
                    ],
                ];
            }

            return [
                'success' => false,
                'error' => $emailMessage->failed_reason ?? 'Failed to send email',
            ];
        } catch (\Exception $e) {
            Log::error('Cadence email failed', [
                'enrollment_id' => $enrollment->id,
                'record_id' => $enrollment->record_id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get the email account to use for a cadence.
     */
    protected function getEmailAccountForCadence(Cadence $cadence): ?EmailAccount
    {
        // First, try to get the cadence owner's default email account
        if ($cadence->owner_id) {
            $ownerAccount = EmailAccount::where('user_id', $cadence->owner_id)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($ownerAccount) {
                return $ownerAccount;
            }

            // Fallback to any active account for the owner
            $ownerAccount = EmailAccount::where('user_id', $cadence->owner_id)
                ->where('is_active', true)
                ->first();

            if ($ownerAccount) {
                return $ownerAccount;
            }
        }

        // Fallback to any system-wide active account (for system cadences)
        return EmailAccount::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first();
    }

    /**
     * Execute a call step (creates a task for the rep).
     */
    protected function executeCallStep(CadenceStep $step, CadenceEnrollment $enrollment): array
    {
        $record = ModuleRecord::find($enrollment->record_id);

        if (!$record) {
            return ['success' => false, 'error' => 'Record not found'];
        }

        $recordData = $record->data ?? [];
        $phone = $recordData['phone'] ?? $recordData['Phone'] ?? $recordData['mobile'] ?? null;

        // Create a call task for the assigned user
        $assignedTo = $step->task_assigned_to ?? $enrollment->enrolled_by ?? $enrollment->cadence->owner_id;

        try {
            // Create task if Task model exists
            if (class_exists(Task::class)) {
                Task::create([
                    'title' => "Call: " . ($recordData['name'] ?? $recordData['first_name'] ?? 'Contact'),
                    'description' => $step->content ?? "Make a call to this contact as part of cadence: {$enrollment->cadence->name}",
                    'type' => 'call',
                    'assigned_to' => $assignedTo,
                    'due_at' => now(),
                    'related_record_id' => $enrollment->record_id,
                    'related_record_type' => ModuleRecord::class,
                    'metadata' => [
                        'cadence_id' => $enrollment->cadence_id,
                        'enrollment_id' => $enrollment->id,
                        'step_id' => $step->id,
                        'phone' => $phone,
                    ],
                ]);
            }

            Log::info('Cadence call task created', [
                'enrollment_id' => $enrollment->id,
                'record_id' => $enrollment->record_id,
                'assigned_to' => $assignedTo,
                'phone' => $phone,
            ]);

            return [
                'success' => true,
                'result' => CadenceStepExecution::RESULT_COMPLETED,
                'metadata' => [
                    'phone' => $phone,
                    'task_created' => true,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute an SMS step.
     */
    protected function executeSmsStep(CadenceStep $step, CadenceEnrollment $enrollment): array
    {
        $record = ModuleRecord::find($enrollment->record_id);

        if (!$record) {
            return ['success' => false, 'error' => 'Record not found'];
        }

        $recordData = $record->data ?? [];
        $phone = $recordData['phone'] ?? $recordData['Phone'] ?? $recordData['mobile'] ?? $recordData['Mobile'] ?? $recordData['phone_number'] ?? null;

        if (!$phone) {
            return ['success' => false, 'error' => 'No phone number found on record'];
        }

        $message = $this->personalizeContent($step->content ?? '', $recordData);

        try {
            // Get SMS connection for the cadence
            $smsConnection = $this->getSmsConnectionForCadence($enrollment->cadence);

            if (!$smsConnection) {
                return ['success' => false, 'error' => 'No SMS connection configured for sending'];
            }

            // Send the SMS using the SMS service
            $smsMessage = $this->smsService->sendMessage(
                connection: $smsConnection,
                to: $phone,
                content: $message,
                template: $step->sms_template_id ? \App\Models\SmsTemplate::find($step->sms_template_id) : null,
                mergeData: $recordData,
                recordId: $enrollment->record_id,
                moduleApiName: $record->module?->api_name
            );

            if ($smsMessage->status === 'sent' || $smsMessage->status === 'delivered') {
                Log::info('Cadence SMS sent successfully', [
                    'enrollment_id' => $enrollment->id,
                    'record_id' => $enrollment->record_id,
                    'to' => $phone,
                    'sms_message_id' => $smsMessage->id,
                ]);

                return [
                    'success' => true,
                    'result' => CadenceStepExecution::RESULT_SENT,
                    'metadata' => [
                        'to' => $phone,
                        'message_length' => strlen($message),
                        'segment_count' => $smsMessage->segment_count,
                        'sms_message_id' => $smsMessage->id,
                    ],
                ];
            }

            // Check for opt-out or rate limit failures
            if ($smsMessage->error_code === 'OPT_OUT') {
                return [
                    'success' => false,
                    'error' => 'Recipient has opted out of SMS messages',
                    'metadata' => ['opt_out' => true],
                ];
            }

            if ($smsMessage->error_code === 'RATE_LIMIT') {
                return [
                    'success' => false,
                    'error' => 'SMS rate limit exceeded',
                    'metadata' => ['rate_limited' => true],
                ];
            }

            return [
                'success' => false,
                'error' => $smsMessage->error_message ?? 'Failed to send SMS',
            ];
        } catch (\Exception $e) {
            Log::error('Cadence SMS failed', [
                'enrollment_id' => $enrollment->id,
                'record_id' => $enrollment->record_id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get the SMS connection to use for a cadence.
     */
    protected function getSmsConnectionForCadence(Cadence $cadence): ?SmsConnection
    {
        // First, check if the cadence has a specific SMS connection configured
        if ($cadence->sms_connection_id) {
            $connection = SmsConnection::where('id', $cadence->sms_connection_id)
                ->where('is_active', true)
                ->first();

            if ($connection) {
                return $connection;
            }
        }

        // Fallback to the default active SMS connection
        return SmsConnection::where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? SmsConnection::where('is_active', true)->first();
    }

    /**
     * Execute a LinkedIn step.
     */
    protected function executeLinkedInStep(CadenceStep $step, CadenceEnrollment $enrollment): array
    {
        // LinkedIn actions are typically manual or require API integration
        // Create a task for the user to perform the LinkedIn action

        $record = ModuleRecord::find($enrollment->record_id);
        $recordData = $record?->data ?? [];
        $linkedinUrl = $recordData['linkedin'] ?? $recordData['linkedin_url'] ?? null;

        $actionDescription = match ($step->linkedin_action) {
            CadenceStep::LINKEDIN_CONNECTION_REQUEST => 'Send a connection request',
            CadenceStep::LINKEDIN_MESSAGE => 'Send a LinkedIn message',
            CadenceStep::LINKEDIN_VIEW_PROFILE => 'View their LinkedIn profile',
            CadenceStep::LINKEDIN_ENGAGE => 'Engage with their content (like/comment)',
            default => 'Perform LinkedIn action',
        };

        $assignedTo = $step->task_assigned_to ?? $enrollment->enrolled_by ?? $enrollment->cadence->owner_id;

        try {
            if (class_exists(Task::class)) {
                Task::create([
                    'title' => "LinkedIn: {$actionDescription}",
                    'description' => ($step->content ?? $actionDescription) . ($linkedinUrl ? "\n\nProfile: {$linkedinUrl}" : ''),
                    'type' => 'linkedin',
                    'assigned_to' => $assignedTo,
                    'due_at' => now(),
                    'related_record_id' => $enrollment->record_id,
                    'related_record_type' => ModuleRecord::class,
                    'metadata' => [
                        'cadence_id' => $enrollment->cadence_id,
                        'enrollment_id' => $enrollment->id,
                        'step_id' => $step->id,
                        'linkedin_action' => $step->linkedin_action,
                        'linkedin_url' => $linkedinUrl,
                    ],
                ]);
            }

            return [
                'success' => true,
                'result' => CadenceStepExecution::RESULT_COMPLETED,
                'metadata' => [
                    'linkedin_action' => $step->linkedin_action,
                    'task_created' => true,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute a task step (creates a generic task).
     */
    protected function executeTaskStep(CadenceStep $step, CadenceEnrollment $enrollment): array
    {
        $record = ModuleRecord::find($enrollment->record_id);
        $recordData = $record?->data ?? [];

        $title = $this->personalizeContent($step->name ?? 'Cadence Task', $recordData);
        $description = $this->personalizeContent($step->content ?? '', $recordData);

        $assignedTo = $step->task_assigned_to ?? $enrollment->enrolled_by ?? $enrollment->cadence->owner_id;

        try {
            if (class_exists(Task::class)) {
                Task::create([
                    'title' => $title,
                    'description' => $description,
                    'type' => $step->task_type ?? 'follow_up',
                    'assigned_to' => $assignedTo,
                    'due_at' => now(),
                    'related_record_id' => $enrollment->record_id,
                    'related_record_type' => ModuleRecord::class,
                    'metadata' => [
                        'cadence_id' => $enrollment->cadence_id,
                        'enrollment_id' => $enrollment->id,
                        'step_id' => $step->id,
                    ],
                ]);
            }

            return [
                'success' => true,
                'result' => CadenceStepExecution::RESULT_COMPLETED,
                'metadata' => ['task_created' => true],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute a wait step (just passes, delay is handled when scheduling).
     */
    protected function executeWaitStep(CadenceStep $step, CadenceEnrollment $enrollment): array
    {
        // Wait steps don't do anything when executed
        // The delay was already applied when scheduling
        return [
            'success' => true,
            'result' => CadenceStepExecution::RESULT_COMPLETED,
            'metadata' => ['wait_completed' => true],
        ];
    }

    /**
     * Advance the enrollment to the next step.
     */
    protected function advanceEnrollment(CadenceEnrollment $enrollment, CadenceStep $completedStep): void
    {
        // Check for branching first
        $nextStep = $this->determineBranchingStep($enrollment, $completedStep);

        if (!$nextStep) {
            // Get the next sequential step
            $nextStep = $this->getNextStep($enrollment);
        }

        if ($nextStep) {
            $this->scheduleNextStep($enrollment, $nextStep);
        } else {
            // No more steps - complete the enrollment
            $enrollment->complete('All steps completed');
            $this->recordMetric($enrollment->cadence_id, 'enrollment', 'completed');
        }
    }

    /**
     * Determine if branching should occur based on engagement.
     */
    protected function determineBranchingStep(CadenceEnrollment $enrollment, CadenceStep $step): ?CadenceStep
    {
        // Check latest execution result for branching conditions
        $latestExecution = $enrollment->executions()
            ->where('step_id', $step->id)
            ->latest()
            ->first();

        if (!$latestExecution) {
            return null;
        }

        // Check for reply
        if ($latestExecution->result === CadenceStepExecution::RESULT_REPLIED && $step->on_reply_goto_step) {
            return CadenceStep::find($step->on_reply_goto_step);
        }

        // Check for click
        if ($latestExecution->result === CadenceStepExecution::RESULT_CLICKED && $step->on_click_goto_step) {
            return CadenceStep::find($step->on_click_goto_step);
        }

        // Check for no response after a period (this would typically be checked separately)
        // For now, we use this as a fallback path
        if ($step->on_no_response_goto_step) {
            // Only use no-response branch if enough time has passed (configured elsewhere)
            // This is a simplified implementation
        }

        return null;
    }

    /**
     * Get the next sequential step for an enrollment.
     */
    protected function getNextStep(CadenceEnrollment $enrollment): ?CadenceStep
    {
        $currentStepOrder = $enrollment->currentStep?->step_order ?? 0;

        return CadenceStep::where('cadence_id', $enrollment->cadence_id)
            ->where('step_order', '>', $currentStepOrder)
            ->where('is_active', true)
            ->whereNull('ab_variant_of') // Don't return A/B variants as main steps
            ->orderBy('step_order')
            ->first();
    }

    /**
     * Schedule the next step for an enrollment.
     */
    protected function scheduleNextStep(CadenceEnrollment $enrollment, CadenceStep $nextStep): void
    {
        $delay = $nextStep->getDelayInSeconds();

        // Apply preferred time if set
        $nextStepAt = now()->addSeconds($delay);

        if ($nextStep->preferred_time) {
            $preferredHour = (int) $nextStep->preferred_time->format('H');
            $preferredMinute = (int) $nextStep->preferred_time->format('i');

            // If the calculated time is before the preferred time today, use today
            // Otherwise, use the next day at preferred time
            $nextStepAt = $nextStepAt->copy()->setTime($preferredHour, $preferredMinute);

            if ($nextStepAt->isPast()) {
                $nextStepAt->addDay();
            }
        }

        $enrollment->update([
            'current_step_id' => $nextStep->id,
            'next_step_at' => $nextStepAt,
        ]);
    }

    /**
     * Select an A/B test variant.
     */
    protected function selectAbVariant(CadenceStep $step): CadenceStep
    {
        $variants = $step->abVariants()->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            return $step;
        }

        // Include the original step in the selection
        $allVariants = collect([$step])->concat($variants);

        // Calculate total percentage
        $totalPercentage = $allVariants->sum('ab_percentage');

        if ($totalPercentage <= 0) {
            // Equal distribution if no percentages set
            return $allVariants->random();
        }

        // Weighted random selection
        $random = mt_rand(1, $totalPercentage);
        $cumulative = 0;

        foreach ($allVariants as $variant) {
            $cumulative += $variant->ab_percentage ?? 0;
            if ($random <= $cumulative) {
                return $variant;
            }
        }

        return $step;
    }

    /**
     * Personalize content with record data.
     */
    protected function personalizeContent(string $content, array $recordData): string
    {
        // Replace {{field_name}} with actual values
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($recordData) {
            $field = $matches[1];
            return $recordData[$field] ?? $recordData[strtolower($field)] ?? $recordData[ucfirst($field)] ?? '';
        }, $content);
    }

    /**
     * Record a metric for the cadence.
     */
    protected function recordMetric(int $cadenceId, string $channel, string $metricType, ?int $stepId = null): void
    {
        try {
            // Map channel + metric type to actual column name
            $metricColumn = $this->getMetricColumn($channel, $metricType);
            if ($metricColumn) {
                CadenceMetric::incrementMetric($cadenceId, $metricColumn, $stepId);
            }
        } catch (\Exception $e) {
            // Don't fail the execution if metrics fail
            Log::warning('Failed to record cadence metric', [
                'cadence_id' => $cadenceId,
                'channel' => $channel,
                'metric_type' => $metricType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the metric column name based on channel and metric type.
     */
    protected function getMetricColumn(string $channel, string $metricType): ?string
    {
        $mapping = [
            'email' => [
                'executed' => 'emails_sent',
                'sent' => 'emails_sent',
                'opened' => 'emails_opened',
                'clicked' => 'emails_clicked',
                'replied' => 'replies',
                'bounced' => 'bounces',
                'unsubscribed' => 'unsubscribes',
                'failed' => null, // No column for failures
            ],
            'call' => [
                'executed' => 'calls_made',
                'connected' => 'calls_connected',
                'failed' => null,
            ],
            'sms' => [
                'executed' => 'sms_sent',
                'sent' => 'sms_sent',
                'replied' => 'sms_replied',
                'failed' => null,
            ],
            'enrollment' => [
                'completed' => 'completions',
                'enrolled' => 'enrollments',
            ],
            'linkedin' => [
                'executed' => null, // No specific column
                'failed' => null,
            ],
            'task' => [
                'executed' => null, // No specific column
                'failed' => null,
            ],
            'wait' => [
                'executed' => null,
            ],
        ];

        return $mapping[$channel][$metricType] ?? null;
    }

    /**
     * Handle a reply event for an enrollment.
     */
    public function handleReply(CadenceEnrollment $enrollment, array $metadata = []): void
    {
        // Update the latest execution
        $latestExecution = $enrollment->executions()->latest()->first();
        if ($latestExecution) {
            $latestExecution->update([
                'result' => CadenceStepExecution::RESULT_REPLIED,
                'metadata' => array_merge($latestExecution->metadata ?? [], $metadata),
            ]);
        }

        // Exit the enrollment with replied status
        $enrollment->exitWithReason(CadenceEnrollment::STATUS_REPLIED, 'Contact replied');

        // Record metric
        $this->recordMetric($enrollment->cadence_id, 'email', 'replied');
    }

    /**
     * Handle a bounce event for an enrollment.
     */
    public function handleBounce(CadenceEnrollment $enrollment, array $metadata = []): void
    {
        $latestExecution = $enrollment->executions()->latest()->first();
        if ($latestExecution) {
            $latestExecution->update([
                'result' => CadenceStepExecution::RESULT_BOUNCED,
                'metadata' => array_merge($latestExecution->metadata ?? [], $metadata),
            ]);
        }

        $enrollment->exitWithReason(CadenceEnrollment::STATUS_BOUNCED, 'Email bounced');
        $this->recordMetric($enrollment->cadence_id, 'email', 'bounced');
    }

    /**
     * Handle an unsubscribe event for an enrollment.
     */
    public function handleUnsubscribe(CadenceEnrollment $enrollment): void
    {
        $enrollment->exitWithReason(CadenceEnrollment::STATUS_UNSUBSCRIBED, 'Contact unsubscribed');
        $this->recordMetric($enrollment->cadence_id, 'email', 'unsubscribed');
    }
}
