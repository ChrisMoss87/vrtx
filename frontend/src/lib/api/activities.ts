import { apiClient } from './client';

export interface Activity {
	id: number;
	user_id: number;
	type: ActivityType;
	action: ActivityAction;
	subject_type: string;
	subject_id: number;
	related_type: string | null;
	related_id: number | null;
	title: string;
	description: string | null;
	content: string | null;
	metadata: Record<string, unknown> | null;
	is_pinned: boolean;
	is_internal: boolean;
	is_system: boolean;
	scheduled_at: string | null;
	completed_at: string | null;
	duration_minutes: number | null;
	outcome: ActivityOutcome | null;
	created_at: string;
	updated_at: string;
	user?: {
		id: number;
		name: string;
		email: string;
	};
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

export type ActivityAction =
	| 'created'
	| 'updated'
	| 'deleted'
	| 'completed'
	| 'sent'
	| 'received'
	| 'scheduled'
	| 'cancelled';

export type ActivityOutcome =
	| 'completed'
	| 'no_answer'
	| 'left_voicemail'
	| 'busy'
	| 'wrong_number'
	| 'rescheduled'
	| 'cancelled';

export interface ActivityListParams {
	subject_type?: string;
	subject_id?: number;
	type?: ActivityType;
	user_id?: number;
	include_system?: boolean;
	scheduled_only?: boolean;
	overdue_only?: boolean;
	per_page?: number;
	page?: number;
}

export interface ActivityListResponse {
	data: Activity[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

export interface CreateActivityData {
	type: 'note' | 'call' | 'meeting' | 'task' | 'comment';
	subject_type: string;
	subject_id: number;
	title: string;
	description?: string;
	content?: string;
	scheduled_at?: string;
	duration_minutes?: number;
	outcome?: ActivityOutcome;
	is_internal?: boolean;
	is_pinned?: boolean;
	metadata?: Record<string, unknown>;
}

export interface UpdateActivityData {
	title?: string;
	description?: string;
	content?: string;
	scheduled_at?: string;
	duration_minutes?: number;
	outcome?: ActivityOutcome;
	is_internal?: boolean;
	is_pinned?: boolean;
	metadata?: Record<string, unknown>;
}

export interface ActivityTimelineParams {
	subject_type: string;
	subject_id: number;
	type?: ActivityType;
	include_system?: boolean;
	limit?: number;
}

export const activitiesApi = {
	async list(params: ActivityListParams = {}): Promise<ActivityListResponse> {
		const searchParams = new URLSearchParams();
		Object.entries(params).forEach(([key, value]) => {
			if (value !== undefined && value !== null) {
				// Convert booleans to 1/0 for Laravel validation
				if (typeof value === 'boolean') {
					searchParams.append(key, value ? '1' : '0');
				} else {
					searchParams.append(key, String(value));
				}
			}
		});
		const queryString = searchParams.toString();
		const url = queryString ? `/activities?${queryString}` : '/activities';
		return apiClient.get<ActivityListResponse>(url);
	},

	async get(id: number): Promise<{ data: Activity }> {
		return apiClient.get<{ data: Activity }>(`/activities/${id}`);
	},

	async create(data: CreateActivityData): Promise<{ data: Activity; message: string }> {
		return apiClient.post<{ data: Activity; message: string }>('/activities', data);
	},

	async update(
		id: number,
		data: UpdateActivityData
	): Promise<{ data: Activity; message: string }> {
		return apiClient.put<{ data: Activity; message: string }>(`/activities/${id}`, data);
	},

	async delete(id: number): Promise<{ message: string }> {
		return apiClient.delete<{ message: string }>(`/activities/${id}`);
	},

	async complete(
		id: number,
		data?: { outcome?: ActivityOutcome; duration_minutes?: number }
	): Promise<{ data: Activity; message: string }> {
		return apiClient.post<{ data: Activity; message: string }>(`/activities/${id}/complete`, data ?? {});
	},

	async togglePin(id: number): Promise<{ data: Activity; message: string }> {
		return apiClient.post<{ data: Activity; message: string }>(`/activities/${id}/toggle-pin`, {});
	},

	async timeline(params: ActivityTimelineParams): Promise<{ data: Activity[] }> {
		const searchParams = new URLSearchParams();
		Object.entries(params).forEach(([key, value]) => {
			if (value !== undefined && value !== null) {
				searchParams.append(key, String(value));
			}
		});
		return apiClient.get<{ data: Activity[] }>(`/activities/timeline?${searchParams.toString()}`);
	},

	async upcoming(params?: { days?: number; user_id?: number }): Promise<{ data: Activity[] }> {
		const searchParams = new URLSearchParams();
		if (params) {
			Object.entries(params).forEach(([key, value]) => {
				if (value !== undefined) {
					searchParams.append(key, String(value));
				}
			});
		}
		const queryString = searchParams.toString();
		const url = queryString ? `/activities/upcoming?${queryString}` : '/activities/upcoming';
		return apiClient.get<{ data: Activity[] }>(url);
	},

	async overdue(params?: { user_id?: number }): Promise<{ data: Activity[] }> {
		const searchParams = new URLSearchParams();
		if (params?.user_id) {
			searchParams.append('user_id', String(params.user_id));
		}
		const queryString = searchParams.toString();
		const url = queryString ? `/activities/overdue?${queryString}` : '/activities/overdue';
		return apiClient.get<{ data: Activity[] }>(url);
	},

	async getTypes(): Promise<{ data: Record<string, string> }> {
		return apiClient.get<{ data: Record<string, string> }>('/activities/types');
	},

	async getOutcomes(): Promise<{ data: Record<string, string> }> {
		return apiClient.get<{ data: Record<string, string> }>('/activities/outcomes');
	}
};
