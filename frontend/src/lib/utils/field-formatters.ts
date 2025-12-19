import { formatDate, formatDateTime, formatTime } from './datetime';

/**
 * Format a field value based on its type
 */
export function formatFieldValue(value: unknown, fieldType: string): string {
	if (value === null || value === undefined || value === '') {
		return '';
	}

	switch (fieldType) {
		case 'currency':
			return formatCurrency(Number(value));

		case 'percent':
			return formatPercent(Number(value));

		case 'number':
		case 'decimal':
			return formatNumber(Number(value));

		case 'date':
			return formatDate(value as string | Date);

		case 'datetime':
			return formatDateTime(value as string | Date);

		case 'time':
			return formatTime(value as string | Date);

		case 'phone':
			return formatPhone(String(value));

		case 'email':
			return String(value);

		case 'url':
			return String(value);

		case 'checkbox':
		case 'toggle':
			return value ? 'Yes' : 'No';

		case 'multiselect':
			if (Array.isArray(value)) {
				return value.join(', ');
			}
			return String(value);

		case 'select':
		case 'radio':
		case 'text':
		case 'textarea':
		case 'rich_text':
		case 'auto_number':
		case 'formula':
		case 'lookup':
		default:
			return String(value);
	}
}

/**
 * Format a currency value
 */
export function formatCurrency(value: number): string {
	if (isNaN(value)) return '';

	return new Intl.NumberFormat('en-US', {
		style: 'currency',
		currency: 'USD',
		minimumFractionDigits: 0,
		maximumFractionDigits: 2
	}).format(value);
}

/**
 * Format a percentage value
 */
export function formatPercent(value: number): string {
	if (isNaN(value)) return '';

	return new Intl.NumberFormat('en-US', {
		style: 'percent',
		minimumFractionDigits: 0,
		maximumFractionDigits: 2
	}).format(value / 100);
}

/**
 * Format a number value
 */
export function formatNumber(value: number): string {
	if (isNaN(value)) return '';

	return new Intl.NumberFormat('en-US', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2
	}).format(value);
}

/**
 * Format a phone number
 */
export function formatPhone(value: string): string {
	if (!value) return '';

	// Remove all non-numeric characters
	const cleaned = value.replace(/\D/g, '');

	// Format US phone numbers (10 digits)
	if (cleaned.length === 10) {
		return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`;
	}

	// Format US phone numbers with country code (11 digits)
	if (cleaned.length === 11 && cleaned.startsWith('1')) {
		return `+1 (${cleaned.slice(1, 4)}) ${cleaned.slice(4, 7)}-${cleaned.slice(7)}`;
	}

	// Return original value if not a standard format
	return value;
}

/**
 * Get a color class for a field type badge
 */
export function getFieldTypeColor(fieldType: string): string {
	const colorMap: Record<string, string> = {
		text: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
		email: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
		phone: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
		url: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
		number: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
		currency: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
		percent: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
		date: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
		datetime: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
		select: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
		multiselect: 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-300',
		checkbox: 'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-300',
		lookup: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
	};

	return colorMap[fieldType] || 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-300';
}

/**
 * Truncate text to a specified length
 */
export function truncateText(text: string, maxLength: number = 50): string {
	if (!text || text.length <= maxLength) return text;
	return text.slice(0, maxLength) + '...';
}
