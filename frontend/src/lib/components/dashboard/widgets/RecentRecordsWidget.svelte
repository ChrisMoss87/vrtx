<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Clock, ExternalLink } from 'lucide-svelte';
	import { formatDistanceToNow } from 'date-fns';
	import { goto } from '$app/navigation';

	interface Record {
		id: number;
		title: string;
		subtitle?: string;
		module_id: number;
		module_name: string;
		created_at: string;
		fields?: { label: string; value: string }[];
	}

	interface Props {
		title: string;
		data: {
			records: Record[];
			module_name?: string;
		} | null;
		loading?: boolean;
	}

	let { title, data, loading = false }: Props = $props();

	function formatDate(dateString: string): string {
		try {
			return formatDistanceToNow(new Date(dateString), { addSuffix: true });
		} catch {
			return dateString;
		}
	}

	function handleRecordClick(record: Record) {
		// Navigate to the record detail page
		goto(`/modules/${record.module_id}/records/${record.id}`);
	}
</script>

<Card.Root class="flex h-full flex-col">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<Clock class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
		{#if data?.module_name}
			<p class="text-xs text-muted-foreground">{data.module_name}</p>
		{/if}
	</Card.Header>
	<Card.Content class="flex-1 overflow-auto">
		{#if loading}
			<div class="space-y-3">
				{#each [1, 2, 3, 4, 5] as _}
					<div class="animate-pulse rounded-lg border p-3">
						<div class="h-4 w-3/4 rounded bg-muted"></div>
						<div class="mt-2 h-3 w-1/2 rounded bg-muted"></div>
					</div>
				{/each}
			</div>
		{:else if data?.records && data.records.length > 0}
			<div class="space-y-2">
				{#each data.records as record (record.id)}
					<button
						type="button"
						class="group w-full rounded-lg border p-3 text-left transition-colors hover:bg-muted/50"
						onclick={() => handleRecordClick(record)}
					>
						<div class="flex items-start justify-between gap-2">
							<div class="min-w-0 flex-1">
								<div class="flex items-center gap-2">
									<span class="truncate font-medium">{record.title}</span>
									<ExternalLink
										class="hidden h-3 w-3 flex-shrink-0 text-muted-foreground group-hover:block"
									/>
								</div>
								{#if record.subtitle}
									<div class="mt-0.5 truncate text-sm text-muted-foreground">
										{record.subtitle}
									</div>
								{/if}
							</div>
							<span class="flex-shrink-0 text-xs text-muted-foreground">
								{formatDate(record.created_at)}
							</span>
						</div>

						<!-- Additional fields -->
						{#if record.fields && record.fields.length > 0}
							<div class="mt-2 flex flex-wrap gap-2">
								{#each record.fields.slice(0, 3) as field}
									<span
										class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs"
									>
										<span class="text-muted-foreground">{field.label}:</span>
										<span class="ml-1 font-medium">{field.value}</span>
									</span>
								{/each}
							</div>
						{/if}
					</button>
				{/each}
			</div>
		{:else}
			<div class="py-8 text-center text-sm text-muted-foreground">No recent records</div>
		{/if}
	</Card.Content>
</Card.Root>
