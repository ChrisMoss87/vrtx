<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

/**
 * Interface for workflow action implementations.
 */
interface ActionInterface
{
    /**
     * Execute the action.
     *
     * @param array $config The action configuration
     * @param array $context The execution context (record data, step outputs, etc.)
     * @return array The action output data
     * @throws \Exception If the action fails
     */
    public function execute(array $config, array $context): array;

    /**
     * Get the configuration schema for this action.
     *
     * @return array Configuration schema for frontend form building
     */
    public static function getConfigSchema(): array;

    /**
     * Validate the configuration.
     *
     * @param array $config The configuration to validate
     * @return array Validation errors (empty if valid)
     */
    public function validate(array $config): array;
}
