<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Upload, ImageIcon, X, Loader2, AlertCircle } from 'lucide-svelte';
	import type { FieldSettings } from '$lib/api/modules';
	import { uploadFile, deleteFile, formatFileSize } from '$lib/api/files';

	interface ImageInfo {
		id?: string;
		name: string;
		size: number;
		type: string;
		url: string;
		path?: string;
		isUploading?: boolean;
		error?: string;
	}

	interface Props {
		value: ImageInfo[];
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: Partial<FieldSettings>;
		moduleApiName?: string;
		fieldApiName?: string;
		onchange: (value: ImageInfo[]) => void;
	}

	let {
		value = $bindable([]),
		error,
		disabled = false,
		placeholder,
		required,
		settings,
		moduleApiName,
		fieldApiName,
		onchange
	}: Props = $props();

	let fileInput: HTMLInputElement;
	let uploadingCount = $state(0);

	const maxImages = settings?.max_files || 1;
	const maxSizeMB = settings?.max_file_size || 5;
	const acceptedTypes = 'image/jpeg,image/png,image/gif,image/webp';

	async function handleFileSelect(event: Event) {
		const target = event.target as HTMLInputElement;
		const files = target.files;

		if (!files || files.length === 0) return;

		const filesToUpload: File[] = [];

		for (let i = 0; i < files.length && filesToUpload.length + value.length < maxImages; i++) {
			const file = files[i];

			// Validate image type
			if (!file.type.startsWith('image/')) {
				alert(`${file.name} is not a valid image file.`);
				continue;
			}

			// Check file size
			if (file.size > maxSizeMB * 1024 * 1024) {
				alert(`Image ${file.name} is too large. Maximum size is ${maxSizeMB}MB.`);
				continue;
			}

			filesToUpload.push(file);
		}

		// Reset input first
		target.value = '';

		// Upload files sequentially
		for (const file of filesToUpload) {
			await uploadSingleImage(file);
		}
	}

	async function uploadSingleImage(file: File) {
		// Create local preview URL
		const previewUrl = URL.createObjectURL(file);
		const tempId = crypto.randomUUID();

		const placeholder: ImageInfo = {
			id: tempId,
			name: file.name,
			size: file.size,
			type: file.type,
			url: previewUrl,
			isUploading: true
		};

		value = [...value, placeholder];
		uploadingCount++;

		try {
			const uploaded = await uploadFile(file, {
				type: 'image',
				module: moduleApiName,
				field: fieldApiName
			});

			// Revoke preview URL
			URL.revokeObjectURL(previewUrl);

			// Replace placeholder with uploaded image info
			value = value.map((img) =>
				img.id === tempId
					? {
							id: uploaded.id,
							name: uploaded.name,
							size: uploaded.size,
							type: uploaded.mime_type,
							url: uploaded.url,
							path: uploaded.path,
							isUploading: false
						}
					: img
			);
			onchange(value);
		} catch (err) {
			// Keep preview URL for display, mark as error
			value = value.map((img) =>
				img.id === tempId
					? {
							...img,
							isUploading: false,
							error: err instanceof Error ? err.message : 'Upload failed'
						}
					: img
			);
		} finally {
			uploadingCount--;
		}
	}

	async function removeImage(index: number) {
		const image = value[index];

		// If image has a server path, delete from server
		if (image.path) {
			try {
				await deleteFile(image.path);
			} catch (err) {
				console.error('Failed to delete image from server:', err);
				// Continue removing from UI anyway
			}
		}

		// Revoke blob URL if exists
		if (image.url && image.url.startsWith('blob:')) {
			URL.revokeObjectURL(image.url);
		}

		value = value.filter((_, i) => i !== index);
		onchange(value);
	}
</script>

<div class="space-y-3">
	<input
		bind:this={fileInput}
		type="file"
		onchange={handleFileSelect}
		accept={acceptedTypes}
		multiple={maxImages > 1}
		disabled={disabled || uploadingCount > 0}
		class="hidden"
	/>

	<Button
		type="button"
		variant="outline"
		disabled={disabled || value.length >= maxImages || uploadingCount > 0}
		onclick={() => fileInput?.click()}
		class={`w-full ${error ? 'border-destructive' : ''} ${value.length >= maxImages ? 'opacity-50' : ''}`}
	>
		{#if uploadingCount > 0}
			<Loader2 class="mr-2 h-4 w-4 animate-spin" />
			Uploading...
		{:else}
			<Upload class="mr-2 h-4 w-4" />
			{value.length === 0 ? 'Upload Images' : `Upload More (${value.length}/${maxImages})`}
		{/if}
	</Button>

	{#if value.length > 0}
		<div class="grid grid-cols-2 gap-3 md:grid-cols-3">
			{#each value as image, i}
				<div
					class="group relative aspect-square overflow-hidden rounded-lg border {image.error
						? 'border-destructive'
						: ''} bg-muted"
				>
					<img
						src={image.url}
						alt={image.name}
						class="h-full w-full object-cover transition-transform group-hover:scale-105 {image.isUploading
							? 'opacity-50'
							: ''}"
					/>

					{#if image.isUploading}
						<div class="absolute inset-0 flex items-center justify-center bg-black/40">
							<div class="flex flex-col items-center gap-2">
								<Loader2 class="h-6 w-6 animate-spin text-white" />
								<span class="text-xs text-white">Uploading...</span>
							</div>
						</div>
					{:else if image.error}
						<div class="absolute inset-0 flex items-center justify-center bg-destructive/20">
							<div class="flex flex-col items-center gap-2 p-2">
								<AlertCircle class="h-6 w-6 text-destructive" />
								<span class="text-center text-xs text-destructive">{image.error}</span>
								<Button
									type="button"
									variant="destructive"
									size="sm"
									onclick={() => removeImage(i)}
								>
									<X class="mr-1 h-3 w-3" />
									Remove
								</Button>
							</div>
						</div>
					{:else}
						<div
							class="absolute inset-0 bg-black/60 opacity-0 transition-opacity group-hover:opacity-100"
						>
							<div class="flex h-full flex-col items-center justify-center gap-2 p-2">
								<p class="truncate text-xs font-medium text-white">{image.name}</p>
								<p class="text-xs text-white/80">{formatFileSize(image.size)}</p>
								<Button
									type="button"
									variant="destructive"
									size="sm"
									{disabled}
									onclick={() => removeImage(i)}
									class="mt-2"
								>
									<X class="mr-1 h-3 w-3" />
									Remove
								</Button>
							</div>
						</div>
					{/if}
				</div>
			{/each}
		</div>
	{/if}

	<div class="text-xs text-muted-foreground">
		Max {maxImages} image{maxImages !== 1 ? 's' : ''} • Up to {maxSizeMB}MB each • JPEG, PNG, GIF,
		WebP
	</div>
</div>
