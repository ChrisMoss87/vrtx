<script lang="ts">
	import type { KanbanCardConfig } from '$lib/types/kanban-card-config';
	import { DEFAULT_CARD_CONFIG } from '$lib/types/kanban-card-config';
	import CardLayoutEditor from './CardLayoutEditor.svelte';
	import CardStyleEditor from './CardStyleEditor.svelte';
	import ColumnStyleEditor from './ColumnStyleEditor.svelte';
	import CardPreview from './CardPreview.svelte';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Badge } from '$lib/components/ui/badge';
	import { cn } from '$lib/utils';
	import { LayoutList, Palette, Columns3, Sparkles } from 'lucide-svelte';

	interface Props {
		config: KanbanCardConfig | null;
		availableFields: Array<{ api_name: string; label: string; type: string }>;
		groupByField?: string | null;
		fieldOptions?: Array<{ value: string; label: string }>;
		onchange: (config: KanbanCardConfig) => void;
		class?: string;
	}

	let {
		config = null,
		availableFields,
		groupByField = null,
		fieldOptions = [],
		onchange,
		class: className
	}: Props = $props();

	// Initialize config with defaults if null
	let localConfig = $state<KanbanCardConfig>(config || DEFAULT_CARD_CONFIG);

	// Update local config when prop changes
	$effect(() => {
		if (config) {
			localConfig = config;
		}
	});

	// Emit changes
	function handleConfigChange(updates: Partial<KanbanCardConfig>) {
		const newConfig = { ...localConfig, ...updates };
		localConfig = newConfig;
		onchange(newConfig);
	}

	function handleLayoutChange(layout: typeof localConfig.layout) {
		handleConfigChange({ layout });
	}

	function handleDefaultStyleChange(style: typeof localConfig.default) {
		handleConfigChange({ default: style });
	}

	function handleFieldOverridesChange(overrides: typeof localConfig.fieldOverrides) {
		handleConfigChange({ fieldOverrides: overrides });
	}

	const overrideCount = $derived(
		localConfig.fieldOverrides ? Object.keys(localConfig.fieldOverrides).length : 0
	);

	const fieldCount = $derived(localConfig.layout?.fields?.length || 0);
</script>

<div class={cn('flex flex-col gap-6', className)}>
	<!-- Header -->
	<div class="flex items-start justify-between">
		<div>
			<div class="flex items-center gap-2">
				<Sparkles class="h-5 w-5 text-primary" />
				<h2 class="text-2xl font-bold">Card Designer</h2>
			</div>
			<p class="text-sm text-muted-foreground mt-1">
				Customize how your cards look and what information they display
			</p>
		</div>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
		<!-- Left: Configuration Tabs -->
		<div class="lg:col-span-2">
			<Tabs.Root value="layout" class="w-full">
				<Tabs.List class="grid grid-cols-3 w-full">
					<Tabs.Trigger value="layout" class="gap-2">
						<LayoutList class="h-4 w-4" />
						<span>Layout</span>
						{#if fieldCount > 0}
							<Badge variant="secondary" class="ml-1 h-5 px-1.5 text-xs">{fieldCount}</Badge>
						{/if}
					</Tabs.Trigger>
					<Tabs.Trigger value="default-style" class="gap-2">
						<Palette class="h-4 w-4" />
						<span>Default Style</span>
					</Tabs.Trigger>
					<Tabs.Trigger value="column-styles" class="gap-2">
						<Columns3 class="h-4 w-4" />
						<span>Column Styles</span>
						{#if overrideCount > 0}
							<Badge variant="secondary" class="ml-1 h-5 px-1.5 text-xs">{overrideCount}</Badge>
						{/if}
					</Tabs.Trigger>
				</Tabs.List>

				<div class="mt-6">
					<Tabs.Content value="layout" class="mt-0">
						<div class="rounded-lg border bg-card p-6">
							<CardLayoutEditor
								layout={localConfig.layout}
								{availableFields}
								onchange={handleLayoutChange}
							/>
						</div>
					</Tabs.Content>

					<Tabs.Content value="default-style" class="mt-0">
						<div class="rounded-lg border bg-card p-6">
							<CardStyleEditor
								style={localConfig.default}
								onchange={handleDefaultStyleChange}
								showPresets={true}
							/>
						</div>
					</Tabs.Content>

					<Tabs.Content value="column-styles" class="mt-0">
						<div class="rounded-lg border bg-card p-6">
							<ColumnStyleEditor
								{groupByField}
								{fieldOptions}
								fieldOverrides={localConfig.fieldOverrides || {}}
								defaultStyle={localConfig.default}
								layout={localConfig.layout}
								{availableFields}
								onchange={handleFieldOverridesChange}
							/>
						</div>
					</Tabs.Content>
				</div>
			</Tabs.Root>
		</div>

		<!-- Right: Live Preview -->
		<div class="lg:col-span-1">
			<div class="sticky top-6 space-y-4">
				<CardPreview
					style={localConfig.default}
					layout={localConfig.layout}
					{availableFields}
					sampleData={{
						title: 'Sample Opportunity',
						company: 'Acme Corp',
						amount: 125000,
						stage: 'Proposal',
						priority: 'High',
						close_date: new Date().toISOString()
					}}
				/>

				{#if overrideCount > 0}
					<div class="rounded-lg border-2 border-dashed bg-muted/30 p-4">
						<p class="text-xs font-medium uppercase tracking-wider text-muted-foreground mb-3">
							Style Overrides Preview
						</p>
						<div class="space-y-2">
							{#each Object.entries(localConfig.fieldOverrides || {}) as [fieldValue, override]}
								<div class="text-xs">
									<span class="font-medium">{fieldValue}:</span>
									<div class="flex gap-1 mt-1">
										{#if override.backgroundColor}
											<div
												class="h-4 w-4 rounded border"
												style="background-color: {override.backgroundColor}"
												title="Background"
											></div>
										{/if}
										{#if override.accentColor}
											<div
												class="h-4 w-4 rounded border"
												style="background-color: {override.accentColor}"
												title="Accent"
											></div>
										{/if}
										{#if override.titleColor}
											<div
												class="h-4 w-4 rounded border"
												style="background-color: {override.titleColor}"
												title="Title"
											></div>
										{/if}
									</div>
								</div>
							{/each}
						</div>
					</div>
				{/if}

				<!-- Info card -->
				<div class="rounded-lg bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 p-4">
					<h5 class="text-xs font-semibold text-blue-900 dark:text-blue-100 mb-2">
						Quick Tips
					</h5>
					<ul class="text-xs text-blue-700 dark:text-blue-300 space-y-1.5">
						<li>Add fields in the Layout tab to see them on cards</li>
						<li>Use preset themes for quick styling</li>
						<li>Column styles override default styles for specific values</li>
						<li>The accent strip helps identify card types at a glance</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
