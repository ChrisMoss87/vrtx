import { apiClient } from './client';

// Types
export interface Activity {
	id: number;
	user_id: number | null;
	type: ActivityType;
	action: string;
	subject_type: string;
	subject_id: number;
	related_type: string | null;
	related_id: number | null;
	title: string;
	description: string | null;
	metadata: Record<string, unknown> | null;
	content: string | null;
	is_pinned: boolean;
	scheduled_at: string | null;
	completed_at: string | null;
	duration_minutes: number | null;
	outcome: string | null;
	is_internal: boolean;
	is_system: boolean;
	created_at: string;
	updated_at: string;
	user?: { id: number; name: string; email: string };
	icon?: string;
	color?: string;
}

export type ActivityType =
	| 'note'
	| 'call'
	| 'meeting'
	| 'task'
	| 'email'
	| 'status_change'
	| 'field_update'
	| 'comment'
	| 'attachment'
	| 'created'
	| 'deleted';

export interface AuditLog {
	id: number;
	user_id: number | null;
	auditable_type: string;
	auditable_id: number;
	event: AuditEvent;
	event_description: string;
	old_values: Record<string, unknown> | null;
	new_values: Record<string, unknown> | null;
	changed_fields: string[];
	ip_address: string | null;
	user_agent: string | null;
	url: string | null;
	tags: string[] | null;
	batch_id: string | null;
	created_at: string;
	user?: { id: number; name: string; email: string };
	diff?: Record<string, { old: unknown; new: unknown }>;
}

export type AuditEvent =
	| 'created'
	| 'updated'
	| 'deleted'
	| 'restored'
	| 'force_deleted'
	| 'attached'
	| 'detached'
	| 'synced'
	| 'login'
	| 'logout'
	| 'failed_login';

export interface CreateActivityData {
	type: 'note' | 'call' | 'meeting' | 'task' | 'comment';
	subject_type: string;
	subject_id: number;
	title: string;
	description?: string;
	content?: string;
	scheduled_at?: string;
	duration_minutes?: number;
	outcome?: string;
	is_internal?: boolean;
	is_pinned?: boolean;
	metadata?: Record<string, unknown>;
}

export interface ActivityFilters {
	subject_type?: string;
	subject_id?: number;
	type?: ActivityType;
	user_id?: number;
	include_system?: boolean;
	scheduled_only?: boolean;
	overdue_only?: boolean;
	per_page?: number;
}

export interface AuditLogFilters {
	auditable_type?: string;
	auditable_id?: number;
	event?: AuditEvent;
	user_id?: number;
	start_date?: string;
	end_date?: string;
	tags?: string[];
	per_page?: number;
}

// Helper to convert filters to string params
function toStringParams(obj?: Record<string, unknown>): Record<string, string> | undefined {
	if (!obj) return undefined;
	const result: Record<string, string> = {};
	for (const [key, value] of Object.entries(obj)) {
		if (value !== undefined && value !== null) {
			result[key] = String(value);
		}
	}
	return Object.keys(result).length > 0 ? result : undefined;
}

// Activities API
export const activitiesApi = {
	list: async (filters?: ActivityFilters) => {
		const response = await apiClient.get<{
			data: Activity[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>('/activities', toStringParams(filters as Record<string, unknown>));
		return response;
	},

	get: async (id: number) => {
		const response = await apiClient.get<{ data: Activity }>(`/activities/${id}`);
		return response.data;
	},

	create: async (data: CreateActivityData) => {
		const response = await apiClient.post<{ data: Activity; message: string }>(
			'/activities',
			data
		);
		return response;
	},

	update: async (id: number, data: Partial<CreateActivityData>) => {
		const response = await apiClient.put<{ data: Activity; message: string }>(
			`/activities/${id}`,
			data
		);
		return response;
	},

	delete: async (id: number) => {
		const response = await apiClient.delete<{ message: string }>(`/activities/${id}`);
		return response;
	},

	complete: async (id: number, outcome?: string, durationMinutes?: number) => {
		const response = await apiClient.post<{ data: Activity; message: string }>(
			`/activities/${id}/complete`,
			{ outcome, duration_minutes: durationMinutes }
		);
		return response;
	},

	togglePin: async (id: number) => {
		const response = await apiClient.post<{ data: Activity; message: string }>(
			`/activities/${id}/toggle-pin`
		);
		return response;
	},

	getTimeline: async (subjectType: string, subjectId: number, options?: {
		type?: ActivityType;
		include_system?: boolean;
		limit?: number;
	}) => {
		const params = toStringParams({
			subject_type: subjectType,
			subject_id: subjectId,
			...options
		});
		const response = await apiClient.get<{ data: Activity[] }>('/activities/timeline', params);
		return response.data;
	},

	getUpcoming: async (days?: number, userId?: number) => {
		const params = toStringParams({ days, user_id: userId });
		const response = await apiClient.get<{ data: Activity[] }>('/activities/upcoming', params);
		return response.data;
	},

	getOverdue: async (userId?: number) => {
		const params = toStringParams({ user_id: userId });
		const response = await apiClient.get<{ data: Activity[] }>('/activities/overdue', params);
		return response.data;
	},

	getTypes: async () => {
		const response = await apiClient.get<{ data: Record<string, string> }>('/activities/types');
		return response.data;
	},

	getOutcomes: async () => {
		const response = await apiClient.get<{ data: Record<string, string> }>('/activities/outcomes');
		return response.data;
	}
};

// Audit Logs API
export const auditLogsApi = {
	list: async (filters?: AuditLogFilters) => {
		const response = await apiClient.get<{
			data: AuditLog[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>('/audit-logs', toStringParams(filters as Record<string, unknown>));
		return response;
	},

	get: async (id: number) => {
		const response = await apiClient.get<{ data: AuditLog }>(`/audit-logs/${id}`);
		return response.data;
	},

	getForRecord: async (auditableType: string, auditableId: number, limit?: number) => {
		const params = toStringParams({
			auditable_type: auditableType,
			auditable_id: auditableId,
			limit
		});
		const response = await apiClient.get<{ data: AuditLog[] }>('/audit-logs/for-record', params);
		return response.data;
	},

	getSummary: async (auditableType: string, auditableId: number) => {
		const params = toStringParams({
			auditable_type: auditableType,
			auditable_id: auditableId
		});
		const response = await apiClient.get<{
			data: {
				total_changes: number;
				event_counts: Record<string, number>;
				unique_users: number;
				first_change_at: string | null;
				last_change_at: string | null;
				last_change_by: { id: number; name: string; email: string } | null;
			};
		}>('/audit-logs/summary', params);
		return response.data;
	},

	getForUser: async (userId: number, options?: {
		start_date?: string;
		end_date?: string;
		per_page?: number;
	}) => {
		const response = await apiClient.get<{
			data: AuditLog[];
			meta: { current_page: number; last_page: number; per_page: number; total: number };
		}>(`/audit-logs/user/${userId}`, toStringParams(options as Record<string, unknown>));
		return response;
	},

	compare: async (log1Id: number, log2Id: number) => {
		const response = await apiClient.get<{
			data: {
				log1: AuditLog;
				log2: AuditLog;
				time_diff: string;
			};
		}>(`/audit-logs/compare/${log1Id}/${log2Id}`);
		return response.data;
	}
};

// Helper functions
export function getActivityIcon(type: ActivityType): string {
	const icons: Record<ActivityType, string> = {
		note: 'sticky-note',
		call: 'phone',
		meeting: 'calendar',
		task: 'check-square',
		email: 'mail',
		status_change: 'git-branch',
		field_update: 'edit',
		comment: 'message-circle',
		attachment: 'paperclip',
		created: 'plus-circle',
		deleted: 'trash'
	};
	return icons[type] ?? 'activity';
}

export function getActivityColor(type: ActivityType): string {
	const colors: Record<ActivityType, string> = {
		note: 'yellow',
		call: 'blue',
		meeting: 'purple',
		task: 'green',
		email: 'cyan',
		status_change: 'orange',
		field_update: 'gray',
		comment: 'pink',
		attachment: 'indigo',
		created: 'green',
		deleted: 'red'
	};
	return colors[type] ?? 'gray';
}

export function getEventLabel(event: AuditEvent): string {
	const labels: Record<AuditEvent, string> = {
		created: 'Created',
		updated: 'Updated',
		deleted: 'Deleted',
		restored: 'Restored',
		force_deleted: 'Permanently Deleted',
		attached: 'Attached',
		detached: 'Detached',
		synced: 'Synced',
		login: 'Logged In',
		logout: 'Logged Out',
		failed_login: 'Failed Login'
	};
	return labels[event] ?? event;
}
