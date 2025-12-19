import { apiClient } from './client';

export interface DealRoomMember {
	id: number;
	user_id: number | null;
	name: string;
	email: string | null;
	role: 'owner' | 'team' | 'stakeholder' | 'viewer';
	is_internal: boolean;
	last_accessed_at: string | null;
	access_token: string | null;
}

export interface DealRoomActionItem {
	id: number;
	title: string;
	description: string | null;
	assigned_to: number | null;
	assignee_name: string | null;
	assigned_party: 'seller' | 'buyer' | 'both' | null;
	due_date: string | null;
	status: 'pending' | 'in_progress' | 'completed';
	display_order: number;
	is_overdue: boolean;
	completed_at: string | null;
}

export interface DealRoomDocument {
	id: number;
	name: string;
	file_size: number | null;
	formatted_size: string;
	mime_type: string | null;
	version: number;
	description: string | null;
	is_visible_to_external: boolean;
	view_count: number;
	uploaded_by: string | null;
	created_at: string;
}

export interface DealRoomMessage {
	id: number;
	member: DealRoomMember | null;
	message: string;
	is_internal: boolean;
	attachments: unknown[];
	created_at: string;
}

export interface DealRoomActivity {
	id: number;
	type: string;
	description: string;
	member: DealRoomMember | null;
	data: Record<string, unknown>;
	created_at: string;
}

export interface DealRoom {
	id: number;
	deal_record_id: number;
	name: string;
	slug: string;
	description: string | null;
	status: 'active' | 'won' | 'lost' | 'archived';
	branding?: Record<string, unknown>;
	settings?: Record<string, unknown>;
	action_items_count: number;
	documents_count: number;
	messages_count: number;
	member_count: number;
	members?: DealRoomMember[];
	action_items?: DealRoomActionItem[];
	documents?: DealRoomDocument[];
	progress?: { total: number; completed: number; percentage: number };
	created_at: string;
	updated_at: string;
}

export interface DealRoomAnalytics {
	action_plan: { total: number; completed: number; percentage: number };
	documents: Array<{
		id: number;
		name: string;
		view_count: number;
		unique_viewers: number;
		total_time_spent: number;
	}>;
	member_engagement: Array<{
		id: number;
		name: string;
		is_internal: boolean;
		last_accessed: string | null;
		documents_viewed: number;
		messages_sent: number;
	}>;
	activity_count: number;
	message_count: number;
}

/**
 * Get deal rooms for the current user.
 */
export async function getDealRooms(status?: string): Promise<DealRoom[]> {
	const params = status ? `?status=${status}` : '';
	const response = await apiClient.get<{ data: DealRoom[] }>(`/deal-rooms${params}`);
	return response.data;
}

/**
 * Get a single deal room.
 */
export async function getDealRoom(id: number): Promise<DealRoom> {
	const response = await apiClient.get<{ data: DealRoom }>(`/deal-rooms/${id}`);
	return response.data;
}

/**
 * Create a deal room.
 */
export async function createDealRoom(data: {
	deal_record_id: number;
	name: string;
	description?: string;
	branding?: Record<string, unknown>;
	settings?: Record<string, unknown>;
}): Promise<DealRoom> {
	const response = await apiClient.post<{ data: DealRoom }>('/deal-rooms', data);
	return response.data;
}

/**
 * Update a deal room.
 */
export async function updateDealRoom(
	id: number,
	data: {
		name?: string;
		description?: string;
		status?: string;
		branding?: Record<string, unknown>;
		settings?: Record<string, unknown>;
	}
): Promise<DealRoom> {
	const response = await apiClient.put<{ data: DealRoom }>(`/deal-rooms/${id}`, data);
	return response.data;
}

/**
 * Delete a deal room.
 */
export async function deleteDealRoom(id: number): Promise<void> {
	await apiClient.delete(`/deal-rooms/${id}`);
}

/**
 * Add a member to a deal room.
 */
export async function addMember(
	roomId: number,
	data: {
		user_id?: number;
		external_email?: string;
		external_name?: string;
		role?: string;
	}
): Promise<DealRoomMember> {
	const response = await apiClient.post<{ data: DealRoomMember }>(
		`/deal-rooms/${roomId}/members`,
		data
	);
	return response.data;
}

/**
 * Remove a member from a deal room.
 */
export async function removeMember(roomId: number, memberId: number): Promise<void> {
	await apiClient.delete(`/deal-rooms/${roomId}/members/${memberId}`);
}

/**
 * Get action items for a room.
 */
export async function getActionItems(
	roomId: number
): Promise<{ data: DealRoomActionItem[]; progress: { total: number; completed: number; percentage: number } }> {
	const response = await apiClient.get<{
		data: DealRoomActionItem[];
		progress: { total: number; completed: number; percentage: number };
	}>(`/deal-rooms/${roomId}/actions`);
	return response;
}

/**
 * Create an action item.
 */
export async function createActionItem(
	roomId: number,
	data: {
		title: string;
		description?: string;
		assigned_to?: number;
		assigned_party?: string;
		due_date?: string;
	}
): Promise<DealRoomActionItem> {
	const response = await apiClient.post<{ data: DealRoomActionItem }>(
		`/deal-rooms/${roomId}/actions`,
		data
	);
	return response.data;
}

/**
 * Update an action item.
 */
export async function updateActionItem(
	roomId: number,
	actionId: number,
	data: Partial<DealRoomActionItem>
): Promise<DealRoomActionItem> {
	const response = await apiClient.put<{ data: DealRoomActionItem }>(
		`/deal-rooms/${roomId}/actions/${actionId}`,
		data
	);
	return response.data;
}

/**
 * Delete an action item.
 */
export async function deleteActionItem(roomId: number, actionId: number): Promise<void> {
	await apiClient.delete(`/deal-rooms/${roomId}/actions/${actionId}`);
}

/**
 * Get messages for a room.
 */
export async function getMessages(
	roomId: number,
	includeInternal = false
): Promise<DealRoomMessage[]> {
	const params = includeInternal ? '?include_internal=true' : '';
	const response = await apiClient.get<{ data: DealRoomMessage[] }>(
		`/deal-rooms/${roomId}/messages${params}`
	);
	return response.data;
}

/**
 * Send a message.
 */
export async function sendMessage(
	roomId: number,
	message: string,
	isInternal = false
): Promise<DealRoomMessage> {
	const response = await apiClient.post<{ data: DealRoomMessage }>(
		`/deal-rooms/${roomId}/messages`,
		{ message, is_internal: isInternal }
	);
	return response.data;
}

/**
 * Get room analytics.
 */
export async function getRoomAnalytics(roomId: number): Promise<DealRoomAnalytics> {
	const response = await apiClient.get<{ data: DealRoomAnalytics }>(
		`/deal-rooms/${roomId}/analytics`
	);
	return response.data;
}

/**
 * Get room activity feed.
 */
export async function getRoomActivities(roomId: number): Promise<DealRoomActivity[]> {
	const response = await apiClient.get<{ data: DealRoomActivity[] }>(
		`/deal-rooms/${roomId}/activities`
	);
	return response.data;
}

/**
 * Upload a document.
 */
export async function uploadDocument(
	roomId: number,
	file: File,
	options?: { name?: string; description?: string; is_visible_to_external?: boolean }
): Promise<DealRoomDocument> {
	const formData = new FormData();
	formData.append('file', file);
	if (options?.name) formData.append('name', options.name);
	if (options?.description) formData.append('description', options.description);
	if (options?.is_visible_to_external !== undefined) {
		formData.append('is_visible_to_external', options.is_visible_to_external ? '1' : '0');
	}

	const response = await apiClient.post<{ data: DealRoomDocument }>(
		`/deal-rooms/${roomId}/documents`,
		formData
	);
	return response.data;
}

/**
 * Delete a document.
 */
export async function deleteDocument(roomId: number, docId: number): Promise<void> {
	await apiClient.delete(`/deal-rooms/${roomId}/documents/${docId}`);
}
