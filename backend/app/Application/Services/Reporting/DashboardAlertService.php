<?php

declare(strict_types=1);

namespace App\Application\Services\Reporting;

use App\Domain\Reporting\Repositories\DashboardWidgetAlertRepositoryInterface;
use App\Domain\Reporting\Repositories\DashboardWidgetAlertHistoryRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class DashboardAlertService
{
    // Condition types
    public const CONDITION_ABOVE = 'above';
    public const CONDITION_BELOW = 'below';
    public const CONDITION_PERCENT_CHANGE = 'percent_change';
    public const CONDITION_EQUALS = 'equals';

    public function __construct(
        private readonly DashboardWidgetAlertRepositoryInterface $alertRepository,
        private readonly DashboardWidgetAlertHistoryRepositoryInterface $historyRepository,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Create a new alert for a widget
     */
    public function createAlert(int $widgetId, int $userId, array $data): array
    {
        return $this->alertRepository->create([
            'widget_id' => $widgetId,
            'user_id' => $userId,
            'name' => $data['name'],
            'condition_type' => $data['condition_type'],
            'threshold_value' => $data['threshold_value'],
            'comparison_period' => $data['comparison_period'] ?? null,
            'severity' => $data['severity'] ?? 'warning',
            'notification_channels' => $data['notification_channels'] ?? ['in_app'],
            'cooldown_minutes' => $data['cooldown_minutes'] ?? 60,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing alert
     */
    public function updateAlert(int $alertId, array $data): ?array
    {
        return $this->alertRepository->update($alertId, $data);
    }

    /**
     * Delete an alert
     */
    public function deleteAlert(int $alertId): bool
    {
        return $this->alertRepository->delete($alertId);
    }

    /**
     * Toggle alert active status
     */
    public function toggleAlert(int $alertId): ?array
    {
        return $this->alertRepository->toggleActive($alertId);
    }

    /**
     * Check if an alert should be triggered based on current value
     */
    public function checkAlert(int $alertId, mixed $currentValue): bool
    {
        $alert = $this->alertRepository->findById($alertId);

        if (!$alert || !$alert['is_active']) {
            return false;
        }

        if ($this->alertRepository->isInCooldown($alertId)) {
            return false;
        }

        // Get previous value if needed for percent change
        $previousValue = null;
        if ($alert['condition_type'] === self::CONDITION_PERCENT_CHANGE && ($alert['comparison_period'] ?? null)) {
            $previousValue = $this->getPreviousValue($alertId, $alert['cooldown_minutes'] ?? 60);
        }

        return $this->evaluateCondition(
            $alert['condition_type'],
            (float) $currentValue,
            (float) $alert['threshold_value'],
            $previousValue
        );
    }

    /**
     * Trigger an alert and send notifications
     */
    public function triggerAlert(int $alertId, mixed $value, array $context = []): array
    {
        $alert = $this->alertRepository->findById($alertId);
        if (!$alert) {
            throw new \InvalidArgumentException("Alert not found: {$alertId}");
        }

        // Record the trigger
        $history = $this->historyRepository->create([
            'alert_id' => $alertId,
            'triggered_value' => (float) $value,
            'threshold_value' => (float) $alert['threshold_value'],
            'context' => $context,
        ]);

        // Send notifications
        $this->sendAlertNotifications($alert, $history);

        return $history;
    }

    /**
     * Process all active alerts for a dashboard
     */
    public function processAlertsForDashboard(int $dashboardId, array $widgetData): array
    {
        $triggeredAlerts = [];

        $alerts = $this->alertRepository->findActiveByDashboardId($dashboardId);

        foreach ($alerts as $alert) {
            $alertWidgetId = $alert['widget_id'];
            $data = $widgetData[$alertWidgetId] ?? null;

            if (!$data) {
                continue;
            }

            // Extract the value based on widget type
            $widget = $this->alertRepository->getWidget($alert['id']);
            $value = $this->extractWidgetValue($widget, $data);

            if ($value === null) {
                continue;
            }

            try {
                if ($this->checkAlert($alert['id'], $value)) {
                    $history = $this->triggerAlert($alert['id'], $value, [
                        'widget_title' => $widget['title'] ?? 'Widget',
                        'dashboard_id' => $dashboardId,
                    ]);
                    $triggeredAlerts[] = $history;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process alert', [
                    'alert_id' => $alert['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $triggeredAlerts;
    }

    /**
     * Get previous value for comparison
     */
    protected function getPreviousValue(int $alertId, int $cooldownMinutes): ?float
    {
        return $this->historyRepository->getLastTriggeredValue($alertId, $cooldownMinutes);
    }

    /**
     * Evaluate if a condition is met
     */
    protected function evaluateCondition(
        string $conditionType,
        float $currentValue,
        float $threshold,
        ?float $previousValue = null
    ): bool {
        return match ($conditionType) {
            self::CONDITION_ABOVE => $currentValue > $threshold,
            self::CONDITION_BELOW => $currentValue < $threshold,
            self::CONDITION_EQUALS => abs($currentValue - $threshold) < 0.0001,
            self::CONDITION_PERCENT_CHANGE => $previousValue !== null && $previousValue > 0
                ? abs(($currentValue - $previousValue) / $previousValue * 100) >= $threshold
                : false,
            default => false,
        };
    }

    /**
     * Extract the relevant value from widget data
     */
    protected function extractWidgetValue(?array $widget, mixed $data): ?float
    {
        if (is_numeric($data)) {
            return (float) $data;
        }

        if (is_array($data)) {
            // KPI widget
            if (isset($data['value'])) {
                return (float) $data['value'];
            }

            // Goal KPI widget
            if (isset($data['current'])) {
                return (float) $data['current'];
            }

            // Progress widget
            if (isset($data['progress'])) {
                return (float) $data['progress'];
            }
        }

        return null;
    }

    /**
     * Send notifications for a triggered alert
     */
    protected function sendAlertNotifications(array $alert, array $history): void
    {
        $channels = $alert['notification_channels'] ?? ['in_app'];
        $user = $this->alertRepository->getUser($alert['id']);
        $widget = $this->alertRepository->getWidget($alert['id']);

        if (!$user) {
            return;
        }

        $title = $this->getAlertTitle($alert);
        $message = $this->getAlertMessage($alert, $history, $widget);

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'in_app':
                        $this->notificationService->create([
                            'user_id' => $user['id'],
                            'type' => 'dashboard_alert',
                            'title' => $title,
                            'message' => $message,
                            'data' => [
                                'alert_id' => $alert['id'],
                                'history_id' => $history['id'],
                                'dashboard_id' => $widget['dashboard_id'] ?? null,
                                'widget_id' => $widget['id'] ?? null,
                                'severity' => $alert['severity'] ?? 'warning',
                            ],
                        ]);
                        break;

                    case 'email':
                        // Email notification would be handled here
                        break;
                }
            } catch (\Exception $e) {
                Log::error('Failed to send alert notification', [
                    'alert_id' => $alert['id'],
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get alert title based on severity
     */
    protected function getAlertTitle(array $alert): string
    {
        $severityPrefix = match ($alert['severity'] ?? 'info') {
            'critical' => 'Critical Alert: ',
            'warning' => 'Warning: ',
            default => 'Alert: ',
        };

        return $severityPrefix . ($alert['name'] ?? 'Dashboard Alert');
    }

    /**
     * Get alert message
     */
    protected function getAlertMessage(array $alert, array $history, ?array $widget): string
    {
        $widgetTitle = $widget['title'] ?? 'Widget';
        $condition = match ($alert['condition_type'] ?? '') {
            self::CONDITION_ABOVE => 'exceeded',
            self::CONDITION_BELOW => 'dropped below',
            self::CONDITION_PERCENT_CHANGE => 'changed by',
            self::CONDITION_EQUALS => 'reached',
            default => 'triggered',
        };

        $valueDisplay = number_format((float) ($history['triggered_value'] ?? 0), 2);
        $thresholdDisplay = number_format((float) ($history['threshold_value'] ?? 0), 2);

        if (($alert['condition_type'] ?? '') === self::CONDITION_PERCENT_CHANGE) {
            return sprintf(
                '%s: Value %s %s%% (threshold: %s%%)',
                $widgetTitle,
                $condition,
                $valueDisplay,
                $thresholdDisplay
            );
        }

        return sprintf(
            '%s: Value %s threshold of %s (current: %s)',
            $widgetTitle,
            $condition,
            $thresholdDisplay,
            $valueDisplay
        );
    }

    /**
     * Get alert history for a user
     */
    public function getAlertHistory(int $userId, ?int $dashboardId = null, int $limit = 50): array
    {
        return $this->historyRepository->findByUserId($userId, $dashboardId, $limit);
    }

    /**
     * Acknowledge an alert history entry
     */
    public function acknowledgeAlert(int $historyId, int $userId): ?array
    {
        $this->historyRepository->acknowledge($historyId, $userId);

        return $this->historyRepository->findById($historyId);
    }

    /**
     * Dismiss an alert history entry
     */
    public function dismissAlert(int $historyId, int $userId): ?array
    {
        $this->historyRepository->dismiss($historyId, $userId);

        return $this->historyRepository->findById($historyId);
    }

    /**
     * Get unacknowledged alert count for user
     */
    public function getUnacknowledgedCount(int $userId): int
    {
        return $this->historyRepository->getUnacknowledgedCount($userId);
    }
}
