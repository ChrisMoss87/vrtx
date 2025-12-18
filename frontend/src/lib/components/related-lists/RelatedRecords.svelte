<script lang="ts">
	import { goto } from '$app/navigation';
	import { recordsApi } from '$lib/api/records';
	import { modulesApi, type Module, type Field } from '$lib/api/modules';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import {
		Link2,
		ChevronDown,
		ChevronUp,
		Plus,
		ExternalLink
	} from 'lucide-svelte';
	import { onMount } from 'svelte';

	interface Props {
		/** The module that has the lookup field pointing to current record */
		relatedModuleApiName: string;
		/** The lookup field in the related module that points to current record */
		lookupFieldApiName: string;
		/** The ID of the current record being viewed */
		currentRecordId: number;
		/** Display title for the related list */
		title?: string;
		/** Max records to show initially */
		limit?: number;
		/** Allow creating new related records */
		showAddButton?: boolean;
	}

	let {
		relatedModuleApiName,
		lookupFieldApiName,
		currentRecordId,
		title,
		limit = 5,
		showAddButton = true
	}: Props = $props();

	interface RelatedRecord {
		id: number;
		data: Record<string, unknown>;
		created_at: string;
	}

	let records = $state<RelatedRecord[]>([]);
	let relatedModule = $state<Module | null>(null);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let expanded = $state(true);
	let showAll = $state(false);
	let total = $state(0);

	async function loadData() {
		loading = true;
		error = null;

		try {
			// Load module info
			relatedModule = await modulesApi.getByApiName(relatedModuleApiName);

			// Load related records with filter
			const response = await recordsApi.getAll(relatedModuleApiName, {
				filters: {
					[lookupFieldApiName]: {
						operator: 'equals',
						value: currentRecordId
					}
				},
				per_page: showAll ? 100 : limit
			});

			records = response.records;
			total = response.meta?.total || records.length;
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load related records';
		} finally {
			loading = false;
		}
	}

	function getDisplayValue(record: RelatedRecord): string {
		if (!relatedModule) return `#${record.id}`;

		const nameField = relatedModule.settings?.record_name_field;
		if (nameField && record.data[nameField]) {
			return String(record.data[nameField]);
		}

		// Try common name fields
		const commonFields = ['name', 'title', 'subject', 'first_name', 'company_name'];
		for (const field of commonFields) {
			if (record.data[field]) {
				return String(record.data[field]);
			}
		}

		return `${relatedModule.singular_name} #${record.id}`;
	}

	function getSecondaryValue(record: RelatedRecord): string | null {
		if (!relatedModule) return null;

		// Try to find a secondary display field
		const secondaryFields = ['email', 'status', 'stage', 'type', 'amount'];
		for (const field of secondaryFields) {
			if (record.data[field] && field !== relatedModule.settings?.record_name_field) {
				const value = record.data[field];
				if (typeof value === 'number' && field === 'amount') {
					return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
				}
				return String(value);
			}
		}

		return null;
	}

	function navigateToRecord(recordId: number) {
		goto(`/records/${relatedModuleApiName}/${recordId}`);
	}

	function createNewRecord() {
		const params = new URLSearchParams();
		params.set('prefill', JSON.stringify({ [lookupFieldApiName]: currentRecordId }));
		goto(`/records/${relatedModuleApiName}/create?${params.toString()}`);
	}

	onMount(() => {
		loadData();
	});

	$effect(() => {
		if (relatedModuleApiName && lookupFieldApiName && currentRecordId) {
			loadData();
		}
	});

	const displayTitle = $derived(title || relatedModule?.name || relatedModuleApiName);
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<div class="flex items-center justify-between">
			<button
				class="flex items-center gap-2 hover:text-primary transition-colors"
				onclick={() => (expanded = !expanded)}
			>
				{#if expanded}
					<ChevronUp class="h-4 w-4" />
				{:else}
					<ChevronDown class="h-4 w-4" />
				{/if}
				<Link2 class="h-4 w-4" />
				<Card.Title class="text-base">{displayTitle}</Card.Title>
				{#if !loading}
					<Badge variant="secondary" class="ml-2">{total}</Badge>
				{/if}
			</button>
			{#if showAddButton}
				<Button variant="outline" size="sm" onclick={createNewRecord}>
					<Plus class="mr-1 h-3 w-3" />
					Add
				</Button>
			{/if}
		</div>
	</Card.Header>

	{#if expanded}
		<Card.Content>
			{#if loading}
				<div class="space-y-2">
					{#each [1, 2, 3] as _}
						<div class="flex items-center gap-3 p-2">
							<Skeleton class="h-4 w-4 rounded" />
							<div class="flex-1 space-y-1">
								<Skeleton class="h-4 w-2/3" />
								<Skeleton class="h-3 w-1/3" />
							</div>
						</div>
					{/each}
				</div>
			{:else if error}
				<p class="text-sm text-destructive">{error}</p>
			{:else if records.length === 0}
				<p class="text-sm text-muted-foreground text-center py-4">
					No {relatedModule?.name?.toLowerCase() || 'records'} found
				</p>
			{:else}
				<div class="space-y-1">
					{#each records as record (record.id)}
						{@const secondaryValue = getSecondaryValue(record)}
						<button
							class="w-full flex items-center gap-3 p-2 rounded-md hover:bg-muted/50 transition-colors text-left group"
							onclick={() => navigateToRecord(record.id)}
						>
							<ExternalLink class="h-4 w-4 text-muted-foreground group-hover:text-primary flex-shrink-0" />
							<div class="flex-1 min-w-0">
								<p class="text-sm font-medium truncate group-hover:text-primary">
									{getDisplayValue(record)}
								</p>
								{#if secondaryValue}
									<p class="text-xs text-muted-foreground truncate">
										{secondaryValue}
									</p>
								{/if}
							</div>
						</button>
					{/each}
				</div>

				{#if total > limit && !showAll}
					<Button
						variant="ghost"
						size="sm"
						class="w-full mt-3"
						onclick={() => {
							showAll = true;
							loadData();
						}}
					>
						Show all {total} {relatedModule?.name?.toLowerCase() || 'records'}
					</Button>
				{/if}
			{/if}
		</Card.Content>
	{/if}
</Card.Root>
