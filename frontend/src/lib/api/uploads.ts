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

export interface UploadResponse {
	success: boolean;
	data: UploadedFile;
}

export interface MultiUploadResponse {
	success: boolean;
	data: {
		uploaded: UploadedFile[];
		errors: Array<{ index: number; name: string; error: string }>;
		total: number;
		successful: number;
		failed: number;
	};
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
export async function uploadFile(
	file: File,
	options?: {
		type?: 'file' | 'image';
		module?: string;
		field?: string;
		onProgress?: (progress: number) => void;
	}
): Promise<UploadedFile> {
	const formData = new FormData();
	formData.append('file', file);

	if (options?.type) {
		formData.append('type', options.type);
	}
	if (options?.module) {
		formData.append('module', options.module);
	}
	if (options?.field) {
		formData.append('field', options.field);
	}

	const url = `${getBaseUrl()}/upload`;

	// Use XMLHttpRequest for progress tracking
	if (options?.onProgress) {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();

			xhr.upload.addEventListener('progress', (e) => {
				if (e.lengthComputable) {
					const progress = Math.round((e.loaded / e.total) * 100);
					options.onProgress!(progress);
				}
			});

			xhr.addEventListener('load', () => {
				if (xhr.status >= 200 && xhr.status < 300) {
					const response: UploadResponse = JSON.parse(xhr.responseText);
					if (response.success) {
						resolve(response.data);
					} else {
						reject(new Error('Upload failed'));
					}
				} else {
					try {
						const error = JSON.parse(xhr.responseText);
						reject(new Error(error.message || 'Upload failed'));
					} catch {
						reject(new Error('Upload failed'));
					}
				}
			});

			xhr.addEventListener('error', () => {
				reject(new Error('Network error during upload'));
			});

			xhr.open('POST', url);

			const headers = getAuthHeaders();
			Object.entries(headers).forEach(([key, value]) => {
				xhr.setRequestHeader(key, value);
			});

			xhr.send(formData);
		});
	}

	// Simple fetch for non-progress uploads
	const response = await fetch(url, {
		method: 'POST',
		headers: getAuthHeaders(),
		body: formData
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Upload failed' }));
		throw new Error(error.message || 'Upload failed');
	}

	const data: UploadResponse = await response.json();
	return data.data;
}

/**
 * Upload multiple files
 */
export async function uploadMultipleFiles(
	files: File[],
	options?: {
		type?: 'file' | 'image';
		module?: string;
		field?: string;
	}
): Promise<MultiUploadResponse['data']> {
	const formData = new FormData();

	files.forEach((file) => {
		formData.append('files[]', file);
	});

	if (options?.type) {
		formData.append('type', options.type);
	}
	if (options?.module) {
		formData.append('module', options.module);
	}
	if (options?.field) {
		formData.append('field', options.field);
	}

	const url = `${getBaseUrl()}/upload-multiple`;

	const response = await fetch(url, {
		method: 'POST',
		headers: getAuthHeaders(),
		body: formData
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Upload failed' }));
		throw new Error(error.message || 'Upload failed');
	}

	const data: MultiUploadResponse = await response.json();
	return data.data;
}

/**
 * Upload an image specifically (with validation)
 */
export async function uploadImage(
	file: File,
	options?: {
		module?: string;
		field?: string;
		onProgress?: (progress: number) => void;
	}
): Promise<UploadedFile> {
	// Validate image type
	if (!file.type.startsWith('image/')) {
		throw new Error('File must be an image');
	}

	const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	if (!validTypes.includes(file.type)) {
		throw new Error('Invalid image type. Supported: JPEG, PNG, GIF, WebP');
	}

	// Validate size (10MB max for images)
	const maxSize = 10 * 1024 * 1024;
	if (file.size > maxSize) {
		throw new Error('Image size must be less than 10MB');
	}

	return uploadFile(file, {
		type: 'image',
		module: options?.module,
		field: options?.field,
		onProgress: options?.onProgress
	});
}

/**
 * Delete a file
 */
export async function deleteFile(path: string): Promise<void> {
	const url = `${getBaseUrl()}/uploads`;

	const response = await fetch(url, {
		method: 'DELETE',
		headers: {
			'Content-Type': 'application/json',
			...getAuthHeaders()
		},
		body: JSON.stringify({ path })
	});

	if (!response.ok) {
		const error = await response.json().catch(() => ({ message: 'Delete failed' }));
		throw new Error(error.message || 'Delete failed');
	}
}

/**
 * Helper to create an object URL for local preview
 */
export function createPreviewUrl(file: File): string {
	return URL.createObjectURL(file);
}

/**
 * Helper to revoke an object URL
 */
export function revokePreviewUrl(url: string): void {
	URL.revokeObjectURL(url);
}
