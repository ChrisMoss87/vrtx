import { browser } from '$app/environment';

// Types
export interface PublicSchedulingPage {
	name: string;
	slug: string;
	description: string | null;
	timezone: string;
	branding: {
		logo_url?: string;
		primary_color?: string;
		background_color?: string;
		text_color?: string;
	} | null;
	host: {
		name: string;
	};
}

export type LocationType = 'in_person' | 'phone' | 'zoom' | 'google_meet' | 'custom';

export interface PublicMeetingType {
	id: number;
	name: string;
	slug: string;
	duration_minutes: number;
	description: string | null;
	location_type: LocationType;
	color: string;
}

export interface PublicMeetingTypeDetail extends PublicMeetingType {
	questions: Array<{
		id: string;
		type: 'text' | 'textarea' | 'select' | 'checkbox';
		label: string;
		placeholder?: string;
		required: boolean;
		options?: string[];
	}>;
	settings: {
		max_days_advance: number;
	};
}

export interface TimeSlot {
	time: string;
	available: boolean;
}

export interface BookedMeeting {
	id: number;
	start_time: string;
	end_time: string;
	timezone: string;
	location: string | null;
	manage_url: string;
	cancel_url: string;
	host: {
		name: string;
	};
	meeting_type: {
		name: string;
		duration_minutes: number;
	};
}

export interface MeetingDetails {
	id: number;
	attendee_name: string;
	attendee_email: string;
	start_time: string;
	end_time: string;
	timezone: string;
	location: string | null;
	status: 'scheduled' | 'completed' | 'cancelled' | 'rescheduled' | 'no_show';
	can_cancel: boolean;
	can_reschedule: boolean;
	host: {
		name: string;
	};
	meeting_type: {
		name: string;
		slug: string;
		duration_minutes: number;
	};
	page: {
		slug: string;
	};
}

// Helper function to get base URL
function getBaseUrl(): string {
	if (!browser) {
		return 'http://localhost:8000/api/v1';
	}
	return `${window.location.origin}/api/v1`;
}

// Public API calls (no auth required)
export async function getPublicPage(
	slug: string
): Promise<{ page: PublicSchedulingPage; meeting_types: PublicMeetingType[] }> {
	const response = await fetch(`${getBaseUrl()}/schedule/${slug}`, {
		headers: {
			Accept: 'application/json'
		}
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Page not found' }));
		throw new Error(error.message);
	}

	return response.json();
}

export async function getPublicMeetingType(
	pageSlug: string,
	typeSlug: string
): Promise<{ page: PublicSchedulingPage; meeting_type: PublicMeetingTypeDetail }> {
	const response = await fetch(`${getBaseUrl()}/schedule/${pageSlug}/${typeSlug}`, {
		headers: {
			Accept: 'application/json'
		}
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Meeting type not found' }));
		throw new Error(error.message);
	}

	return response.json();
}

export async function getAvailableDates(
	pageSlug: string,
	typeSlug: string,
	month: string,
	timezone: string
): Promise<{ available_dates: string[]; month: string }> {
	const params = new URLSearchParams({ month, timezone });
	const response = await fetch(
		`${getBaseUrl()}/schedule/${pageSlug}/${typeSlug}/dates?${params}`,
		{
			headers: {
				Accept: 'application/json'
			}
		}
	);

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Failed to get dates' }));
		throw new Error(error.message);
	}

	return response.json();
}

export async function getAvailableSlots(
	pageSlug: string,
	typeSlug: string,
	date: string,
	timezone: string
): Promise<{ slots: TimeSlot[]; date: string; timezone: string }> {
	const params = new URLSearchParams({ date, timezone });
	const response = await fetch(
		`${getBaseUrl()}/schedule/${pageSlug}/${typeSlug}/slots?${params}`,
		{
			headers: {
				Accept: 'application/json'
			}
		}
	);

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Failed to get slots' }));
		throw new Error(error.message);
	}

	return response.json();
}

export async function bookMeeting(
	pageSlug: string,
	typeSlug: string,
	data: {
		name: string;
		email: string;
		phone?: string;
		start_time: string;
		timezone: string;
		notes?: string;
		answers?: Record<string, string>;
	}
): Promise<{ message: string; meeting: BookedMeeting }> {
	const response = await fetch(`${getBaseUrl()}/schedule/${pageSlug}/${typeSlug}/book`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json'
		},
		body: JSON.stringify(data)
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Failed to book meeting' }));
		throw new Error(error.message);
	}

	return response.json();
}

export async function getMeetingByToken(token: string): Promise<{ meeting: MeetingDetails }> {
	const response = await fetch(`${getBaseUrl()}/schedule/meetings/${token}`, {
		headers: {
			Accept: 'application/json'
		}
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Meeting not found' }));
		throw new Error(error.message);
	}

	return response.json();
}

export async function cancelMeetingByToken(
	token: string,
	reason?: string
): Promise<{ message: string }> {
	const response = await fetch(`${getBaseUrl()}/schedule/meetings/${token}/cancel`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json'
		},
		body: JSON.stringify({ reason })
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Failed to cancel meeting' }));
		throw new Error(error.message);
	}

	return response.json();
}

export async function rescheduleMeetingByToken(
	token: string,
	data: {
		start_time: string;
		timezone: string;
	}
): Promise<{ message: string; meeting: { start_time: string; end_time: string; timezone: string } }> {
	const response = await fetch(`${getBaseUrl()}/schedule/meetings/${token}/reschedule`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json'
		},
		body: JSON.stringify(data)
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Failed to reschedule meeting' }));
		throw new Error(error.message);
	}

	return response.json();
}

// Utility functions
export function formatTimeSlot(time: string): string {
	const [hours, minutes] = time.split(':');
	const hour = parseInt(hours);
	const ampm = hour >= 12 ? 'PM' : 'AM';
	const hour12 = hour % 12 || 12;
	return `${hour12}:${minutes} ${ampm}`;
}

export function getTimezoneLabel(timezone: string): string {
	try {
		const now = new Date();
		const formatter = new Intl.DateTimeFormat('en-US', {
			timeZone: timezone,
			timeZoneName: 'short'
		});
		const parts = formatter.formatToParts(now);
		const tzPart = parts.find((p) => p.type === 'timeZoneName');
		return tzPart ? `${timezone} (${tzPart.value})` : timezone;
	} catch {
		return timezone;
	}
}

export function getBrowserTimezone(): string {
	return Intl.DateTimeFormat().resolvedOptions().timeZone;
}
