<script lang="ts">
	import type { CardStyle } from '$lib/types/kanban-card-config';
	import ColorPicker from './ColorPicker.svelte';
	import CardPreview from './CardPreview.svelte';
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import * as Accordion from '$lib/components/ui/accordion';
	import { cn } from '$lib/utils';
	import { Plus, Trash2, Eye, EyeOff } from 'lucide-svelte';

	interface Props {
		groupByField: string | null;
		fieldOptions: Array<{ value: string; label: string; color?: string }>;
		fieldOverrides: Record<string, Partial<CardStyle>>;
		defaultStyle: CardStyle;
		layout: any;
		availableFields: Array<{ api_name: string; label: string; type: string }>;
		onchange: (overrides: Record<string, Partial<CardStyle>>) => void;
		class?: string;
	}

	let {
		groupByField,
		fieldOptions,
		fieldOverrides,
		defaultStyle,
		layout,
		availableFields,
		onchange,
		class: className
	}: Props = $props();

	let selectedFieldValue = $state<string>('');
	let showPreview = $state<Record<string, boolean>>({});

	function addOverride() {
		if (!selectedFieldValue) return;

		const newOverrides = { ...fieldOverrides };
		if (!newOverrides[selectedFieldValue]) {
			// Use the field option's color if available, otherwise default to blue
			const option = fieldOptions.find((o) => o.value === selectedFieldValue);
			const defaultColor = option?.color || '#3b82f6';
			newOverrides[selectedFieldValue] = {
				accentColor: defaultColor
			};
		}

		onchange(newOverrides);
		selectedFieldValue = '';
	}

	function removeOverride(fieldValue: string) {
		const newOverrides = { ...fieldOverrides };
		delete newOverrides[fieldValue];
		onchange(newOverrides);
	}

	function updateOverride(fieldValue: string, updates: Partial<CardStyle>) {
		const newOverrides = {
			...fieldOverrides,
			[fieldValue]: {
				...fieldOverrides[fieldValue],
				...updates
			}
		};
		onchange(newOverrides);
	}

	function togglePreview(fieldValue: string) {
		showPreview = {
			...showPreview,
			[fieldValue]: !showPreview[fieldValue]
		};
	}

	function getFieldLabel(value: string): string {
		return fieldOptions.find((o) => o.value === value)?.label || value;
	}

	const unusedOptions = $derived(
		fieldOptions.filter((opt) => !fieldOverrides[opt.value])
	);

	const overrideEntries = $derived(Object.entries(fieldOverrides));
</script>

<div class={cn('space-y-6', className)}>
	<div>
		<h3 class="text-lg font-semibold">Column-Specific Styles</h3>
		<p class="text-sm text-muted-foreground">
			Customize card appearance based on the
			{#if groupByField}
				<Badge variant="outline" class="mx-1">{groupByField}</Badge>
			{/if}
			field value
		</p>
	</div>

	{#if !groupByField}
		<div class="rounded-lg border-2 border-dashed bg-muted/20 p-6 text-center">
			<p class="text-sm text-muted-foreground">
				Column-specific styles require a kanban grouping field.
				<br />
				Configure the kanban view to use this feature.
			</p>
		</div>
	{:else if fieldOptions.length === 0}
		<div class="rounded-lg border-2 border-dashed bg-muted/20 p-6 text-center">
			<p class="text-sm text-muted-foreground">
				The selected field has no options available for styling.
			</p>
		</div>
	{:else}
		<!-- Add override section -->
		<div class="flex gap-2">
			<div class="flex-1">
				<Select.Root type="single" bind:value={selectedFieldValue}>
					<Select.Trigger>
						{#if selectedFieldValue}
							{@const opt = fieldOptions.find((o) => o.value === selectedFieldValue)}
							<div class="flex items-center gap-2">
								{#if opt?.color}
									<div class="h-3 w-3 rounded-full border" style="background-color: {opt.color}"></div>
								{/if}
								{getFieldLabel(selectedFieldValue)}
							</div>
						{:else}
							<span class="text-muted-foreground">Select column to customize...</span>
						{/if}
					</Select.Trigger>
					<Select.Content>
						{#each unusedOptions as option}
							<Select.Item value={option.value}>
								<div class="flex items-center gap-2">
									{#if option.color}
										<div class="h-3 w-3 rounded-full border" style="background-color: {option.color}"></div>
									{/if}
									{option.label}
								</div>
							</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>
			<Button onclick={addOverride} disabled={!selectedFieldValue} class="gap-2">
				<Plus class="h-4 w-4" />
				Add Style Override
			</Button>
		</div>

		{#if overrideEntries.length === 0}
			<div class="rounded-lg border-2 border-dashed bg-muted/20 p-8 text-center">
				<p class="text-sm text-muted-foreground mb-3">No column-specific styles configured</p>
				<p class="text-xs text-muted-foreground">
					Add style overrides to customize cards based on their column (e.g., green for "Won",
					red for "Lost")
				</p>
			</div>
		{:else}
			<!-- Override list -->
			<Accordion.Root type="multiple" class="space-y-2">
				{#each overrideEntries as [fieldValue, override]}
					{@const originalOption = fieldOptions.find((o) => o.value === fieldValue)}
					<Accordion.Item value={fieldValue} class="rounded-lg border bg-card">
						<Accordion.Trigger class="px-4 hover:no-underline">
							<div class="flex items-center justify-between flex-1">
								<div class="flex items-center gap-3">
									{#if originalOption?.color}
										<div
											class="h-4 w-4 rounded-full border"
											style="background-color: {originalOption.color}"
											title="Original option color"
										></div>
									{/if}
									<div
										class="h-4 w-4 rounded border"
										style="background-color: {override.accentColor || defaultStyle.accentColor}"
										title="Card accent color"
									></div>
									<span class="font-medium">{getFieldLabel(fieldValue)}</span>
									<Badge variant="secondary" class="text-xs">Custom Style</Badge>
								</div>
								<div class="flex items-center gap-2" onclick={(e) => e.stopPropagation()}>
									<Button
										size="icon"
										variant="ghost"
										onclick={() => togglePreview(fieldValue)}
										class="h-8 w-8"
									>
										{#if showPreview[fieldValue]}
											<EyeOff class="h-4 w-4" />
										{:else}
											<Eye class="h-4 w-4" />
										{/if}
									</Button>
									<Button
										size="icon"
										variant="ghost"
										onclick={() => removeOverride(fieldValue)}
										class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
									>
										<Trash2 class="h-4 w-4" />
									</Button>
								</div>
							</div>
						</Accordion.Trigger>
						<Accordion.Content class="px-4 pb-4">
							<div class="space-y-4 pt-2">
								<!-- Preview -->
								{#if showPreview[fieldValue]}
									<CardPreview
										style={{
											...defaultStyle,
											...override
										}}
										{layout}
										{availableFields}
										sampleData={{
											title: `Sample ${getFieldLabel(fieldValue)} Card`,
											[groupByField]: fieldValue
										}}
									/>
								{/if}

								<!-- Style overrides -->
								<div class="grid grid-cols-2 gap-4">
									<ColorPicker
										label="Background"
										value={override.backgroundColor || defaultStyle.backgroundColor || '#ffffff'}
										onchange={(val) => updateOverride(fieldValue, { backgroundColor: val })}
									/>
									<ColorPicker
										label="Border"
										value={override.borderColor || defaultStyle.borderColor || '#e5e7eb'}
										onchange={(val) => updateOverride(fieldValue, { borderColor: val })}
									/>
								</div>

								<div class="grid grid-cols-2 gap-4">
									<ColorPicker
										label="Accent Color"
										value={override.accentColor || defaultStyle.accentColor || '#3b82f6'}
										onchange={(val) => updateOverride(fieldValue, { accentColor: val })}
									/>
									<div class="space-y-2">
										<Label>Accent Width (px)</Label>
										<Input
											type="number"
											min="0"
											max="10"
											value={override.accentWidth ?? defaultStyle.accentWidth ?? 3}
											oninput={(e) =>
												updateOverride(fieldValue, {
													accentWidth: parseInt((e.target as HTMLInputElement).value)
												})}
										/>
									</div>
								</div>

								<div class="grid grid-cols-2 gap-4">
									<ColorPicker
										label="Title Color"
										value={override.titleColor || defaultStyle.titleColor || '#111827'}
										onchange={(val) => updateOverride(fieldValue, { titleColor: val })}
									/>
									<ColorPicker
										label="Subtitle Color"
										value={override.subtitleColor || defaultStyle.subtitleColor || '#6b7280'}
										onchange={(val) => updateOverride(fieldValue, { subtitleColor: val })}
									/>
								</div>

								<ColorPicker
									label="Body Text Color"
									value={override.textColor || defaultStyle.textColor || '#374151'}
									onchange={(val) => updateOverride(fieldValue, { textColor: val })}
									class="grid grid-cols-2 gap-4"
								/>

								<div class="text-xs text-muted-foreground bg-muted/50 rounded p-2">
									<strong>Note:</strong> Only the properties you set here will override the default
									style. Unset properties will use the default card style.
								</div>
							</div>
						</Accordion.Content>
					</Accordion.Item>
				{/each}
			</Accordion.Root>
		{/if}

		<!-- Example use cases -->
		<div class="rounded-lg bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 p-4">
			<h5 class="text-xs font-semibold text-blue-900 dark:text-blue-100 mb-2">
				Example Use Cases
			</h5>
			<ul class="text-xs text-blue-700 dark:text-blue-300 space-y-1 ml-4">
				<li>Sales Pipeline: Green for "Won", Red for "Lost", Yellow for "Negotiation"</li>
				<li>Task Status: Gray for "Backlog", Blue for "In Progress", Green for "Done"</li>
				<li>Priority Levels: Red for "High", Orange for "Medium", Gray for "Low"</li>
			</ul>
		</div>
	{/if}
</div>
