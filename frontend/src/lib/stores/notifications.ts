import { writable, derived, get } from 'svelte/store';
import { browser } from '$app/environment';
import {
	notificationsApi,
	type Notification,
	type NotificationCategory,
	type NotificationPreference,
	type NotificationSchedule,
	type EmailFrequency
} from '$lib/api/notifications';

interface NotificationsState {
	notifications: Notification[];
	unreadCount: number;
	loading: boolean;
	hasMore: boolean;
	preferences: NotificationPreference[];
	schedule: NotificationSchedule | null;
	preferencesLoading: boolean;
}

const initialState: NotificationsState = {
	notifications: [],
	unreadCount: 0,
	loading: false,
	hasMore: true,
	preferences: [],
	schedule: null,
	preferencesLoading: false
};

function createNotificationsStore() {
	const { subscribe, set, update } = writable<NotificationsState>(initialState);

	let echoChannel: unknown = null;

	return {
		subscribe,

		/**
		 * Load notifications from API
		 */
		async load(reset = false) {
			update((state) => ({ ...state, loading: true }));

			try {
				const offset = reset ? 0 : get({ subscribe }).notifications.length;
				const response = await notificationsApi.list({ limit: 20, offset });

				update((state) => ({
					...state,
					notifications: reset
						? response.data
						: [...state.notifications, ...response.data],
					unreadCount: response.meta.unread_count,
					hasMore: response.data.length === 20,
					loading: false
				}));
			} catch (error) {
				console.error('Failed to load notifications:', error);
				update((state) => ({ ...state, loading: false }));
			}
		},

		/**
		 * Load more notifications (pagination)
		 */
		async loadMore() {
			const state = get({ subscribe });
			if (state.loading || !state.hasMore) return;
			await this.load(false);
		},

		/**
		 * Refresh unread count only
		 */
		async refreshUnreadCount() {
			try {
				const response = await notificationsApi.getUnreadCount();
				update((state) => ({ ...state, unreadCount: response.data.count }));
			} catch (error) {
				console.error('Failed to refresh unread count:', error);
			}
		},

		/**
		 * Mark a notification as read
		 */
		async markAsRead(id: number) {
			try {
				await notificationsApi.markAsRead(id);
				update((state) => ({
					...state,
					notifications: state.notifications.map((n) =>
						n.id === id ? { ...n, read_at: new Date().toISOString() } : n
					),
					unreadCount: Math.max(0, state.unreadCount - 1)
				}));
			} catch (error) {
				console.error('Failed to mark notification as read:', error);
			}
		},

		/**
		 * Mark all notifications as read
		 */
		async markAllAsRead(category?: NotificationCategory) {
			try {
				await notificationsApi.markAllAsRead(category);
				update((state) => ({
					...state,
					notifications: state.notifications.map((n) =>
						!category || n.category === category
							? { ...n, read_at: new Date().toISOString() }
							: n
					),
					unreadCount: category ? state.unreadCount : 0
				}));
			} catch (error) {
				console.error('Failed to mark all as read:', error);
			}
		},

		/**
		 * Archive a notification
		 */
		async archive(id: number) {
			try {
				await notificationsApi.archive(id);
				update((state) => ({
					...state,
					notifications: state.notifications.filter((n) => n.id !== id)
				}));
			} catch (error) {
				console.error('Failed to archive notification:', error);
			}
		},

		/**
		 * Add a new notification (from real-time)
		 */
		addNotification(notification: Notification) {
			update((state) => ({
				...state,
				notifications: [notification, ...state.notifications],
				unreadCount: state.unreadCount + 1
			}));

			// Play notification sound if enabled
			if (browser) {
				this.playNotificationSound();
				this.showDesktopNotification(notification);
			}
		},

		/**
		 * Play notification sound
		 */
		playNotificationSound() {
			// Check if sounds are enabled in preferences
			const soundsEnabled = localStorage.getItem('vrtx_preferences');
			if (soundsEnabled) {
				try {
					const prefs = JSON.parse(soundsEnabled);
					if (prefs.notification_sounds === false) return;
				} catch {
					// Ignore parse errors
				}
			}

			// Play a subtle notification sound
			try {
				const audio = new Audio('/sounds/notification.mp3');
				audio.volume = 0.3;
				audio.play().catch(() => {
					// Ignore autoplay errors
				});
			} catch {
				// Ignore errors
			}
		},

		/**
		 * Show desktop notification
		 */
		async showDesktopNotification(notification: Notification) {
			// Check if desktop notifications are enabled
			const prefsStr = localStorage.getItem('vrtx_preferences');
			if (prefsStr) {
				try {
					const prefs = JSON.parse(prefsStr);
					if (prefs.desktop_notifications === false) return;
				} catch {
					// Ignore parse errors
				}
			}

			// Request permission if needed
			if (Notification.permission === 'default') {
				await Notification.requestPermission();
			}

			if (Notification.permission === 'granted') {
				new Notification(notification.title, {
					body: notification.body || undefined,
					icon: '/favicon.png',
					tag: `notification-${notification.id}`
				});
			}
		},

		/**
		 * Connect to real-time channel
		 */
		connectRealtime(userId: number) {
			if (!browser) return;

			// Check if Laravel Echo is available
			const win = window as unknown as { Echo?: { private: (channel: string) => unknown } };
			if (!win.Echo) {
				console.warn('Laravel Echo not initialized - real-time notifications disabled');
				return;
			}

			// Subscribe to user's notification channel
			echoChannel = win.Echo.private(`notifications.${userId}`);

			const channel = echoChannel as { listen: (event: string, callback: (data: Notification) => void) => void };
			channel.listen('.notification.created', (notification: Notification) => {
				this.addNotification(notification);
			});
		},

		/**
		 * Disconnect from real-time channel
		 */
		disconnectRealtime(userId: number) {
			if (!browser) return;

			const win = window as unknown as { Echo?: { leave: (channel: string) => void } };
			if (win.Echo && echoChannel) {
				win.Echo.leave(`notifications.${userId}`);
				echoChannel = null;
			}
		},

		/**
		 * Load notification preferences
		 */
		async loadPreferences() {
			update((state) => ({ ...state, preferencesLoading: true }));

			try {
				const [prefsResponse, scheduleResponse] = await Promise.all([
					notificationsApi.getPreferences(),
					notificationsApi.getSchedule()
				]);

				update((state) => ({
					...state,
					preferences: prefsResponse.data,
					schedule: scheduleResponse.data,
					preferencesLoading: false
				}));
			} catch (error) {
				console.error('Failed to load notification preferences:', error);
				update((state) => ({ ...state, preferencesLoading: false }));
			}
		},

		/**
		 * Update a single preference
		 */
		async updatePreference(
			category: NotificationCategory,
			updates: { in_app?: boolean; email?: boolean; push?: boolean; email_frequency?: EmailFrequency }
		) {
			try {
				await notificationsApi.updatePreferences([{ category, ...updates }]);

				update((state) => ({
					...state,
					preferences: state.preferences.map((p) =>
						p.category === category ? { ...p, ...updates } : p
					)
				}));
			} catch (error) {
				console.error('Failed to update preference:', error);
				throw error;
			}
		},

		/**
		 * Update notification schedule
		 */
		async updateSchedule(scheduleUpdates: Partial<NotificationSchedule>) {
			try {
				const response = await notificationsApi.updateSchedule(scheduleUpdates);

				update((state) => ({
					...state,
					schedule: response.data
				}));
			} catch (error) {
				console.error('Failed to update schedule:', error);
				throw error;
			}
		},

		/**
		 * Reset store
		 */
		reset() {
			set(initialState);
		}
	};
}

export const notifications = createNotificationsStore();

// Derived stores for convenience
export const unreadCount = derived(notifications, ($n) => $n.unreadCount);
export const notificationsList = derived(notifications, ($n) => $n.notifications);
export const notificationsLoading = derived(notifications, ($n) => $n.loading);
export const notificationPreferences = derived(notifications, ($n) => $n.preferences);
export const notificationSchedule = derived(notifications, ($n) => $n.schedule);
