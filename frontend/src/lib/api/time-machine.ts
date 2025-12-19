import { apiClient } from './client';

export interface HistoryEntry {
	id: number;
	timestamp: string;
	type: 'field_change' | 'stage_change' | 'daily' | 'manual';
	changes: {
		action?: string;
		fields_changed?: string[];
		changes?: Record<string, { old: unknown; new: unknown }>;
		note?: string;
	};
	created_by: {
		id: number;
		name: string;
	} | null;
}

export interface FieldChange {
	id: number;
	field: string;
	old_value: unknown;
	new_value: unknown;
	changed_at: string;
	changed_by: {
		id: number;
		name: string;
	} | null;
}

export interface TimelineMarker {
	id: number;
	timestamp: string;
	type: string;
	label: string;
}

export interface TimelineEvent {
	timestamp: string;
	type: string;
	label: string;
	details: Record<string, unknown>;
	user: string | null;
}

export interface DiffChange {
	field_api_name: string;
	field_label: string;
	field_type: string;
	from_value: unknown;
	to_value: unknown;
	change_type: 'added' | 'modified' | 'removed';
	formatted_change: {
		from_display: string;
		to_display: string;
		numeric_change?: number;
		percentage_change?: number | null;
	};
}

export interface DiffResult {
	from_timestamp: string;
	to_timestamp: string;
	from_state: Record<string, unknown>;
	to_state: Record<string, unknown>;
	changes: Record<string, DiffChange>;
	summary: {
		total_fields_changed: number;
		fields_added: number;
		fields_modified: number;
		fields_removed: number;
		significant_changes: Array<{
			field: string;
			from: string;
			to: string;
		}>;
	};
}

export interface ComparisonField {
	field_api_name: string;
	field_label: string;
	from_value: unknown;
	to_value: unknown;
	from_display: string;
	to_display: string;
	has_changed: boolean;
	change_type: 'added' | 'modified' | 'removed' | null;
}

export interface ComparisonResult {
	from_timestamp: string;
	to_timestamp: string;
	comparison: ComparisonField[];
	summary: DiffResult['summary'];
}

/**
 * Get record history (list of snapshots/changes).
 */
export async function getRecordHistory(
	moduleApiName: string,
	recordId: number,
	options?: {
		startDate?: string;
		endDate?: string;
		limit?: number;
	}
): Promise<HistoryEntry[]> {
	const params = new URLSearchParams();
	if (options?.startDate) params.set('start_date', options.startDate);
	if (options?.endDate) params.set('end_date', options.endDate);
	if (options?.limit) params.set('limit', options.limit.toString());

	const response = await apiClient.get<{ data: HistoryEntry[] }>(
		`/records/${moduleApiName}/${recordId}/history?${params}`
	);
	return response.data;
}

/**
 * Get record state at a specific timestamp.
 */
export async function getRecordAtTimestamp(
	moduleApiName: string,
	recordId: number,
	timestamp: string
): Promise<{
	data: Record<string, unknown>;
	fields: Record<string, { label: string; type: string }>;
}> {
	const response = await apiClient.get<{
		data: Record<string, unknown>;
		meta: {
			fields: Record<string, { label: string; type: string }>;
		};
	}>(`/records/${moduleApiName}/${recordId}/at/${encodeURIComponent(timestamp)}`);
	return {
		data: response.data,
		fields: response.meta.fields
	};
}

/**
 * Get diff between two timestamps.
 */
export async function getRecordDiff(
	moduleApiName: string,
	recordId: number,
	fromTimestamp: string,
	toTimestamp: string
): Promise<DiffResult> {
	const params = new URLSearchParams({
		from: fromTimestamp,
		to: toTimestamp
	});
	const response = await apiClient.get<{ data: DiffResult }>(
		`/records/${moduleApiName}/${recordId}/diff?${params}`
	);
	return response.data;
}

/**
 * Get side-by-side comparison.
 */
export async function getRecordComparison(
	moduleApiName: string,
	recordId: number,
	fromTimestamp: string,
	toTimestamp: string
): Promise<ComparisonResult> {
	const params = new URLSearchParams({
		from: fromTimestamp,
		to: toTimestamp
	});
	const response = await apiClient.get<{ data: ComparisonResult }>(
		`/records/${moduleApiName}/${recordId}/compare?${params}`
	);
	return response.data;
}

/**
 * Get timeline events for visualization.
 */
export async function getRecordTimeline(
	moduleApiName: string,
	recordId: number
): Promise<TimelineEvent[]> {
	const response = await apiClient.get<{ data: TimelineEvent[] }>(
		`/records/${moduleApiName}/${recordId}/timeline`
	);
	return response.data;
}

/**
 * Get timeline markers for the time machine slider.
 */
export async function getTimelineMarkers(
	moduleApiName: string,
	recordId: number,
	options?: {
		startDate?: string;
		endDate?: string;
	}
): Promise<TimelineMarker[]> {
	const params = new URLSearchParams();
	if (options?.startDate) params.set('start_date', options.startDate);
	if (options?.endDate) params.set('end_date', options.endDate);

	const response = await apiClient.get<{ data: TimelineMarker[] }>(
		`/records/${moduleApiName}/${recordId}/timeline-markers?${params}`
	);
	return response.data;
}

/**
 * Get field change log for a record.
 */
export async function getFieldChanges(
	moduleApiName: string,
	recordId: number,
	options?: {
		startDate?: string;
		endDate?: string;
		field?: string;
	}
): Promise<FieldChange[]> {
	const params = new URLSearchParams();
	if (options?.startDate) params.set('start_date', options.startDate);
	if (options?.endDate) params.set('end_date', options.endDate);
	if (options?.field) params.set('field', options.field);

	const response = await apiClient.get<{ data: FieldChange[] }>(
		`/records/${moduleApiName}/${recordId}/field-changes?${params}`
	);
	return response.data;
}

/**
 * Create a manual snapshot.
 */
export async function createSnapshot(
	moduleApiName: string,
	recordId: number,
	note?: string
): Promise<{
	id: number;
	timestamp: string;
	type: string;
}> {
	const response = await apiClient.post<{
		data: { id: number; timestamp: string; type: string };
	}>(`/records/${moduleApiName}/${recordId}/snapshot`, { note });
	return response.data;
}
