import { apiClient } from './client';

export interface Meeting {
	id: number;
	title: string;
	description: string | null;
	start_time: string;
	end_time: string;
	duration_minutes: number;
	location: string | null;
	is_online: boolean;
	meeting_url: string | null;
	status: 'tentative' | 'confirmed' | 'cancelled';
	outcome: 'completed' | 'no_show' | 'rescheduled' | 'cancelled' | null;
	outcome_notes: string | null;
	deal_id: number | null;
	company_id: number | null;
	is_upcoming: boolean;
	is_today: boolean;
	participant_count: number;
	calendar_provider: string;
	participants?: MeetingParticipant[];
}

export interface MeetingParticipant {
	id: number;
	email: string;
	name: string | null;
	contact_id: number | null;
	is_organizer: boolean;
	response_status: 'needs_action' | 'accepted' | 'declined' | 'tentative';
}

export interface CreateMeetingData {
	title: string;
	description?: string;
	start_time: string;
	end_time: string;
	location?: string;
	is_online?: boolean;
	meeting_url?: string;
	deal_id?: number;
	company_id?: number;
	participants?: { email: string; name?: string }[];
}

export interface UpdateMeetingData {
	title?: string;
	description?: string;
	start_time?: string;
	end_time?: string;
	location?: string;
	is_online?: boolean;
	meeting_url?: string;
	deal_id?: number | null;
	company_id?: number | null;
}

export interface MeetingAnalyticsOverview {
	total_meetings: number;
	total_hours: number;
	unique_stakeholders: number;
	change_percent: number | null;
	period: string;
}

export interface MeetingHeatmap {
	data: Record<number, Record<string, number>>;
	max_value: number;
	peak_times: { hour: number; day: string }[];
	days: string[];
	hours: number[];
}

export interface DealMeetingAnalytics {
	total_meetings: number;
	total_hours: number;
	unique_stakeholders: number;
	meetings_per_week: number | null;
	first_meeting: string | null;
	last_meeting: string | null;
	timeline: {
		id: number;
		title: string;
		date: string;
		duration_minutes: number;
		participant_count: number;
	}[];
	stakeholders: {
		email: string;
		name: string | null;
		contact_id: number | null;
		meeting_count: number;
		last_met: string | null;
	}[];
}

export interface StakeholderCoverage {
	total_stakeholders: number;
	total_meetings: number;
	stakeholders: {
		email: string;
		name: string | null;
		contact_id: number | null;
		meeting_count: number;
		first_met: string | null;
		last_met: string | null;
		total_minutes: number;
	}[];
}

export interface DealInsight {
	type: 'success' | 'warning' | 'info';
	title: string;
	description: string;
}

export interface DealInsightsResponse {
	analytics: DealMeetingAnalytics;
	insights: DealInsight[];
}

// API Functions
export const meetingsApi = {
	// Meeting CRUD operations
	async getMeetings(params?: {
		from?: string;
		to?: string;
		deal_id?: number;
		company_id?: number;
	}): Promise<Meeting[]> {
		const response = await apiClient.get<{ data: Meeting[] }>('/meetings', { params });
		return response.data;
	},

	async getMeeting(id: number): Promise<Meeting> {
		const response = await apiClient.get<{ data: Meeting }>(`/meetings/${id}`);
		return response.data;
	},

	async createMeeting(data: CreateMeetingData): Promise<Meeting> {
		const response = await apiClient.post<{ data: Meeting; message: string }>('/meetings', data);
		return response.data;
	},

	async updateMeeting(id: number, data: UpdateMeetingData): Promise<Meeting> {
		const response = await apiClient.put<{ data: Meeting; message: string }>(`/meetings/${id}`, data);
		return response.data;
	},

	async deleteMeeting(id: number): Promise<void> {
		await apiClient.delete(`/meetings/${id}`);
	},

	// Quick access endpoints
	async getUpcomingMeetings(limit: number = 10): Promise<Meeting[]> {
		const response = await apiClient.get<{ data: Meeting[] }>('/meetings/upcoming', {
			params: { limit }
		});
		return response.data;
	},

	async getTodaysMeetings(): Promise<Meeting[]> {
		const response = await apiClient.get<{ data: Meeting[] }>('/meetings/today');
		return response.data;
	},

	// Meeting actions
	async linkMeetingToDeal(meetingId: number, dealId: number): Promise<Meeting> {
		const response = await apiClient.post<{ data: Meeting; message: string }>(
			`/meetings/${meetingId}/link-deal`,
			{ deal_id: dealId }
		);
		return response.data;
	},

	async logMeetingOutcome(
		meetingId: number,
		outcome: 'completed' | 'no_show' | 'rescheduled' | 'cancelled',
		notes?: string
	): Promise<Meeting> {
		const response = await apiClient.post<{ data: Meeting; message: string }>(
			`/meetings/${meetingId}/outcome`,
			{ outcome, notes }
		);
		return response.data;
	},

	// Analytics endpoints
	async getAnalyticsOverview(period: 'week' | 'month' | 'quarter' = 'month'): Promise<MeetingAnalyticsOverview> {
		const response = await apiClient.get<{ data: MeetingAnalyticsOverview }>(
			'/meetings/analytics/overview',
			{ params: { period } }
		);
		return response.data;
	},

	async getAnalyticsHeatmap(weeks: number = 4): Promise<MeetingHeatmap> {
		const response = await apiClient.get<{ data: MeetingHeatmap }>(
			'/meetings/analytics/heatmap',
			{ params: { weeks } }
		);
		return response.data;
	},

	async getDealAnalytics(dealId: number): Promise<DealMeetingAnalytics> {
		const response = await apiClient.get<{ data: DealMeetingAnalytics }>(
			`/meetings/analytics/deal/${dealId}`
		);
		return response.data;
	},

	async getCompanyAnalytics(companyId: number): Promise<StakeholderCoverage> {
		const response = await apiClient.get<{ data: StakeholderCoverage }>(
			`/meetings/analytics/company/${companyId}`
		);
		return response.data;
	},

	async getStakeholderCoverage(companyId: number, dealId?: number): Promise<StakeholderCoverage> {
		const response = await apiClient.get<{ data: StakeholderCoverage }>(
			`/meetings/stakeholder-coverage/${companyId}`,
			{ params: dealId ? { deal_id: dealId } : undefined }
		);
		return response.data;
	},

	async getDealInsights(dealId: number): Promise<DealInsightsResponse> {
		const response = await apiClient.get<{ data: DealInsightsResponse }>(
			`/meetings/deal-insights/${dealId}`
		);
		return response.data;
	}
};
