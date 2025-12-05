<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Popover from '$lib/components/ui/popover';
	import { Input } from '$lib/components/ui/input';
	import { Code2, Search } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface Props {
		fields: Field[];
		onInsert: (variable: string) => void;
	}

	let { fields, onInsert }: Props = $props();

	let searchQuery = $state('');
	let open = $state(false);

	const filteredFields = $derived(() => {
		if (!searchQuery) return fields;
		const query = searchQuery.toLowerCase();
		return fields.filter(
			(f) =>
				f.label.toLowerCase().includes(query) ||
				f.api_name.toLowerCase().includes(query)
		);
	});

	// System variables available in all contexts
	const systemVariables = [
		{ name: 'current_user.name', label: 'Current User Name' },
		{ name: 'current_user.email', label: 'Current User Email' },
		{ name: 'current_date', label: 'Current Date' },
		{ name: 'current_datetime', label: 'Current Date & Time' },
		{ name: 'record.id', label: 'Record ID' },
		{ name: 'record.created_at', label: 'Record Created Date' },
		{ name: 'record.updated_at', label: 'Record Updated Date' }
	];

	function handleInsert(variable: string) {
		onInsert(variable);
		open = false;
		searchQuery = '';
	}
</script>

<Popover.Root bind:open>
	<Popover.Trigger>
		{#snippet child({ props })}
			<Button variant="ghost" size="sm" class="h-7 gap-1 px-2" {...props}>
				<Code2 class="h-3.5 w-3.5" />
				Insert Variable
			</Button>
		{/snippet}
	</Popover.Trigger>
	<Popover.Content class="w-72 p-0" align="end">
		<div class="border-b p-2">
			<div class="relative">
				<Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<Input
					bind:value={searchQuery}
					placeholder="Search fields..."
					class="h-8 pl-8"
				/>
			</div>
		</div>
		<div class="max-h-64 overflow-y-auto p-1">
			<!-- Record Fields -->
			{#if filteredFields().length > 0}
				<div class="px-2 py-1">
					<span class="text-xs font-medium text-muted-foreground">Record Fields</span>
				</div>
				{#each filteredFields() as field}
					<button
						type="button"
						class="flex w-full items-center justify-between rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
						onclick={() => handleInsert(`record.${field.api_name}`)}
					>
						<span>{field.label}</span>
						<code class="text-xs text-muted-foreground">{field.api_name}</code>
					</button>
				{/each}
			{/if}

			<!-- System Variables -->
			<div class="mt-2 border-t pt-2">
				<div class="px-2 py-1">
					<span class="text-xs font-medium text-muted-foreground">System Variables</span>
				</div>
				{#each systemVariables as variable}
					<button
						type="button"
						class="flex w-full items-center justify-between rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
						onclick={() => handleInsert(variable.name)}
					>
						<span>{variable.label}</span>
						<code class="text-xs text-muted-foreground">{variable.name}</code>
					</button>
				{/each}
			</div>

			{#if filteredFields().length === 0 && searchQuery}
				<div class="px-2 py-4 text-center text-sm text-muted-foreground">
					No fields found matching "{searchQuery}"
				</div>
			{/if}
		</div>
	</Popover.Content>
</Popover.Root>
