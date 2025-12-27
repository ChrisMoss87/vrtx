<script lang="ts">
	import { FileText, Table2, Presentation } from 'lucide-svelte';
	import type { DocumentType } from '$lib/api/collaborative-documents';

	interface Props {
		type: DocumentType;
		class?: string;
	}

	let { type, class: className = '' }: Props = $props();
</script>

{#if type === 'spreadsheet'}
	<Table2 class="text-green-500 {className}" />
{:else if type === 'presentation'}
	<Presentation class="text-orange-500 {className}" />
{:else}
	<FileText class="text-blue-500 {className}" />
{/if}
