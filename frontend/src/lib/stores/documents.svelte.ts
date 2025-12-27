import {
	documentsApi,
	documentFoldersApi,
	documentSyncApi,
	documentCollaboratorsApi,
	type CollaborativeDocument,
	type DocumentFolder,
	type DocumentCollaborator,
	type DocumentType,
	type FolderContents,
	type DocumentStatistics,
	type ShareSettings,
	type JoinSessionResult,
	type CursorPosition
} from '$lib/api/collaborative-documents';

// ============================================================================
// Document Browser Store
// ============================================================================

interface DocumentBrowserState {
	documents: CollaborativeDocument[];
	folders: DocumentFolder[];
	currentFolder: DocumentFolder | null;
	folderPath: DocumentFolder[];
	loading: boolean;
	error: string | null;
	viewMode: 'grid' | 'list';
	sortBy: string;
	sortDirection: 'asc' | 'desc';
	searchQuery: string;
	filterType: DocumentType | null;
}

class DocumentBrowserStore {
	private _documents = $state<CollaborativeDocument[]>([]);
	private _folders = $state<DocumentFolder[]>([]);
	private _currentFolder = $state<DocumentFolder | null>(null);
	private _folderPath = $state<DocumentFolder[]>([]);
	private _loading = $state(false);
	private _error = $state<string | null>(null);
	private _viewMode = $state<'grid' | 'list'>('grid');
	private _sortBy = $state('updated_at');
	private _sortDirection = $state<'asc' | 'desc'>('desc');
	private _searchQuery = $state('');
	private _filterType = $state<DocumentType | null>(null);
	private _statistics = $state<DocumentStatistics | null>(null);
	private _recentDocuments = $state<CollaborativeDocument[]>([]);
	private _folderTree = $state<DocumentFolder[]>([]);

	// Getters
	get documents() { return this._documents; }
	get folders() { return this._folders; }
	get currentFolder() { return this._currentFolder; }
	get folderPath() { return this._folderPath; }
	get loading() { return this._loading; }
	get error() { return this._error; }
	get viewMode() { return this._viewMode; }
	get sortBy() { return this._sortBy; }
	get sortDirection() { return this._sortDirection; }
	get searchQuery() { return this._searchQuery; }
	get filterType() { return this._filterType; }
	get statistics() { return this._statistics; }
	get recentDocuments() { return this._recentDocuments; }
	get folderTree() { return this._folderTree; }

	// Actions
	async loadFolderContents(folderId?: number) {
		this._loading = true;
		this._error = null;

		try {
			const response = await documentFoldersApi.getContents(folderId);
			const contents = response.data;

			this._documents = contents.documents;
			this._folders = contents.subfolders;
			this._currentFolder = contents.folder;
			this._folderPath = contents.path;
		} catch (err) {
			this._error = err instanceof Error ? err.message : 'Failed to load folder contents';
		} finally {
			this._loading = false;
		}
	}

	async loadFolderTree() {
		try {
			const response = await documentFoldersApi.getTree();
			this._folderTree = response.data;
		} catch (err) {
			console.error('Failed to load folder tree:', err);
		}
	}

	async loadRecentDocuments(limit = 10) {
		try {
			const response = await documentsApi.getRecent(limit);
			this._recentDocuments = response.data;
		} catch (err) {
			console.error('Failed to load recent documents:', err);
		}
	}

	async loadStatistics() {
		try {
			const response = await documentsApi.getStatistics();
			this._statistics = response.data;
		} catch (err) {
			console.error('Failed to load statistics:', err);
		}
	}

	async searchDocuments(query: string) {
		if (query.length < 2) {
			this._searchQuery = '';
			return;
		}

		this._searchQuery = query;
		this._loading = true;

		try {
			const response = await documentsApi.search(query);
			this._documents = response.data;
			this._folders = [];
			this._currentFolder = null;
			this._folderPath = [];
		} catch (err) {
			this._error = err instanceof Error ? err.message : 'Search failed';
		} finally {
			this._loading = false;
		}
	}

	async createDocument(title: string, type: DocumentType, folderId?: number) {
		try {
			const response = await documentsApi.create({
				title,
				type,
				folder_id: folderId ?? this._currentFolder?.id
			});
			this._documents = [response.data, ...this._documents];
			return response.data;
		} catch (err) {
			throw err;
		}
	}

	async createFolder(name: string, color?: string) {
		try {
			const response = await documentFoldersApi.create({
				name,
				parent_id: this._currentFolder?.id,
				color
			});
			this._folders = [response.data, ...this._folders];
			await this.loadFolderTree();
			return response.data;
		} catch (err) {
			throw err;
		}
	}

	async deleteDocument(id: number) {
		try {
			await documentsApi.delete(id);
			this._documents = this._documents.filter(d => d.id !== id);
		} catch (err) {
			throw err;
		}
	}

	async deleteFolder(id: number) {
		try {
			await documentFoldersApi.delete(id);
			this._folders = this._folders.filter(f => f.id !== id);
			await this.loadFolderTree();
		} catch (err) {
			throw err;
		}
	}

	async duplicateDocument(id: number, title?: string) {
		try {
			const response = await documentsApi.duplicate(id, title);
			this._documents = [response.data, ...this._documents];
			return response.data;
		} catch (err) {
			throw err;
		}
	}

	setViewMode(mode: 'grid' | 'list') {
		this._viewMode = mode;
		if (typeof window !== 'undefined') {
			localStorage.setItem('document_view_mode', mode);
		}
	}

	setSortBy(field: string) {
		this._sortBy = field;
	}

	setSortDirection(direction: 'asc' | 'desc') {
		this._sortDirection = direction;
	}

	setFilterType(type: DocumentType | null) {
		this._filterType = type;
	}

	clearSearch() {
		this._searchQuery = '';
	}

	clearError() {
		this._error = null;
	}

	// Initialize from localStorage
	initialize() {
		if (typeof window !== 'undefined') {
			const savedViewMode = localStorage.getItem('document_view_mode');
			if (savedViewMode === 'grid' || savedViewMode === 'list') {
				this._viewMode = savedViewMode;
			}
		}
	}
}

export const documentBrowserStore = new DocumentBrowserStore();

// ============================================================================
// Document Editor Store
// ============================================================================

interface ActiveCollaborator {
	id: number;
	name: string;
	color: string;
	permission: string;
	cursor?: CursorPosition;
}

class DocumentEditorStore {
	private _document = $state<CollaborativeDocument | null>(null);
	private _loading = $state(false);
	private _saving = $state(false);
	private _error = $state<string | null>(null);
	private _yjsState = $state<string | null>(null);
	private _version = $state(0);
	private _activeCollaborators = $state<ActiveCollaborator[]>([]);
	private _isConnected = $state(false);
	private _shareSettings = $state<ShareSettings | null>(null);
	private _collaborators = $state<DocumentCollaborator[]>([]);
	private _hasUnsavedChanges = $state(false);
	private _lastSavedAt = $state<Date | null>(null);

	// Getters
	get document() { return this._document; }
	get loading() { return this._loading; }
	get saving() { return this._saving; }
	get error() { return this._error; }
	get yjsState() { return this._yjsState; }
	get version() { return this._version; }
	get activeCollaborators() { return this._activeCollaborators; }
	get isConnected() { return this._isConnected; }
	get shareSettings() { return this._shareSettings; }
	get collaborators() { return this._collaborators; }
	get hasUnsavedChanges() { return this._hasUnsavedChanges; }
	get lastSavedAt() { return this._lastSavedAt; }

	// Actions
	async loadDocument(id: number) {
		this._loading = true;
		this._error = null;

		try {
			const response = await documentsApi.get(id);
			this._document = response.data;
			return response.data;
		} catch (err) {
			this._error = err instanceof Error ? err.message : 'Failed to load document';
			throw err;
		} finally {
			this._loading = false;
		}
	}

	async joinSession(documentId: number): Promise<JoinSessionResult> {
		try {
			const response = await documentSyncApi.join(documentId);
			const result = response.data;

			this._yjsState = result.yjs_state;
			this._version = result.version;
			this._activeCollaborators = result.active_collaborators.map(c => ({
				id: c.user_id,
				name: c.user_name || 'Unknown',
				color: '#4ECDC4',
				permission: c.permission,
				cursor: c.cursor_position || undefined
			}));
			this._isConnected = true;

			return result;
		} catch (err) {
			this._error = err instanceof Error ? err.message : 'Failed to join session';
			throw err;
		}
	}

	async leaveSession(documentId: number) {
		try {
			await documentSyncApi.leave(documentId);
			this._isConnected = false;
			this._activeCollaborators = [];
		} catch (err) {
			console.error('Failed to leave session:', err);
		}
	}

	async syncUpdate(documentId: number, update: string) {
		try {
			const response = await documentSyncApi.sync(documentId, update);
			this._version = response.data.version;
			this._hasUnsavedChanges = false;
			this._lastSavedAt = new Date();
		} catch (err) {
			this._error = err instanceof Error ? err.message : 'Sync failed';
			throw err;
		}
	}

	async saveContent(documentId: number, htmlContent: string, textContent: string = '') {
		this._saving = true;
		try {
			await documentsApi.update(documentId, {
				html_snapshot: htmlContent,
				text_content: textContent
			});
			this._hasUnsavedChanges = false;
			this._lastSavedAt = new Date();
			// Update local document state
			if (this._document) {
				this._document = {
					...this._document,
					html_snapshot: htmlContent,
					text_content: textContent
				};
			}
		} catch (err) {
			this._error = err instanceof Error ? err.message : 'Save failed';
			throw err;
		} finally {
			this._saving = false;
		}
	}

	async updateCursor(documentId: number, position: CursorPosition) {
		try {
			await documentSyncApi.updateCursor(documentId, position);
		} catch (err) {
			console.error('Failed to update cursor:', err);
		}
	}

	async loadCollaborators(documentId: number) {
		try {
			const response = await documentCollaboratorsApi.list(documentId);
			this._collaborators = response.data;
		} catch (err) {
			console.error('Failed to load collaborators:', err);
		}
	}

	async loadShareSettings(documentId: number) {
		try {
			const response = await documentCollaboratorsApi.getLinkSharing(documentId);
			this._shareSettings = response.data;
		} catch (err) {
			console.error('Failed to load share settings:', err);
		}
	}

	async addCollaborator(documentId: number, userId: number, permission: 'view' | 'comment' | 'edit') {
		try {
			const response = await documentCollaboratorsApi.add(documentId, { user_id: userId, permission });
			this._collaborators = [...this._collaborators, response.data];
			return response.data;
		} catch (err) {
			throw err;
		}
	}

	async removeCollaborator(documentId: number, userId: number) {
		try {
			await documentCollaboratorsApi.remove(documentId, userId);
			this._collaborators = this._collaborators.filter(c => c.user_id !== userId);
		} catch (err) {
			throw err;
		}
	}

	async updateCollaboratorPermission(documentId: number, userId: number, permission: 'view' | 'comment' | 'edit') {
		try {
			const response = await documentCollaboratorsApi.update(documentId, userId, permission);
			this._collaborators = this._collaborators.map(c =>
				c.user_id === userId ? response.data : c
			);
		} catch (err) {
			throw err;
		}
	}

	async enableLinkSharing(
		documentId: number,
		permission: 'view' | 'comment' | 'edit',
		options?: { password?: string; expires_at?: string; allow_download?: boolean }
	) {
		try {
			const response = await documentCollaboratorsApi.enableLinkSharing(documentId, {
				permission,
				...options
			});
			this._shareSettings = response.data;
			return response.data;
		} catch (err) {
			throw err;
		}
	}

	async disableLinkSharing(documentId: number) {
		try {
			await documentCollaboratorsApi.disableLinkSharing(documentId);
			this._shareSettings = { enabled: false };
		} catch (err) {
			throw err;
		}
	}

	// Handle incoming WebSocket events
	handleCollaboratorJoined(data: { user_id: number; user_name: string; user_color: string }) {
		const existing = this._activeCollaborators.find(c => c.id === data.user_id);
		if (!existing) {
			this._activeCollaborators = [
				...this._activeCollaborators,
				{
					id: data.user_id,
					name: data.user_name,
					color: data.user_color,
					permission: 'view'
				}
			];
		}
	}

	handleCollaboratorLeft(data: { user_id: number }) {
		this._activeCollaborators = this._activeCollaborators.filter(c => c.id !== data.user_id);
	}

	handleDocumentUpdated(data: { yjs_update: string; user_id: number }) {
		// The actual Y.js merge will be handled by the editor component
		// This just updates the version and notifies the component
		this._version++;
	}

	handleCursorUpdate(data: { user_id: number; position: CursorPosition }) {
		this._activeCollaborators = this._activeCollaborators.map(c =>
			c.id === data.user_id ? { ...c, cursor: data.position } : c
		);
	}

	setHasUnsavedChanges(value: boolean) {
		this._hasUnsavedChanges = value;
	}

	setSaving(value: boolean) {
		this._saving = value;
	}

	clearDocument() {
		this._document = null;
		this._yjsState = null;
		this._version = 0;
		this._activeCollaborators = [];
		this._isConnected = false;
		this._shareSettings = null;
		this._collaborators = [];
		this._hasUnsavedChanges = false;
		this._lastSavedAt = null;
		this._error = null;
	}

	clearError() {
		this._error = null;
	}
}

export const documentEditorStore = new DocumentEditorStore();
