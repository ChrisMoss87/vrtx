<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\DTOs\CreateWorkflowDTO;
use App\Domain\Workflow\DTOs\CreateWorkflowStepDTO;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\TriggerType;

/**
 * Domain service for validating workflow configurations.
 */
class WorkflowValidationService
{
    /**
     * Validation errors from the last validation.
     *
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Validate a workflow creation request.
     *
     * @return bool True if valid
     */
    public function validateCreate(CreateWorkflowDTO $dto): bool
    {
        $this->errors = [];

        $this->validateBasicInfo($dto->name, $dto->description);
        $this->validateTriggerConfiguration($dto);
        $this->validateExecutionSettings($dto);
        $this->validateSteps($dto->steps);

        return empty($this->errors);
    }

    /**
     * Get validation errors from the last validation.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Validate basic workflow information.
     */
    private function validateBasicInfo(string $name, ?string $description): void
    {
        if (empty(trim($name))) {
            $this->errors[] = 'Workflow name is required.';
        }

        if (strlen($name) > 255) {
            $this->errors[] = 'Workflow name cannot exceed 255 characters.';
        }

        if ($description !== null && strlen($description) > 5000) {
            $this->errors[] = 'Workflow description cannot exceed 5000 characters.';
        }
    }

    /**
     * Validate trigger configuration.
     */
    private function validateTriggerConfiguration(CreateWorkflowDTO $dto): void
    {
        $triggerType = $dto->triggerType;

        // Validate field_changed trigger has watched fields
        if ($triggerType === TriggerType::FIELD_CHANGED) {
            if (empty($dto->watchedFields) && empty($dto->triggerConfig->fields())) {
                $this->errors[] = 'Field change trigger requires at least one watched field.';
            }
        }

        // Validate time_based trigger has cron or date field
        if ($triggerType === TriggerType::TIME_BASED) {
            $config = $dto->triggerConfig;
            if (empty($dto->scheduleCron) && empty($config->dateField())) {
                $this->errors[] = 'Schedule trigger requires either a cron expression or a date field.';
            }

            if (!empty($dto->scheduleCron) && !$this->isValidCron($dto->scheduleCron)) {
                $this->errors[] = 'Invalid cron expression format.';
            }
        }

        // Validate webhook trigger (could have webhook secret requirement)
        if ($triggerType === TriggerType::WEBHOOK) {
            // Webhook validation is optional since secret can be generated
        }

        // Validate related triggers have related module specified
        if (in_array($triggerType, [TriggerType::RELATED_CREATED, TriggerType::RELATED_UPDATED])) {
            if (empty($dto->triggerConfig->relatedModule())) {
                $this->errors[] = 'Related record trigger requires a related module to be specified.';
            }
        }
    }

    /**
     * Validate execution settings.
     */
    private function validateExecutionSettings(CreateWorkflowDTO $dto): void
    {
        if ($dto->delaySeconds < 0) {
            $this->errors[] = 'Delay seconds cannot be negative.';
        }

        if ($dto->maxExecutionsPerDay !== null && $dto->maxExecutionsPerDay < 1) {
            $this->errors[] = 'Max executions per day must be at least 1.';
        }

        if ($dto->priority < -100 || $dto->priority > 100) {
            $this->errors[] = 'Priority must be between -100 and 100.';
        }
    }

    /**
     * Validate workflow steps.
     *
     * @param array<CreateWorkflowStepDTO> $steps
     */
    private function validateSteps(array $steps): void
    {
        if (empty($steps)) {
            $this->errors[] = 'Workflow must have at least one step.';
            return;
        }

        $orders = [];
        foreach ($steps as $index => $step) {
            $this->validateStep($step, $index);
            $orders[] = $step->order;
        }

        // Check for duplicate orders
        if (count($orders) !== count(array_unique($orders))) {
            $this->errors[] = 'Workflow steps cannot have duplicate order values.';
        }
    }

    /**
     * Validate a single workflow step.
     */
    private function validateStep(CreateWorkflowStepDTO $step, int $index): void
    {
        $prefix = "Step " . ($index + 1);

        // Validate action-specific configuration
        $this->validateActionConfig($step, $prefix);

        // Validate retry settings
        if ($step->retryCount < 0 || $step->retryCount > 10) {
            $this->errors[] = "{$prefix}: Retry count must be between 0 and 10.";
        }

        if ($step->retryDelaySeconds < 0) {
            $this->errors[] = "{$prefix}: Retry delay cannot be negative.";
        }
    }

    /**
     * Validate action-specific configuration.
     */
    private function validateActionConfig(CreateWorkflowStepDTO $step, string $prefix): void
    {
        $config = $step->actionConfig;

        match ($step->actionType) {
            ActionType::SEND_EMAIL => $this->validateEmailAction($config, $prefix),
            ActionType::WEBHOOK => $this->validateWebhookAction($config, $prefix),
            ActionType::UPDATE_FIELD => $this->validateUpdateFieldAction($config, $prefix),
            ActionType::DELAY => $this->validateDelayAction($config, $prefix),
            ActionType::CONDITION => $this->validateConditionAction($config, $prefix),
            ActionType::CREATE_RECORD => $this->validateCreateRecordAction($config, $prefix),
            ActionType::MOVE_STAGE => $this->validateMoveStageAction($config, $prefix),
            ActionType::ASSIGN_USER => $this->validateAssignUserAction($config, $prefix),
            default => null, // Other actions have minimal requirements
        };
    }

    /**
     * Validate email action configuration.
     */
    private function validateEmailAction($config, string $prefix): void
    {
        if (empty($config->get('template_id')) && empty($config->get('subject'))) {
            $this->errors[] = "{$prefix}: Email action requires either a template or a subject.";
        }

        if (empty($config->get('to')) && empty($config->get('to_field'))) {
            $this->errors[] = "{$prefix}: Email action requires a recipient.";
        }
    }

    /**
     * Validate webhook action configuration.
     */
    private function validateWebhookAction($config, string $prefix): void
    {
        $url = $config->getString('url');
        if (empty($url)) {
            $this->errors[] = "{$prefix}: Webhook action requires a URL.";
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->errors[] = "{$prefix}: Webhook URL is not valid.";
        }

        $method = $config->getString('method', 'POST');
        if (!in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->errors[] = "{$prefix}: Invalid HTTP method for webhook.";
        }
    }

    /**
     * Validate update field action configuration.
     */
    private function validateUpdateFieldAction($config, string $prefix): void
    {
        if (empty($config->get('field'))) {
            $this->errors[] = "{$prefix}: Update field action requires a field to update.";
        }

        if (!$config->has('value') && empty($config->get('value_field'))) {
            $this->errors[] = "{$prefix}: Update field action requires a value.";
        }
    }

    /**
     * Validate delay action configuration.
     */
    private function validateDelayAction($config, string $prefix): void
    {
        $delayType = $config->getString('delay_type', 'fixed');

        if ($delayType === 'fixed') {
            $duration = $config->getInt('duration');
            if ($duration < 1) {
                $this->errors[] = "{$prefix}: Delay action requires a positive duration.";
            }
        } elseif ($delayType === 'until_date') {
            if (empty($config->get('date_field'))) {
                $this->errors[] = "{$prefix}: Date-based delay requires a date field.";
            }
        }
    }

    /**
     * Validate condition action configuration.
     */
    private function validateConditionAction($config, string $prefix): void
    {
        $conditions = $config->getArray('conditions');
        if (empty($conditions)) {
            $this->errors[] = "{$prefix}: Condition action requires at least one condition.";
        }
    }

    /**
     * Validate create record action configuration.
     */
    private function validateCreateRecordAction($config, string $prefix): void
    {
        if (empty($config->get('module_id'))) {
            $this->errors[] = "{$prefix}: Create record action requires a target module.";
        }
    }

    /**
     * Validate move stage action configuration.
     */
    private function validateMoveStageAction($config, string $prefix): void
    {
        if (empty($config->get('stage_id')) && empty($config->get('stage_name'))) {
            $this->errors[] = "{$prefix}: Move stage action requires a target stage.";
        }
    }

    /**
     * Validate assign user action configuration.
     */
    private function validateAssignUserAction($config, string $prefix): void
    {
        $assignmentType = $config->getString('assignment_type', 'specific');

        if ($assignmentType === 'specific' && empty($config->get('user_id'))) {
            $this->errors[] = "{$prefix}: Assign user action requires a user when using specific assignment.";
        }

        if ($assignmentType === 'round_robin' && empty($config->getArray('user_pool'))) {
            $this->errors[] = "{$prefix}: Round robin assignment requires a user pool.";
        }
    }

    /**
     * Validate a cron expression format.
     */
    private function isValidCron(string $cron): bool
    {
        // Basic cron format validation (5 or 6 fields)
        $parts = preg_split('/\s+/', trim($cron));
        return count($parts) >= 5 && count($parts) <= 6;
    }
}
