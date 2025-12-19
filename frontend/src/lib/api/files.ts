/**
 * File Upload API Client
 *
 * Handles file uploads, deletions, and info retrieval
 */

import { browser } from '$app/environment';

export interface UploadedFile {
	id: string;
	name: string;
	filename: string;
	path: string;
	url: string;
	size: number;
	mime_type: string;
	extension: string;
}

export interface UploadOptions {
	type?: 'file' | 'image';
	module?: string;
	field?: string;
}

export interface MultiUploadResult {
	uploaded: UploadedFile[];
	errors: {
		index: number;
		name: string;
		error: string;
	}[];
	total: number;
	successful: number;
	failed: number;
}

export interface FileInfo {
	path: string;
	url: string;
	size: number;
	last_modified: number;
}

interface ApiResponse<T> {
	success: boolean;
	message?: string;
	data: T;
}

function getBaseUrl(): string {
	if (!browser) {
		return 'http://localhost:8000/api/v1';
	}
	return `${window.location.origin}/api/v1`;
}

function getAuthHeaders(): Record<string, string> {
	const headers: Record<string, string> = {};
	if (browser) {
		const token = localStorage.getItem('auth_token');
		if (token) {
			headers['Authorization'] = `Bearer ${token}`;
		}
	}
	return headers;
}

/**
 * Upload a single file
 */
export async function uploadFile(file: File, options: UploadOptions = {}): Promise<UploadedFile> {
	const formData = new FormData();
	formData.append('file', file);

	if (options.type) {
		formData.append('type', options.type);
	}
	if (options.module) {
		formData.append('module', options.module);
	}
	if (options.field) {
		formData.append('field', options.field);
	}

	const response = await fetch(`${getBaseUrl()}/files/upload`, {
		method: 'POST',
		headers: {
			...getAuthHeaders(),
			Accept: 'application/json'
			// Don't set Content-Type - browser will set it with boundary for multipart
		},
		body: formData
	});

	if (!response.ok) {
		const errorData = await response.json().catch(() => ({ message: response.statusText }));
		throw new Error(errorData.message || 'Failed to upload file');
	}

	const result: ApiResponse<UploadedFile> = await response.json();

	if (!result.success) {
		throw new Error(result.message || 'Failed to upload file');
	}

	return result.data;
}

/**
 * Upload multiple files
 */
export async function uploadMultipleFiles(
	files: File[],
	options: UploadOptions = {}
): Promise<MultiUploadResult> {
	const formData = new FormData();

	files.forEach((file) => {
		formData.append('files[]', file);
	});

	if (options.type) {
		formData.append('type', options.type);
	}
	if (options.module) {
		formData.append('module', options.module);
	}
	if (options.field) {
		formData.append('field', options.field);
	}

	const response = await fetch(`${getBaseUrl()}/files/upload-multiple`, {
		method: 'POST',
		headers: {
			...getAuthHeaders(),
			Accept: 'application/json'
		},
		body: formData
	});

	if (!response.ok) {
		const errorData = await response.json().catch(() => ({ message: response.statusText }));
		throw new Error(errorData.message || 'Failed to upload files');
	}

	const result: ApiResponse<MultiUploadResult> = await response.json();
	return result.data;
}

/**
 * Delete a file by path
 */
export async function deleteFile(path: string): Promise<void> {
	const response = await fetch(`${getBaseUrl()}/files/delete`, {
		method: 'POST',
		headers: {
			...getAuthHeaders(),
			'Content-Type': 'application/json',
			Accept: 'application/json'
		},
		body: JSON.stringify({ path })
	});

	if (!response.ok) {
		const errorData = await response.json().catch(() => ({ message: response.statusText }));
		throw new Error(errorData.message || 'Failed to delete file');
	}

	const result: ApiResponse<null> = await response.json();

	if (!result.success) {
		throw new Error(result.message || 'Failed to delete file');
	}
}

/**
 * Get file info by path
 */
export async function getFileInfo(path: string): Promise<FileInfo> {
	const response = await fetch(`${getBaseUrl()}/files/info`, {
		method: 'POST',
		headers: {
			...getAuthHeaders(),
			'Content-Type': 'application/json',
			Accept: 'application/json'
		},
		body: JSON.stringify({ path })
	});

	if (!response.ok) {
		const errorData = await response.json().catch(() => ({ message: response.statusText }));
		throw new Error(errorData.message || 'Failed to get file info');
	}

	const result: ApiResponse<FileInfo> = await response.json();

	if (!result.success) {
		throw new Error(result.message || 'Failed to get file info');
	}

	return result.data;
}

/**
 * Format file size for display
 */
export function formatFileSize(bytes: number): string {
	if (bytes === 0) return '0 Bytes';

	const k = 1024;
	const sizes = ['Bytes', 'KB', 'MB', 'GB'];
	const i = Math.floor(Math.log(bytes) / Math.log(k));

	return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Check if file type is allowed
 */
export function isAllowedFileType(file: File, allowedTypes: string[]): boolean {
	// Check by extension
	const extension = file.name.split('.').pop()?.toLowerCase();
	if (extension && allowedTypes.includes(`.${extension}`)) {
		return true;
	}

	// Check by mime type
	if (allowedTypes.includes(file.type)) {
		return true;
	}

	// Check by category (image/*, video/*, etc.)
	for (const type of allowedTypes) {
		if (type.endsWith('/*')) {
			const category = type.slice(0, -2);
			if (file.type.startsWith(category)) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Get file extension from name
 */
export function getFileExtension(filename: string): string {
	return filename.split('.').pop()?.toLowerCase() || '';
}

/**
 * Check if file is an image
 */
export function isImageFile(file: File | string): boolean {
	if (typeof file === 'string') {
		const ext = getFileExtension(file);
		return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext);
	}
	return file.type.startsWith('image/');
}
