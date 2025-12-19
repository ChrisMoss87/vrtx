import { get } from 'svelte/store';
import { dateFormat, timeFormat, timezone, weekStartsOn } from '$lib/stores/preferences';

/**
 * Format a date according to user preferences
 */
export function formatDate(date: Date | string | null | undefined): string {
	if (!date) return '';

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return '';

	const format = get(dateFormat);
	const tz = get(timezone);

	const options: Intl.DateTimeFormatOptions = {
		timeZone: tz
	};

	const day = d.toLocaleDateString('en-US', { ...options, day: '2-digit' });
	const month = d.toLocaleDateString('en-US', { ...options, month: '2-digit' });
	const year = d.toLocaleDateString('en-US', { ...options, year: 'numeric' });

	switch (format) {
		case 'DD/MM/YYYY':
			return `${day}/${month}/${year}`;
		case 'YYYY-MM-DD':
			return `${year}-${month}-${day}`;
		case 'MM/DD/YYYY':
		default:
			return `${month}/${day}/${year}`;
	}
}

/**
 * Format a time according to user preferences
 */
export function formatTime(date: Date | string | null | undefined): string {
	if (!date) return '';

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return '';

	const format = get(timeFormat);
	const tz = get(timezone);

	return d.toLocaleTimeString('en-US', {
		timeZone: tz,
		hour: 'numeric',
		minute: '2-digit',
		hour12: format === '12h'
	});
}

/**
 * Format a date and time according to user preferences
 */
export function formatDateTime(date: Date | string | null | undefined): string {
	if (!date) return '';

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return '';

	return `${formatDate(d)} ${formatTime(d)}`;
}

/**
 * Format a relative time (e.g., "2 hours ago", "in 3 days")
 */
export function formatRelativeTime(date: Date | string | null | undefined): string {
	if (!date) return '';

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return '';

	const now = new Date();
	const diffMs = d.getTime() - now.getTime();
	const diffSecs = Math.round(diffMs / 1000);
	const diffMins = Math.round(diffSecs / 60);
	const diffHours = Math.round(diffMins / 60);
	const diffDays = Math.round(diffHours / 24);

	const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

	if (Math.abs(diffSecs) < 60) {
		return rtf.format(diffSecs, 'second');
	} else if (Math.abs(diffMins) < 60) {
		return rtf.format(diffMins, 'minute');
	} else if (Math.abs(diffHours) < 24) {
		return rtf.format(diffHours, 'hour');
	} else if (Math.abs(diffDays) < 30) {
		return rtf.format(diffDays, 'day');
	} else {
		return formatDate(d);
	}
}

/**
 * Format a date for display in a short format
 */
export function formatShortDate(date: Date | string | null | undefined): string {
	if (!date) return '';

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return '';

	const tz = get(timezone);

	return d.toLocaleDateString('en-US', {
		timeZone: tz,
		month: 'short',
		day: 'numeric'
	});
}

/**
 * Format a date with year in short format
 */
export function formatShortDateWithYear(date: Date | string | null | undefined): string {
	if (!date) return '';

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return '';

	const tz = get(timezone);

	return d.toLocaleDateString('en-US', {
		timeZone: tz,
		month: 'short',
		day: 'numeric',
		year: 'numeric'
	});
}

/**
 * Get the first day of the week (0 = Sunday, 1 = Monday)
 */
export function getFirstDayOfWeek(): number {
	return get(weekStartsOn) === 'monday' ? 1 : 0;
}

/**
 * Format duration in a human readable way
 */
export function formatDuration(minutes: number): string {
	if (minutes < 60) {
		return `${minutes}m`;
	}

	const hours = Math.floor(minutes / 60);
	const mins = minutes % 60;

	if (mins === 0) {
		return `${hours}h`;
	}

	return `${hours}h ${mins}m`;
}

/**
 * Parse a date string in user's preferred format
 */
export function parseUserDate(dateString: string): Date | null {
	if (!dateString) return null;

	const format = get(dateFormat);
	const parts = dateString.split(/[/-]/);

	if (parts.length !== 3) return null;

	let year: number, month: number, day: number;

	switch (format) {
		case 'DD/MM/YYYY':
			day = parseInt(parts[0], 10);
			month = parseInt(parts[1], 10) - 1;
			year = parseInt(parts[2], 10);
			break;
		case 'YYYY-MM-DD':
			year = parseInt(parts[0], 10);
			month = parseInt(parts[1], 10) - 1;
			day = parseInt(parts[2], 10);
			break;
		case 'MM/DD/YYYY':
		default:
			month = parseInt(parts[0], 10) - 1;
			day = parseInt(parts[1], 10);
			year = parseInt(parts[2], 10);
			break;
	}

	const date = new Date(year, month, day);

	if (isNaN(date.getTime())) return null;

	return date;
}

/**
 * Get current date in user's timezone
 */
export function getCurrentDate(): Date {
	return new Date();
}

/**
 * Check if a date is today
 */
export function isToday(date: Date | string | null | undefined): boolean {
	if (!date) return false;

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return false;

	const today = new Date();
	return (
		d.getDate() === today.getDate() &&
		d.getMonth() === today.getMonth() &&
		d.getFullYear() === today.getFullYear()
	);
}

/**
 * Check if a date is in the past
 */
export function isPast(date: Date | string | null | undefined): boolean {
	if (!date) return false;

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return false;

	return d.getTime() < Date.now();
}

/**
 * Check if a date is in the future
 */
export function isFuture(date: Date | string | null | undefined): boolean {
	if (!date) return false;

	const d = typeof date === 'string' ? new Date(date) : date;
	if (isNaN(d.getTime())) return false;

	return d.getTime() > Date.now();
}
