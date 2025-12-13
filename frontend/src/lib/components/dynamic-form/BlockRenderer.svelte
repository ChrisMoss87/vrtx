<script lang="ts">
	import type { Block, Field } from '$lib/api/modules';
	import type { Writable, Readable } from 'svelte/store';
	import FieldRenderer from './FieldRenderer.svelte';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import { ChevronDown, ChevronUp } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';

	interface Props {
		block: Block;
		formData: Writable<Record<string, any>>;
		errors: Writable<Record<string, string>>;
		touched: Writable<Record<string, boolean>>;
		visibleFields: Readable<Set<number>>;
		isReadonly: boolean;
		updateField: (fieldId: number, value: any) => void;
	}

	let { block, formData, errors, touched, visibleFields, isReadonly, updateField }: Props =
		$props();

	// Collapsible state for sections
	let isCollapsed = $state(false);

	// Get visible fields for this block
	const blockFields = $derived.by(() => {
		return block.fields?.filter((field) => $visibleFields.has(field.id)) || [];
	});

	// Get columns setting (default to 1)
	const columns = $derived(block.settings?.columns || 1);

	// Toggle collapse state
	function toggleCollapse() {
		isCollapsed = !isCollapsed;
	}
</script>

{#if block.type === 'section'}
	<Card.Root class="shadow-sm">
		<!-- Block Header -->
		<Card.CardHeader class="pb-3">
			<div class="flex items-center justify-between">
				<div class="flex-1">
					<Card.CardTitle class="text-lg">{block.name}</Card.CardTitle>
					{#if block.description}
						<Card.CardDescription class="mt-1">{block.description}</Card.CardDescription>
					{/if}
				</div>
				{#if block.settings?.collapsible}
					<Button type="button" variant="ghost" size="icon" onclick={toggleCollapse}>
						{#if isCollapsed}
							<ChevronDown class="h-4 w-4" />
						{:else}
							<ChevronUp class="h-4 w-4" />
						{/if}
					</Button>
				{/if}
			</div>
		</Card.CardHeader>

		<!-- Block Content -->
		{#if !isCollapsed}
			<Card.CardContent>
				{#if blockFields.length > 0}
					<!-- Grid Layout based on columns setting -->
					<div
						class="grid gap-6"
						class:grid-cols-1={columns === 1}
						class:grid-cols-2={columns === 2}
						class:md:grid-cols-2={columns === 2}
						class:grid-cols-3={columns === 3}
						class:md:grid-cols-3={columns === 3}
					>
						{#each blockFields as field (field.id)}
							<div
								class="col-span-1"
								class:md:col-span-2={field.width === 100 && typeof columns === 'number' && columns > 1}
								class:md:col-span-1={field.width !== 100}
							>
								<FieldRenderer
									{field}
									value={$formData[field.id]}
									error={$touched[field.id] ? $errors[field.id] : undefined}
									{isReadonly}
									onchange={(value) => updateField(field.id, value)}
								/>
							</div>
						{/each}
					</div>
				{:else}
					<div class="p-4 text-center text-sm text-muted-foreground">
						No visible fields in this section
					</div>
				{/if}
			</Card.CardContent>
		{/if}
	</Card.Root>
{:else if block.type === 'tab'}
	<!-- Tab Block (for future multi-tab support) -->
	<Card.Root class="shadow-sm">
		<Tabs.Root value={block.fields?.[0]?.id.toString()}>
			<Tabs.List class="w-full">
				{#each blockFields as field (field.id)}
					<Tabs.Trigger value={field.id.toString()}>
						{field.label}
					</Tabs.Trigger>
				{/each}
			</Tabs.List>

			{#each blockFields as field (field.id)}
				<Tabs.Content value={field.id.toString()}>
					<Card.CardContent class="pt-6">
						<FieldRenderer
							{field}
							value={$formData[field.id]}
							error={$touched[field.id] ? $errors[field.id] : undefined}
							{isReadonly}
							onchange={(value) => updateField(field.id, value)}
						/>
					</Card.CardContent>
				</Tabs.Content>
			{/each}
		</Tabs.Root>
	</Card.Root>
{:else}
	<!-- Default: render as simple list -->
	<div class="space-y-4">
		<h3 class="text-lg font-semibold">{block.name}</h3>
		{#if block.description}
			<p class="-mt-2 text-sm text-muted-foreground">{block.description}</p>
		{/if}

		{#if blockFields.length > 0}
			<div class="space-y-4">
				{#each blockFields as field (field.id)}
					<FieldRenderer
						{field}
						value={$formData[field.id]}
						error={$touched[field.id] ? $errors[field.id] : undefined}
						{isReadonly}
						onchange={(value) => updateField(field.id, value)}
					/>
				{/each}
			</div>
		{:else}
			<div class="rounded-md border p-4 text-center text-sm text-muted-foreground">
				No visible fields in this block
			</div>
		{/if}
	</div>
{/if}
