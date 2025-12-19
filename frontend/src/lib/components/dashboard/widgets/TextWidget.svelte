<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { FileText } from 'lucide-svelte';

	interface Props {
		title: string;
		content?: string;
	}

	let { title, content = '' }: Props = $props();
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
				<!-- eslint-disable-next-line svelte/no-at-html-tags -->
				{@html content}
			</div>
		{:else}
			<p class="text-center text-sm text-muted-foreground">No content</p>
		{/if}
	</Card.Content>
</Card.Root>
