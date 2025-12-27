import { apiClient, type PaginatedResponse } from './client';

// ============================================================================
// Types
// ============================================================================

export type DocumentType = 'word' | 'spreadsheet' | 'presentation';
export type DocumentPermission = 'view' | 'comment' | 'edit' | 'owner';

export interface CollaborativeDocument {
	id: number;
	title: string;
	type: DocumentType;
	owner_id: number;
	parent_folder_id: number | null;
	is_template: boolean;
	is_starred?: boolean;
	current_version: number;
	html_snapshot?: string | null;
	text_content?: string | null;
	last_edited_at: string | null;
	last_edited_by: number | null;
	created_at: string;
	updated_at: string;
	owner?: DocumentUser;
	collaborators?: DocumentCollaborator[];
	user_permission?: DocumentPermission;
	version_count?: number;
	comment_count?: number;
	unresolved_comments?: number;
}

export interface DocumentUser {
	id: number;
	name: string;
	email: string;
}

export interface DocumentCollaborator {
	id: number;
	document_id: number;
	user_id: number;
	permission: DocumentPermission;
	is_currently_viewing: boolean;
	last_active_at: string | null;
	cursor_position: CursorPosition | null;
	user?: DocumentUser;
	user_name?: string;
	user_email?: string;
}

export interface CursorPosition {
	line: number;
	column: number;
	selection?: {
		start: { line: number; column: number };
		end: { line: number; column: number };
	};
}

export interface DocumentFolder {
	id: number;
	name: string;
	parent_id: number | null;
	owner_id: number;
	color: string | null;
	created_at: string;
	updated_at: string;
	children?: DocumentFolder[];
	document_count?: number;
}

export interface DocumentVersion {
	id: number;
	document_id: number;
	version_number: number;
	label: string | null;
	is_auto_save: boolean;
	created_by: number;
	created_at: string;
	creator?: DocumentUser;
	created_by_name?: string;
}

export interface DocumentComment {
	id: number;
	document_id: number;
	thread_id: number | null;
	user_id: number;
	content: string;
	selection_range: SelectionRange | null;
	is_resolved: boolean;
	resolved_by: number | null;
	resolved_at: string | null;
	created_at: string;
	updated_at: string;
	user?: DocumentUser;
	user_name?: string;
	user_email?: string;
	replies?: DocumentComment[];
	reply_count?: number;
}

export interface SelectionRange {
	start: { line: number; column: number };
	end: { line: number; column: number };
}

export interface ShareSettings {
	enabled: boolean;
	token?: string;
	permission?: DocumentPermission;
	has_password?: boolean;
	expires_at?: string;
	is_expired?: boolean;
	allow_download?: boolean;
	require_email?: boolean;
	share_url?: string;
}

export interface FolderContents {
	folder: DocumentFolder | null;
	path: DocumentFolder[];
	subfolders: DocumentFolder[];
	documents: CollaborativeDocument[];
}

export interface DocumentStatistics {
	total_documents: number;
	documents_by_type: Record<DocumentType, number>;
	total_folders: number;
	shared_with_me: number;
	recent_activity: number;
}

// ============================================================================
// Request/Response Types
// ============================================================================

export interface CreateDocumentData {
	title: string;
	type: DocumentType;
	folder_id?: number;
}

export interface UpdateDocumentData {
	title?: string;
	folder_id?: number | null;
	is_template?: boolean;
	html_snapshot?: string;
	text_content?: string;
}

export interface CreateFolderData {
	name: string;
	parent_id?: number;
	color?: string;
}

export interface UpdateFolderData {
	name?: string;
	parent_id?: number | null;
	color?: string;
}

export interface AddCollaboratorData {
	user_id: number;
	permission: 'view' | 'comment' | 'edit';
}

export interface EnableLinkSharingData {
	permission: 'view' | 'comment' | 'edit';
	password?: string;
	expires_at?: string;
	allow_download?: boolean;
	require_email?: boolean;
}

export interface CreateCommentData {
	content: string;
	thread_id?: number;
	selection_range?: SelectionRange;
}

export interface SyncState {
	document_id: number;
	yjs_state: string;
	version: number;
	last_edited_at: string | null;
	last_edited_by: number | null;
}

export interface JoinSessionResult {
	document_id: number;
	user_id: number;
	user_name: string;
	user_color: string;
	permission: DocumentPermission;
	yjs_state: string;
	version: number;
	active_collaborators: DocumentCollaborator[];
}

// ============================================================================
// Documents API
// ============================================================================

export const documentsApi = {
	// Document CRUD
	list: (params?: {
		type?: DocumentType;
		folder_id?: number;
		is_template?: boolean;
		search?: string;
		sort_by?: string;
		sort_direction?: 'asc' | 'desc';
		per_page?: number;
		page?: number;
	}) => apiClient.get<PaginatedResponse<CollaborativeDocument>>('/documents', { params }),

	get: (id: number) =>
		apiClient.get<{ data: CollaborativeDocument }>(`/documents/${id}`),

	create: (data: CreateDocumentData) =>
		apiClient.post<{ message: string; data: CollaborativeDocument }>('/documents', data),

	update: (id: number, data: UpdateDocumentData) =>
		apiClient.put<{ message: string; data: CollaborativeDocument }>(`/documents/${id}`, data),

	delete: (id: number) =>
		apiClient.delete<{ message: string }>(`/documents/${id}`),

	duplicate: (id: number, title?: string) =>
		apiClient.post<{ message: string; data: CollaborativeDocument }>(`/documents/${id}/duplicate`, { title }),

	getRecent: (limit?: number) =>
		apiClient.get<{ data: CollaborativeDocument[] }>('/documents/recent', { params: { limit } }),

	search: (query: string, limit?: number) =>
		apiClient.get<{ data: CollaborativeDocument[] }>('/documents/search', { params: { q: query, limit } }),

	getStatistics: () =>
		apiClient.get<{ data: DocumentStatistics }>('/documents/statistics'),

	// Templates
	listTemplates: (type?: DocumentType) =>
		apiClient.get<{ data: CollaborativeDocument[] }>('/documents/templates', { params: { type } }),

	createFromTemplate: (templateId: number, title: string, folderId?: number) =>
		apiClient.post<{ message: string; data: CollaborativeDocument }>('/documents/from-template', {
			template_id: templateId,
			title,
			folder_id: folderId,
		}),
};

// ============================================================================
// Sync API (Real-time collaboration)
// ============================================================================

export const documentSyncApi = {
	getState: (documentId: number) =>
		apiClient.get<{ data: SyncState }>(`/documents/${documentId}/state`),

	sync: (documentId: number, update: string) =>
		apiClient.post<{ data: { success: boolean; document_id: number; version: number } }>(
			`/documents/${documentId}/sync`,
			{ update }
		),

	join: (documentId: number) =>
		apiClient.post<{ data: JoinSessionResult }>(`/documents/${documentId}/join`),

	leave: (documentId: number) =>
		apiClient.post<{ data: { success: boolean; document_id: number } }>(`/documents/${documentId}/leave`),

	updateCursor: (documentId: number, position: CursorPosition) =>
		apiClient.post<{ data: { success: boolean } }>(`/documents/${documentId}/cursor`, position),
};

// ============================================================================
// Collaborators API
// ============================================================================

export const documentCollaboratorsApi = {
	list: (documentId: number) =>
		apiClient.get<{ data: DocumentCollaborator[] }>(`/documents/${documentId}/collaborators`),

	getActive: (documentId: number) =>
		apiClient.get<{ data: DocumentCollaborator[] }>(`/documents/${documentId}/collaborators/active`),

	add: (documentId: number, data: AddCollaboratorData) =>
		apiClient.post<{ message: string; data: DocumentCollaborator }>(
			`/documents/${documentId}/collaborators`,
			data
		),

	update: (documentId: number, userId: number, permission: 'view' | 'comment' | 'edit') =>
		apiClient.put<{ message: string; data: DocumentCollaborator }>(
			`/documents/${documentId}/collaborators/${userId}`,
			{ permission }
		),

	remove: (documentId: number, userId: number) =>
		apiClient.delete<{ message: string }>(`/documents/${documentId}/collaborators/${userId}`),

	// Link sharing
	getLinkSharing: (documentId: number) =>
		apiClient.get<{ data: ShareSettings }>(`/documents/${documentId}/share-link`),

	enableLinkSharing: (documentId: number, data: EnableLinkSharingData) =>
		apiClient.post<{ message: string; data: ShareSettings }>(`/documents/${documentId}/share-link`, data),

	updateLinkSharing: (documentId: number, data: Partial<EnableLinkSharingData>) =>
		apiClient.put<{ message: string; data: ShareSettings }>(`/documents/${documentId}/share-link`, data),

	disableLinkSharing: (documentId: number) =>
		apiClient.delete<{ message: string }>(`/documents/${documentId}/share-link`),

	regenerateToken: (documentId: number) =>
		apiClient.post<{ message: string; data: { token: string; share_url: string } }>(
			`/documents/${documentId}/share-link/regenerate`
		),
};

// ============================================================================
// Versions API
// ============================================================================

export const documentVersionsApi = {
	list: (documentId: number, params?: { per_page?: number; page?: number; include_auto_saves?: boolean }) =>
		apiClient.get<PaginatedResponse<DocumentVersion>>(`/documents/${documentId}/versions`, { params }),

	get: (documentId: number, versionNumber: number) =>
		apiClient.get<{ data: DocumentVersion }>(`/documents/${documentId}/versions/${versionNumber}`),

	create: (documentId: number, label: string) =>
		apiClient.post<{ message: string; data: DocumentVersion }>(`/documents/${documentId}/versions`, { label }),

	restore: (documentId: number, versionNumber: number) =>
		apiClient.post<{ message: string; data: CollaborativeDocument }>(
			`/documents/${documentId}/restore/${versionNumber}`
		),

	compare: (documentId: number, fromVersion: number, toVersion: number) =>
		apiClient.get<{ data: { from: DocumentVersion; to: DocumentVersion } }>(
			`/documents/${documentId}/versions/compare`,
			{ params: { from_version: fromVersion, to_version: toVersion } }
		),
};

// ============================================================================
// Comments API
// ============================================================================

export const documentCommentsApi = {
	list: (documentId: number, includeResolved?: boolean) =>
		apiClient.get<{ data: DocumentComment[] }>(`/documents/${documentId}/comments`, {
			params: { include_resolved: includeResolved },
		}),

	get: (documentId: number, commentId: number) =>
		apiClient.get<{ data: { thread: DocumentComment; replies: DocumentComment[] } }>(
			`/documents/${documentId}/comments/${commentId}`
		),

	create: (documentId: number, data: CreateCommentData) =>
		apiClient.post<{ message: string; data: DocumentComment }>(`/documents/${documentId}/comments`, data),

	update: (documentId: number, commentId: number, content: string) =>
		apiClient.put<{ message: string; data: DocumentComment }>(
			`/documents/${documentId}/comments/${commentId}`,
			{ content }
		),

	delete: (documentId: number, commentId: number) =>
		apiClient.delete<{ message: string }>(`/documents/${documentId}/comments/${commentId}`),

	resolve: (documentId: number, commentId: number) =>
		apiClient.post<{ message: string; data: DocumentComment }>(
			`/documents/${documentId}/comments/${commentId}/resolve`
		),

	reopen: (documentId: number, commentId: number) =>
		apiClient.post<{ message: string; data: DocumentComment }>(
			`/documents/${documentId}/comments/${commentId}/reopen`
		),
};

// ============================================================================
// Folders API
// ============================================================================

export const documentFoldersApi = {
	getTree: () =>
		apiClient.get<{ data: DocumentFolder[] }>('/document-folders/tree'),

	getContents: (folderId?: number) =>
		apiClient.get<{ data: FolderContents }>('/document-folders/contents', {
			params: { folder_id: folderId },
		}),

	create: (data: CreateFolderData) =>
		apiClient.post<{ message: string; data: DocumentFolder }>('/document-folders', data),

	update: (id: number, data: UpdateFolderData) =>
		apiClient.put<{ message: string; data: DocumentFolder }>(`/document-folders/${id}`, data),

	delete: (id: number) =>
		apiClient.delete<{ message: string }>(`/document-folders/${id}`),
};

// ============================================================================
// Public Share Link Access
// ============================================================================

export const publicDocumentApi = {
	access: (token: string, password?: string) =>
		apiClient.post<{
			data: {
				document_id: number;
				title: string;
				type: DocumentType;
				permission: DocumentPermission;
				allow_download: boolean;
				yjs_state: string;
			};
		}>(`/d/${token}`, { password }),
};
