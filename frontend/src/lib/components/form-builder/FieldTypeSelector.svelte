<script lang="ts">
	import {
		getFieldTypesByCategory,
		FIELD_CATEGORIES,
		POPULAR_FIELD_TYPES,
		getFieldType,
		type FieldType
	} from '$lib/constants/field-types';
	import { Button } from '$lib/components/ui/button';
	import * as Popover from '$lib/components/ui/popover';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Search, Check, Star } from 'lucide-svelte';
	import { cn } from '$lib/utils';

	interface Props {
		value: FieldType;
		onchange?: (type: FieldType) => void;
		disabled?: boolean;
	}

	let { value = $bindable(), onchange, disabled = false }: Props = $props();

	let open = $state(false);
	let searchQuery = $state('');

	const fieldTypesByCategory = getFieldTypesByCategory();
	const selectedFieldType = $derived(getFieldType(value));

	// Filter field types based on search
	const filteredCategories = $derived.by(() => {
		if (!searchQuery.trim()) {
			return fieldTypesByCategory;
		}

		const query = searchQuery.toLowerCase();
		const filtered: typeof fieldTypesByCategory = {
			basic: [],
			numeric: [],
			choice: [],
			datetime: [],
			relationship: [],
			calculated: [],
			media: []
		};

		Object.entries(fieldTypesByCategory).forEach(([category, types]) => {
			filtered[category as keyof typeof filtered] = types.filter(
				(type) =>
					type.label.toLowerCase().includes(query) ||
					type.description.toLowerCase().includes(query) ||
					type.type.toLowerCase().includes(query)
			);
		});

		return filtered;
	});

	// Show popular field types
	const popularTypes = $derived(
		POPULAR_FIELD_TYPES.map((type) => getFieldType(type)).filter(Boolean)
	);

	function selectType(type: FieldType) {
		value = type;
		onchange?.(type);
		open = false;
		searchQuery = '';
	}
</script>

<Popover.Root bind:open>
	<Popover.Trigger>
		{#snippet child({ props })}
			<Button
				{...props}
				variant="outline"
				role="combobox"
				aria-expanded={open}
				class="w-full justify-between"
				{disabled}
			>
				{#if selectedFieldType}
					<div class="flex items-center gap-2">
						<svelte:component this={selectedFieldType.icon} class="h-4 w-4" />
						<span>{selectedFieldType.label}</span>
						{#if selectedFieldType.isAdvanced}
							<Badge variant="secondary" class="ml-1 text-xs">Advanced</Badge>
						{/if}
					</div>
				{:else}
					<span class="text-muted-foreground">Select field type...</span>
				{/if}
				<svg
					xmlns="http://www.w3.org/2000/svg"
					width="24"
					height="24"
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					stroke-width="2"
					stroke-linecap="round"
					stroke-linejoin="round"
					class="ml-2 h-4 w-4 shrink-0 opacity-50"
				>
					<path d="m6 9 6 6 6-6" />
				</svg>
			</Button>
		{/snippet}
	</Popover.Trigger>

	<Popover.Content class="w-[400px] p-0" align="start">
		<div class="flex flex-col">
			<!-- Search -->
			<div class="border-b p-3">
				<div class="relative">
					<Search class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
					<Input bind:value={searchQuery} placeholder="Search field types..." class="pl-8" />
				</div>
			</div>

			<ScrollArea class="h-[450px]">
				<div class="p-2">
					<!-- Popular Field Types -->
					{#if !searchQuery && popularTypes.length > 0}
						<div class="mb-4">
							<div
								class="flex items-center gap-1 px-2 py-1.5 text-xs font-semibold text-muted-foreground"
							>
								<Star class="h-3 w-3" />
								Popular
							</div>
							<div class="grid gap-1">
								{#each popularTypes as fieldType}
									{#if fieldType}
										<button
											type="button"
											onclick={() => selectType(fieldType.type)}
											class={cn(
												'flex items-start gap-3 rounded-md px-3 py-2 text-left transition-colors hover:bg-accent',
												value === fieldType.type && 'bg-accent'
											)}
										>
											<div class="mt-0.5 shrink-0">
												<svelte:component this={fieldType.icon} class="h-4 w-4" />
											</div>
											<div class="min-w-0 flex-1">
												<div class="flex items-center gap-2">
													<span class="text-sm font-medium">{fieldType.label}</span>
													{#if fieldType.isAdvanced}
														<Badge variant="secondary" class="text-xs">Advanced</Badge>
													{/if}
												</div>
												<p class="mt-0.5 text-xs text-muted-foreground">
													{fieldType.description}
												</p>
											</div>
											{#if value === fieldType.type}
												<Check class="h-4 w-4 shrink-0 text-primary" />
											{/if}
										</button>
									{/if}
								{/each}
							</div>
						</div>
					{/if}

					<!-- Field Types by Category -->
					{#each Object.entries(filteredCategories) as [category, types]}
						{#if types.length > 0}
							<div class="mb-4">
								<div class="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
									{FIELD_CATEGORIES[category].label}
								</div>
								<div class="grid gap-1">
									{#each types as fieldType}
										<button
											type="button"
											onclick={() => selectType(fieldType.type)}
											class={cn(
												'flex items-start gap-3 rounded-md px-3 py-2 text-left transition-colors hover:bg-accent',
												value === fieldType.type && 'bg-accent'
											)}
										>
											<div class="mt-0.5 shrink-0">
												<svelte:component this={fieldType.icon} class="h-4 w-4" />
											</div>
											<div class="min-w-0 flex-1">
												<div class="flex items-center gap-2">
													<span class="text-sm font-medium">{fieldType.label}</span>
													{#if fieldType.isAdvanced}
														<Badge variant="secondary" class="text-xs">Advanced</Badge>
													{/if}
													{#if fieldType.requiresOptions}
														<Badge variant="outline" class="text-xs">Options</Badge>
													{/if}
												</div>
												<p class="mt-0.5 text-xs text-muted-foreground">
													{fieldType.description}
												</p>
											</div>
											{#if value === fieldType.type}
												<Check class="h-4 w-4 shrink-0 text-primary" />
											{/if}
										</button>
									{/each}
								</div>
							</div>
						{/if}
					{/each}

					<!-- No results -->
					{#if searchQuery && Object.values(filteredCategories).every((types) => types.length === 0)}
						<div class="p-8 text-center">
							<p class="text-sm text-muted-foreground">No field types found</p>
							<p class="mt-1 text-xs text-muted-foreground">Try a different search term</p>
						</div>
					{/if}
				</div>
			</ScrollArea>
		</div>
	</Popover.Content>
</Popover.Root>
