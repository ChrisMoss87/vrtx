<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { FileText } from 'lucide-svelte';
	import DOMPurify from 'isomorphic-dompurify';

	interface Props {
		title: string;
		content?: string;
	}

	let { title, content = '' }: Props = $props();

	/**
	 * Sanitize HTML content to prevent XSS attacks.
	 */
	function sanitizeHtml(html: string): string {
		return DOMPurify.sanitize(html, {
			ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'u', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code'],
			ALLOWED_ATTR: ['href', 'class', 'style', 'target'],
			ALLOW_DATA_ATTR: false,
		});
	}
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<FileText class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
	</Card.Header>
	<Card.Content>
		{#if content}
			<div class="prose prose-sm dark:prose-invert max-w-none">
				{@html sanitizeHtml(content)}
			</div>
		{:else}
			<p class="text-center text-sm text-muted-foreground">No content</p>
		{/if}
	</Card.Content>
</Card.Root>
