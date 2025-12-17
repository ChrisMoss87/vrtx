<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Globe, ExternalLink, Play, FileText, AlertTriangle } from 'lucide-svelte';

	type EmbedType = 'iframe' | 'video' | 'image';

	interface Props {
		title: string;
		data: {
			url: string;
			type?: EmbedType;
			allow_fullscreen?: boolean;
			aspect_ratio?: '16:9' | '4:3' | '1:1' | 'auto';
		} | null;
		config?: {
			url?: string;
			type?: EmbedType;
			allow_fullscreen?: boolean;
			aspect_ratio?: '16:9' | '4:3' | '1:1' | 'auto';
		};
		loading?: boolean;
	}

	let { title, data, config, loading = false }: Props = $props();

	// Use config or data
	const embedUrl = $derived(config?.url || data?.url || '');
	const embedType = $derived(config?.type || data?.type || detectType(embedUrl));
	const allowFullscreen = $derived(config?.allow_fullscreen ?? data?.allow_fullscreen ?? true);
	const aspectRatio = $derived(config?.aspect_ratio || data?.aspect_ratio || '16:9');

	let loadError = $state(false);
	let isLoaded = $state(false);

	function detectType(url: string): EmbedType {
		if (!url) return 'iframe';
		const lowerUrl = url.toLowerCase();

		// Video platforms
		if (
			lowerUrl.includes('youtube.com') ||
			lowerUrl.includes('youtu.be') ||
			lowerUrl.includes('vimeo.com') ||
			lowerUrl.includes('wistia.com') ||
			lowerUrl.includes('loom.com')
		) {
			return 'video';
		}

		// Image extensions
		if (/\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i.test(url)) {
			return 'image';
		}

		return 'iframe';
	}

	function getAspectClass(ratio: string): string {
		switch (ratio) {
			case '16:9':
				return 'aspect-video';
			case '4:3':
				return 'aspect-[4/3]';
			case '1:1':
				return 'aspect-square';
			default:
				return '';
		}
	}

	function getEmbedUrl(url: string): string {
		if (!url) return '';

		// Convert YouTube watch URLs to embed URLs
		if (url.includes('youtube.com/watch')) {
			const videoId = new URL(url).searchParams.get('v');
			if (videoId) return `https://www.youtube.com/embed/${videoId}`;
		}

		// Convert youtu.be URLs to embed URLs
		if (url.includes('youtu.be/')) {
			const videoId = url.split('youtu.be/')[1]?.split('?')[0];
			if (videoId) return `https://www.youtube.com/embed/${videoId}`;
		}

		// Convert Vimeo URLs to embed URLs
		if (url.includes('vimeo.com/') && !url.includes('player.vimeo.com')) {
			const videoId = url.split('vimeo.com/')[1]?.split('?')[0];
			if (videoId) return `https://player.vimeo.com/video/${videoId}`;
		}

		return url;
	}

	function handleLoad() {
		isLoaded = true;
		loadError = false;
	}

	function handleError() {
		loadError = true;
		isLoaded = false;
	}

	function openExternal() {
		if (embedUrl) {
			window.open(embedUrl, '_blank', 'noopener,noreferrer');
		}
	}

	const IconComponent = $derived.by(() => {
		switch (embedType) {
			case 'video':
				return Play;
			case 'image':
				return FileText;
			default:
				return Globe;
		}
	});
</script>

<Card.Root class="flex h-full flex-col">
	<Card.Header class="pb-2">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<IconComponent class="h-4 w-4 text-muted-foreground" />
				<Card.Title class="text-sm font-medium">{title}</Card.Title>
			</div>
			{#if embedUrl}
				<Button variant="ghost" size="icon" class="h-6 w-6" onclick={openExternal} title="Open in new tab">
					<ExternalLink class="h-3 w-3" />
				</Button>
			{/if}
		</div>
	</Card.Header>
	<Card.Content class="flex flex-1 flex-col overflow-hidden p-0">
		{#if loading}
			<div class="flex flex-1 items-center justify-center bg-muted">
				<div class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
			</div>
		{:else if !embedUrl}
			<div class="flex flex-1 flex-col items-center justify-center gap-2 p-4 text-center">
				<Globe class="h-8 w-8 text-muted-foreground" />
				<p class="text-sm text-muted-foreground">No URL configured</p>
				<p class="text-xs text-muted-foreground">Edit this widget to add a URL</p>
			</div>
		{:else if loadError}
			<div class="flex flex-1 flex-col items-center justify-center gap-2 p-4 text-center">
				<AlertTriangle class="h-8 w-8 text-destructive" />
				<p class="text-sm text-muted-foreground">Failed to load content</p>
				<Button variant="outline" size="sm" onclick={openExternal}>
					<ExternalLink class="mr-2 h-3 w-3" />
					Open in new tab
				</Button>
			</div>
		{:else if embedType === 'image'}
			<div class="relative flex flex-1 items-center justify-center overflow-hidden bg-muted {aspectRatio !== 'auto' ? getAspectClass(aspectRatio) : ''}">
				<img
					src={embedUrl}
					alt={title}
					class="h-full w-full object-contain"
					onload={handleLoad}
					onerror={handleError}
				/>
			</div>
		{:else}
			<div class="relative flex-1 {aspectRatio !== 'auto' ? getAspectClass(aspectRatio) : 'min-h-[200px]'}">
				{#if !isLoaded}
					<div class="absolute inset-0 flex items-center justify-center bg-muted">
						<div class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
					</div>
				{/if}
				<iframe
					src={getEmbedUrl(embedUrl)}
					{title}
					class="h-full w-full border-0"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
					allowfullscreen={allowFullscreen}
					loading="lazy"
					onload={handleLoad}
					onerror={handleError}
				></iframe>
			</div>
		{/if}
	</Card.Content>
</Card.Root>
