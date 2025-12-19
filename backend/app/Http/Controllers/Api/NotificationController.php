<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Get user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'sometimes|string|in:' . implode(',', Notification::CATEGORIES),
            'unread_only' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $userId = Auth::id();
        $notifications = $this->notificationService->getNotifications(
            $userId,
            $validated['category'] ?? null,
            (bool) ($validated['unread_only'] ?? false),
            (int) ($validated['limit'] ?? 50),
            (int) ($validated['offset'] ?? 0)
        );

        $unreadCount = $this->notificationService->getUnreadCount($userId);

        return response()->json([
            'data' => $notifications,
            'meta' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'sometimes|string|in:' . implode(',', Notification::CATEGORIES),
        ]);

        $count = $this->notificationService->getUnreadCount(
            Auth::id(),
            $validated['category'] ?? null
        );

        return response()->json([
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        $success = $this->notificationService->markAsRead($id, Auth::id());

        if (!$success) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'sometimes|string|in:' . implode(',', Notification::CATEGORIES),
        ]);

        $count = $this->notificationService->markAllAsRead(
            Auth::id(),
            $validated['category'] ?? null
        );

        return response()->json([
            'message' => "{$count} notifications marked as read",
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * Archive a notification
     */
    public function archive(int $id): JsonResponse
    {
        $success = $this->notificationService->archive($id, Auth::id());

        if (!$success) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Notification archived',
        ]);
    }

    /**
     * Get notification preferences
     */
    public function getPreferences(): JsonResponse
    {
        $preferences = $this->notificationService->getPreferences(Auth::id());
        $categoryInfo = NotificationPreference::getCategoryInfo();

        // Format preferences with category info
        $formatted = $preferences->map(function ($pref, $category) use ($categoryInfo) {
            $info = $categoryInfo[$category] ?? [];
            return [
                'category' => $category,
                'label' => $info['label'] ?? ucfirst($category),
                'description' => $info['description'] ?? '',
                'icon' => $info['icon'] ?? 'bell',
                'in_app' => $pref->in_app,
                'email' => $pref->email,
                'push' => $pref->push,
                'email_frequency' => $pref->email_frequency ?? NotificationPreference::FREQUENCY_IMMEDIATE,
            ];
        })->values();

        return response()->json([
            'data' => $formatted,
            'meta' => [
                'frequencies' => [
                    ['value' => NotificationPreference::FREQUENCY_IMMEDIATE, 'label' => 'Immediately'],
                    ['value' => NotificationPreference::FREQUENCY_HOURLY, 'label' => 'Hourly digest'],
                    ['value' => NotificationPreference::FREQUENCY_DAILY, 'label' => 'Daily digest'],
                    ['value' => NotificationPreference::FREQUENCY_WEEKLY, 'label' => 'Weekly digest'],
                ],
            ],
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.category' => 'required|string|in:' . implode(',', Notification::CATEGORIES),
            'preferences.*.in_app' => 'sometimes|boolean',
            'preferences.*.email' => 'sometimes|boolean',
            'preferences.*.push' => 'sometimes|boolean',
            'preferences.*.email_frequency' => 'sometimes|string|in:' . implode(',', NotificationPreference::FREQUENCIES),
        ]);

        $prefs = collect($validated['preferences'])->keyBy('category')->map(function ($item) {
            unset($item['category']);
            return $item;
        })->toArray();

        $this->notificationService->updatePreferences(Auth::id(), $prefs);

        return response()->json([
            'message' => 'Preferences updated successfully',
        ]);
    }

    /**
     * Get notification schedule (quiet hours, DND)
     */
    public function getSchedule(): JsonResponse
    {
        $schedule = $this->notificationService->getSchedule(Auth::id());

        return response()->json([
            'data' => [
                'dnd_enabled' => $schedule->dnd_enabled,
                'quiet_hours_enabled' => $schedule->quiet_hours_enabled,
                'quiet_hours_start' => $schedule->quiet_hours_start?->format('H:i'),
                'quiet_hours_end' => $schedule->quiet_hours_end?->format('H:i'),
                'weekend_notifications' => $schedule->weekend_notifications,
                'timezone' => $schedule->timezone,
            ],
        ]);
    }

    /**
     * Update notification schedule
     */
    public function updateSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dnd_enabled' => 'sometimes|boolean',
            'quiet_hours_enabled' => 'sometimes|boolean',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
            'weekend_notifications' => 'sometimes|boolean',
            'timezone' => 'sometimes|string|timezone',
        ]);

        $schedule = $this->notificationService->updateSchedule(Auth::id(), $validated);

        return response()->json([
            'message' => 'Schedule updated successfully',
            'data' => [
                'dnd_enabled' => $schedule->dnd_enabled,
                'quiet_hours_enabled' => $schedule->quiet_hours_enabled,
                'quiet_hours_start' => $schedule->quiet_hours_start?->format('H:i'),
                'quiet_hours_end' => $schedule->quiet_hours_end?->format('H:i'),
                'weekend_notifications' => $schedule->weekend_notifications,
                'timezone' => $schedule->timezone,
            ],
        ]);
    }
}
