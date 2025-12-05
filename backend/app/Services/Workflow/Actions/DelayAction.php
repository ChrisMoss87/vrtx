<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

class DelayAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $seconds = $config['seconds'] ?? 0;
        $minutes = $config['minutes'] ?? 0;
        $hours = $config['hours'] ?? 0;
        $days = $config['days'] ?? 0;

        $totalSeconds = $seconds + ($minutes * 60) + ($hours * 3600) + ($days * 86400);

        // For now, we'll just sleep. In production, this should schedule a delayed job
        if ($totalSeconds > 0 && $totalSeconds <= 60) {
            sleep($totalSeconds);
        }

        // TODO: For longer delays, dispatch a delayed job
        // dispatch(new ContinueWorkflowJob($executionId))->delay($totalSeconds);

        return ['delayed' => true, 'seconds' => $totalSeconds];
    }

    public static function getConfigSchema(): array
    {
        return ['fields' => [
            ['name' => 'days', 'label' => 'Days', 'type' => 'number', 'default' => 0],
            ['name' => 'hours', 'label' => 'Hours', 'type' => 'number', 'default' => 0],
            ['name' => 'minutes', 'label' => 'Minutes', 'type' => 'number', 'default' => 0],
            ['name' => 'seconds', 'label' => 'Seconds', 'type' => 'number', 'default' => 0],
        ]];
    }

    public function validate(array $config): array
    {
        $total = ($config['seconds'] ?? 0) + ($config['minutes'] ?? 0) + ($config['hours'] ?? 0) + ($config['days'] ?? 0);
        return $total <= 0 ? ['delay' => 'Delay duration must be greater than 0'] : [];
    }
}
