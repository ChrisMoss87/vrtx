import { apiClient } from './client';

// Types
export interface SchedulingPageBranding {
	logo_url?: string;
	primary_color?: string;
	background_color?: string;
	text_color?: string;
}

export interface SchedulingPage {
	id: number;
	name: string;
	slug: string;
	description: string | null;
	timezone: string;
	is_active: boolean;
	branding: SchedulingPageBranding | null;
	meeting_types_count?: number;
	created_at: string;
	updated_at: string;
}

export interface MeetingTypeQuestion {
	id: string;
	type: 'text' | 'textarea' | 'select' | 'checkbox';
	label: string;
	placeholder?: string;
	required: boolean;
	options?: string[];
}

export interface MeetingTypeSettings {
	buffer_before: number;
	buffer_after: number;
	min_notice_hours: number;
	max_days_advance: number;
	slot_interval: number;
}

export type LocationType = 'in_person' | 'phone' | 'zoom' | 'google_meet' | 'custom';

export interface MeetingType {
	id: number;
	scheduling_page_id: number;
	name: string;
	slug: string;
	description: string | null;
	duration_minutes: number;
	location_type: LocationType;
	location_details: string | null;
	color: string;
	is_active: boolean;
	questions: MeetingTypeQuestion[];
	settings: MeetingTypeSettings;
	display_order: number;
	created_at: string;
	updated_at: string;
}

export interface AvailabilityWindow {
	id?: number;
	start_time: string;
	end_time: string;
	is_available: boolean;
}

export interface AvailabilityRule {
	id: number;
	user_id: number;
	day_of_week: number;
	start_time: string;
	end_time: string;
	is_active: boolean;
	windows?: AvailabilityWindow[];
}

export interface SchedulingOverride {
	id: number;
	user_id: number;
	date: string;
	is_available: boolean;
	start_time: string | null;
	end_time: string | null;
	reason: string | null;
}

export interface ScheduledMeeting {
	id: number;
	meeting_type: MeetingType;
	host: {
		id: number;
		name: string;
		email: string;
	};
	attendee_name: string;
	attendee_email: string;
	attendee_phone: string | null;
	start_time: string;
	end_time: string;
	timezone: string;
	location: string | null;
	notes: string | null;
	answers: Record<string, string> | null;
	status: 'scheduled' | 'completed' | 'cancelled' | 'rescheduled' | 'no_show';
	cancellation_reason: string | null;
	cancelled_by: 'host' | 'attendee' | null;
	cancelled_at: string | null;
	contact_id: number | null;
	created_at: string;
}

export interface MeetingStats {
	total: number;
	scheduled: number;
	completed: number;
	cancelled: number;
	no_show: number;
	upcoming: number;
	upcoming_week: number;
	show_rate: number | null;
}

// API response types
interface ListResponse<T> {
	data: T[];
}

interface DetailResponse<T> {
	data: T;
	message?: string;
}

interface PaginatedResponse<T> {
	data: T[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

interface StatsResponse {
	stats: MeetingStats;
}

// Scheduling Pages API
export async function getSchedulingPages(): Promise<SchedulingPage[]> {
	const response = await apiClient.get<{ pages: SchedulingPage[] }>('/scheduling/pages');
	return response.pages || [];
}

export async function getSchedulingPage(id: number): Promise<SchedulingPage> {
	const response = await apiClient.get<{ page: SchedulingPage }>(`/scheduling/pages/${id}`);
	return response.page;
}

export async function createSchedulingPage(data: {
	name: string;
	slug?: string;
	description?: string;
	timezone?: string;
	is_active?: boolean;
	branding?: SchedulingPageBranding;
}): Promise<SchedulingPage> {
	const response = await apiClient.post<{ page: SchedulingPage; message: string }>('/scheduling/pages', data);
	return response.page;
}

export async function updateSchedulingPage(
	id: number,
	data: Partial<{
		name: string;
		slug: string;
		description: string;
		timezone: string;
		is_active: boolean;
		branding: SchedulingPageBranding;
	}>
): Promise<SchedulingPage> {
	const response = await apiClient.put<{ page: SchedulingPage; message: string }>(
		`/scheduling/pages/${id}`,
		data
	);
	return response.page;
}

export async function deleteSchedulingPage(id: number): Promise<void> {
	await apiClient.delete(`/scheduling/pages/${id}`);
}

export async function checkSlugAvailability(
	slug: string,
	excludeId?: number
): Promise<{ available: boolean; suggestions?: string[] }> {
	const params: Record<string, string> = { slug };
	if (excludeId) params.exclude_id = String(excludeId);
	return apiClient.get('/scheduling/pages/check-slug', { params });
}

// Meeting Types API
export async function getMeetingTypes(pageId: number): Promise<MeetingType[]> {
	const response = await apiClient.get<{ meeting_types: MeetingType[] }>(
		`/scheduling/pages/${pageId}/meeting-types`
	);
	return response.meeting_types || [];
}

export async function getMeetingType(pageId: number, typeId: number): Promise<MeetingType> {
	const response = await apiClient.get<{ meeting_type: MeetingType }>(
		`/scheduling/pages/${pageId}/meeting-types/${typeId}`
	);
	return response.meeting_type;
}

export async function createMeetingType(
	pageId: number,
	data: {
		name: string;
		slug?: string;
		description?: string;
		duration_minutes: number;
		location_type: LocationType;
		location_details?: string;
		color?: string;
		is_active?: boolean;
		questions?: MeetingTypeQuestion[];
		settings?: Partial<MeetingTypeSettings>;
	}
): Promise<MeetingType> {
	const response = await apiClient.post<{ meeting_type: MeetingType; message: string }>(
		`/scheduling/pages/${pageId}/meeting-types`,
		data
	);
	return response.meeting_type;
}

export async function updateMeetingType(
	pageId: number,
	typeId: number,
	data: Partial<{
		name: string;
		slug: string;
		description: string;
		duration_minutes: number;
		location_type: LocationType;
		location_details: string;
		color: string;
		is_active: boolean;
		questions: MeetingTypeQuestion[];
		settings: Partial<MeetingTypeSettings>;
	}>
): Promise<MeetingType> {
	const response = await apiClient.put<{ meeting_type: MeetingType; message: string }>(
		`/scheduling/pages/${pageId}/meeting-types/${typeId}`,
		data
	);
	return response.meeting_type;
}

export async function deleteMeetingType(pageId: number, typeId: number): Promise<void> {
	await apiClient.delete(`/scheduling/pages/${pageId}/meeting-types/${typeId}`);
}

export async function reorderMeetingTypes(
	pageId: number,
	order: number[]
): Promise<MeetingType[]> {
	const response = await apiClient.post<{ meeting_types: MeetingType[]; message: string }>(
		`/scheduling/pages/${pageId}/meeting-types/reorder`,
		{ order }
	);
	return response.meeting_types || [];
}

// Availability API
export async function getAvailabilityRules(): Promise<AvailabilityRule[]> {
	const response = await apiClient.get<{ availability: Array<{
		day_of_week: number;
		day_name: string;
		is_available: boolean;
		windows: Array<{
			id: number;
			start_time: string;
			end_time: string;
			is_available: boolean;
		}>;
	}>; days: Record<number, string> }>(
		'/scheduling/availability'
	);
	// Transform backend response to frontend format
	return response.availability.map(day => ({
		id: day.windows[0]?.id || 0,
		user_id: 0,
		day_of_week: day.day_of_week,
		start_time: day.windows[0]?.start_time || '09:00',
		end_time: day.windows[0]?.end_time || '17:00',
		is_active: day.is_available,
		windows: day.windows
	})) as AvailabilityRule[];
}

export async function updateAvailabilityRules(
	rules: Array<{
		day_of_week: number;
		start_time: string;
		end_time: string;
		is_active: boolean;
	}>
): Promise<AvailabilityRule[]> {
	// Transform frontend format to backend expected format
	const backendRules = rules.map(rule => ({
		day_of_week: rule.day_of_week,
		is_available: rule.is_active,
		windows: rule.is_active ? [{ start_time: rule.start_time, end_time: rule.end_time }] : []
	}));

	await apiClient.put<{ message: string }>(
		'/scheduling/availability',
		{ rules: backendRules }
	);
	return getAvailabilityRules();
}

export async function getSchedulingOverrides(params?: {
	start_date?: string;
	end_date?: string;
}): Promise<SchedulingOverride[]> {
	// Backend requires date range, default to 1 year from now
	const defaultParams = {
		start_date: params?.start_date || new Date().toISOString().split('T')[0],
		end_date: params?.end_date || new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
	};
	const response = await apiClient.get<{ overrides: SchedulingOverride[] }>(
		'/scheduling/availability/overrides',
		{ params: defaultParams }
	);
	return response.overrides || [];
}

export async function createSchedulingOverride(data: {
	date: string;
	is_available: boolean;
	start_time?: string;
	end_time?: string;
	reason?: string;
}): Promise<SchedulingOverride> {
	const response = await apiClient.post<{ override: SchedulingOverride; message: string }>(
		'/scheduling/availability/overrides',
		data
	);
	return response.override;
}

export async function deleteSchedulingOverride(id: number): Promise<void> {
	await apiClient.delete(`/scheduling/availability/overrides/${id}`);
}

// Scheduled Meetings API
export async function getScheduledMeetings(params?: {
	status?: 'scheduled' | 'completed' | 'cancelled' | 'no_show';
	start_date?: string;
	end_date?: string;
	page?: number;
	per_page?: number;
}): Promise<{ data: ScheduledMeeting[]; meta: PaginatedResponse<ScheduledMeeting>['meta'] }> {
	const response = await apiClient.get<{ meetings: { data: ScheduledMeeting[]; current_page: number; last_page: number; per_page: number; total: number } }>(
		'/scheduling/meetings',
		{ params }
	);
	// Backend wraps in 'meetings' key with Laravel pagination structure
	const meetings = response.meetings;
	return {
		data: meetings.data || [],
		meta: {
			current_page: meetings.current_page,
			last_page: meetings.last_page,
			per_page: meetings.per_page,
			total: meetings.total
		}
	};
}

export async function getScheduledMeeting(id: number): Promise<ScheduledMeeting> {
	const response = await apiClient.get<{ meeting: ScheduledMeeting }>(
		`/scheduling/meetings/${id}`
	);
	return response.meeting;
}

export async function getMeetingStats(): Promise<MeetingStats> {
	const response = await apiClient.get<StatsResponse>('/scheduling/meetings/stats');
	return response.stats;
}

export async function cancelMeeting(id: number, reason?: string): Promise<ScheduledMeeting> {
	const response = await apiClient.post<{ message: string }>(
		`/scheduling/meetings/${id}/cancel`,
		{ reason }
	);
	return getScheduledMeeting(id);
}

export async function markMeetingComplete(id: number): Promise<ScheduledMeeting> {
	const response = await apiClient.post<{ message: string; meeting: ScheduledMeeting }>(
		`/scheduling/meetings/${id}/complete`,
		{}
	);
	return response.meeting;
}

export async function markMeetingNoShow(id: number): Promise<ScheduledMeeting> {
	const response = await apiClient.post<{ message: string; meeting: ScheduledMeeting }>(
		`/scheduling/meetings/${id}/no-show`,
		{}
	);
	return response.meeting;
}

// Helper functions
export function getDefaultMeetingTypeSettings(): MeetingTypeSettings {
	return {
		buffer_before: 0,
		buffer_after: 0,
		min_notice_hours: 24,
		max_days_advance: 60,
		slot_interval: 15
	};
}

export function getDefaultAvailabilityRules(): Array<{
	day_of_week: number;
	start_time: string;
	end_time: string;
	is_active: boolean;
}> {
	const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
	return days.map((_, index) => ({
		day_of_week: index,
		start_time: '09:00',
		end_time: '17:00',
		is_active: index >= 1 && index <= 5 // Mon-Fri active by default
	}));
}

export function getDayName(dayOfWeek: number): string {
	const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
	return days[dayOfWeek] || '';
}

export function getLocationTypeLabel(type: string): string {
	const labels: Record<string, string> = {
		in_person: 'In Person',
		phone: 'Phone Call',
		zoom: 'Zoom',
		google_meet: 'Google Meet',
		custom: 'Custom'
	};
	return labels[type] || type;
}

export const LOCATION_TYPE_OPTIONS: Array<{ value: LocationType; label: string }> = [
	{ value: 'zoom', label: 'Zoom' },
	{ value: 'google_meet', label: 'Google Meet' },
	{ value: 'phone', label: 'Phone Call' },
	{ value: 'in_person', label: 'In Person' },
	{ value: 'custom', label: 'Custom' }
];

export function getMeetingStatusLabel(status: string): string {
	const labels: Record<string, string> = {
		scheduled: 'Scheduled',
		completed: 'Completed',
		cancelled: 'Cancelled',
		rescheduled: 'Rescheduled',
		no_show: 'No Show'
	};
	return labels[status] || status;
}

export function getMeetingStatusVariant(
	status: string
): 'default' | 'secondary' | 'destructive' | 'outline' {
	const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
		scheduled: 'default',
		completed: 'secondary',
		cancelled: 'destructive',
		rescheduled: 'outline',
		no_show: 'destructive'
	};
	return variants[status] || 'secondary';
}

export const MEETING_COLORS = [
	{ value: '#3B82F6', label: 'Blue' },
	{ value: '#10B981', label: 'Green' },
	{ value: '#8B5CF6', label: 'Purple' },
	{ value: '#F59E0B', label: 'Orange' },
	{ value: '#EF4444', label: 'Red' },
	{ value: '#EC4899', label: 'Pink' },
	{ value: '#06B6D4', label: 'Cyan' },
	{ value: '#6366F1', label: 'Indigo' }
];

export const DURATION_OPTIONS = [
	{ value: 15, label: '15 minutes' },
	{ value: 30, label: '30 minutes' },
	{ value: 45, label: '45 minutes' },
	{ value: 60, label: '1 hour' },
	{ value: 90, label: '1.5 hours' },
	{ value: 120, label: '2 hours' }
];
