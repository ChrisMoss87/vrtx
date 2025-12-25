<?php

declare(strict_types=1);

namespace App\Application\Services\Reporting;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardAlertService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Create a new alert for a widget
     */
    public function createAlert(int $widgetId, int $userId, array $data): DashboardWidgetAlert
    {
        return DB::table('dashboard_widget_alerts')->insertGetId([
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
    public function updateAlert(DashboardWidgetAlert $alert, array $data): DashboardWidgetAlert
    {
        $alert->update($data);

        return $alert->fresh();
    }

    /**
     * Delete an alert
     */
    public function deleteAlert(DashboardWidgetAlert $alert): void
    {
        $alert->delete();
    }

    /**
     * Toggle alert active status
     */
    public function toggleAlert(DashboardWidgetAlert $alert): DashboardWidgetAlert
    {
        $alert->update(['is_active' => ! $alert->is_active]);

        return $alert->fresh();
    }

    /**
     * Check if an alert should be triggered based on current value
     */
    public function checkAlert(DashboardWidgetAlert $alert, mixed $currentValue): bool
    {
        if (! $alert->is_active) {
            return false;
        }

        if ($alert->isInCooldown()) {
            return false;
        }

        // Get previous value if needed for percent change
        $previousValue = null;
        if ($alert->condition_type === DashboardWidgetAlert::CONDITION_PERCENT_CHANGE && $alert->comparison_period) {
            $previousValue = $this->getPreviousValue($alert);
        }

        return $alert->checkCondition((float) $currentValue, $previousValue);
    }

    /**
     * Trigger an alert and send notifications
     */
    public function triggerAlert(DashboardWidgetAlert $alert, mixed $value, array $context = []): DashboardWidgetAlertHistory
    {
        // Record the trigger
        $history = $alert->recordTrigger((float) $value, $context);

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

        $alerts = DashboardWidgetAlert::active()
            ->whereHas('widget', function ($query) use ($dashboardId) {
                $query->where('dashboard_id', $dashboardId);
            })
            ->with('widget')
            ->get();

        foreach ($alerts as $alert) {
            $widgetId = $alert->widget_id;
            $data = $widgetData[$widgetId] ?? null;

            if (! $data) {
                continue;
            }

            // Extract the value based on widget type
            $value = $this->extractWidgetValue($alert->widget, $data);

            if ($value === null) {
                continue;
            }

            try {
                if ($this->checkAlert($alert, $value)) {
                    $history = $this->triggerAlert($alert, $value, [
                        'widget_title' => $alert->widget->title,
                        'dashboard_id' => $dashboardId,
                    ]);
                    $triggeredAlerts[] = $history;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process alert', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $triggeredAlerts;
    }

    /**
     * Get previous value for comparison
     */
    protected function getPreviousValue(DashboardWidgetAlert $alert): ?float
    {
        // Get the last triggered value from history
        $lastHistory = $alert->history()
            ->where('created_at', '<', now()->subMinutes($alert->cooldown_minutes))
            ->orderByDesc('created_at')
            ->first();

        return $lastHistory?->triggered_value;
    }

    /**
     * Extract the relevant value from widget data
     */
    protected function extractWidgetValue(DashboardWidget $widget, mixed $data): ?float
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
    protected function sendAlertNotifications(DashboardWidgetAlert $alert, DashboardWidgetAlertHistory $history): void
    {
        $channels = $alert->notification_channels ?? ['in_app'];
        $user = $alert->user;
        $widget = $alert->widget;

        $title = $this->getAlertTitle($alert);
        $message = $this->getAlertMessage($alert, $history);

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'in_app':
                        $this->notificationService->create([
                            'user_id' => $user->id,
                            'type' => 'dashboard_alert',
                            'title' => $title,
                            'message' => $message,
                            'data' => [
                                'alert_id' => $alert->id,
                                'history_id' => $history->id,
                                'dashboard_id' => $widget->dashboard_id,
                                'widget_id' => $widget->id,
                                'severity' => $alert->severity,
                            ],
                        ]);
                        break;

                    case 'email':
                        // Email notification would be handled here
                        // $this->sendEmailAlert($user, $title, $message, $alert);
                        break;
                }
            } catch (\Exception $e) {
                Log::error('Failed to send alert notification', [
                    'alert_id' => $alert->id,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get alert title based on severity
     */
    protected function getAlertTitle(DashboardWidgetAlert $alert): string
    {
        $severityPrefix = match ($alert->severity) {
            'critical' => '🚨 Critical Alert: ',
            'warning' => '⚠️ Warning: ',
            default => 'ℹ️ Alert: ',
        };

        return $severityPrefix.$alert->name;
    }

    /**
     * Get alert message
     */
    protected function getAlertMessage(DashboardWidgetAlert $alert, DashboardWidgetAlertHistory $history): string
    {
        $widgetTitle = $alert->widget?->title ?? 'Widget';
        $condition = match ($alert->condition_type) {
            'above' => 'exceeded',
            'below' => 'dropped below',
            'percent_change' => 'changed by',
            'equals' => 'reached',
            default => 'triggered',
        };

        $valueDisplay = number_format((float) $history->triggered_value, 2);
        $thresholdDisplay = number_format((float) $history->threshold_value, 2);

        if ($alert->condition_type === 'percent_change') {
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
        $query = DB::table('dashboard_widget_alert_histories')
            ->whereHas('alert', function ($q) use ($userId, $dashboardId) {
                $q->where('user_id', $userId);
                if ($dashboardId) {
                    $q->whereHas('widget', function ($wq) use ($dashboardId) {
                        $wq->where('dashboard_id', $dashboardId);
                    });
                }
            })
            ->with(['alert.widget'])
            ->orderByDesc('created_at')
            ->limit($limit);

        return $query->get()->toArray();
    }

    /**
     * Acknowledge an alert history entry
     */
    public function acknowledgeAlert(DashboardWidgetAlertHistory $history, int $userId): DashboardWidgetAlertHistory
    {
        $history->acknowledge($userId);

        return $history->fresh();
    }

    /**
     * Dismiss an alert history entry
     */
    public function dismissAlert(DashboardWidgetAlertHistory $history, int $userId): DashboardWidgetAlertHistory
    {
        $history->dismiss($userId);

        return $history->fresh();
    }

    /**
     * Get unacknowledged alert count for user
     */
    public function getUnacknowledgedCount(int $userId): int
    {
        return DashboardWidgetAlertHistory::unacknowledged()
            ->whereHas('alert', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->count();
    }
}
