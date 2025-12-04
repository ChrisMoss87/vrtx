<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Search, Link2, Plus, Trash2, Info } from 'lucide-svelte';
	import type { Module } from '$lib/api/modules';

	interface LookupConfiguration {
		related_module_id: number;
		display_field: string;
		search_fields: string[];
		relationship_type: 'one_to_one' | 'many_to_one' | 'many_to_many';
		cascading_field?: string;
		allow_create: boolean;
		filters?: Record<string, any>;
	}

	interface Props {
		value: LookupConfiguration | null;
		availableModules: Pick<Module, 'id' | 'name' | 'api_name'>[];
		onchange?: (config: LookupConfiguration | null) => void;
	}

	let { value = $bindable(), availableModules, onchange }: Props = $props();

	// Initialize with default if null
	if (!value) {
		value = {
			related_module_id: availableModules[0]?.id || 0,
			display_field: '',
			search_fields: [],
			relationship_type: 'many_to_one',
			allow_create: true,
			filters: {}
		};
	}

	const RELATIONSHIP_TYPES = [
		{
			value: 'one_to_one',
			label: 'One to One',
			description: 'Each record can only link to one related record'
		},
		{
			value: 'many_to_one',
			label: 'Many to One',
			description: 'Multiple records can link to the same related record'
		},
		{
			value: 'many_to_many',
			label: 'Many to Many',
			description: 'Records can link to multiple related records'
		}
	];

	// Get selected module
	const selectedModule = $derived(availableModules.find((m) => m.id === value?.related_module_id));

	// Mock fields for selected module (in real app, fetch from API)
	const availableFields = $derived.by(() => {
		if (!selectedModule) return [];
		// TODO: Fetch actual fields from API based on selectedModule.id
		// For now, return common field names as placeholders
		return [
			{ api_name: 'name', label: 'Name', type: 'text' },
			{ api_name: 'email', label: 'Email', type: 'email' },
			{ api_name: 'phone', label: 'Phone', type: 'phone' },
			{ api_name: 'company', label: 'Company', type: 'text' },
			{ api_name: 'status', label: 'Status', type: 'select' }
		];
	});

	let newSearchField = $state('');

	function updateRelatedModule(moduleId: number) {
		if (value) {
			value.related_module_id = moduleId;
			// Reset dependent fields
			value.display_field = '';
			value.search_fields = [];
			value.cascading_field = undefined;
			onchange?.(value);
		}
	}

	function updateDisplayField(fieldName: string) {
		if (value) {
			value.display_field = fieldName;
			onchange?.(value);
		}
	}

	function updateRelationshipType(type: 'one_to_one' | 'many_to_one' | 'many_to_many') {
		if (value) {
			value.relationship_type = type;
			onchange?.(value);
		}
	}

	function addSearchField() {
		if (value && newSearchField && !value.search_fields.includes(newSearchField)) {
			value.search_fields = [...value.search_fields, newSearchField];
			newSearchField = '';
			onchange?.(value);
		}
	}

	function removeSearchField(fieldName: string) {
		if (value) {
			value.search_fields = value.search_fields.filter((f) => f !== fieldName);
			onchange?.(value);
		}
	}

	function toggleAllowCreate() {
		if (value) {
			value.allow_create = !value.allow_create;
			onchange?.(value);
		}
	}

	function updateCascadingField(fieldName: string | undefined) {
		if (value) {
			value.cascading_field = fieldName || undefined;
			onchange?.(value);
		}
	}

	function updateFilters(filtersJson: string) {
		if (value) {
			try {
				value.filters = filtersJson ? JSON.parse(filtersJson) : {};
				onchange?.(value);
			} catch (e) {
				// Invalid JSON, don't update
				console.error('Invalid JSON for filters:', e);
			}
		}
	}
</script>

<Card.Root>
	<Card.CardHeader>
		<Card.CardTitle class="flex items-center gap-2">
			<Link2 class="h-4 w-4" />
			Lookup Field Configuration
		</Card.CardTitle>
		<Card.CardDescription>
			Configure how this field links to records in another module
		</Card.CardDescription>
	</Card.CardHeader>

	<Card.CardContent class="space-y-6">
		<!-- Related Module Selection -->
		<div class="space-y-2">
			<Label>Related Module</Label>
			<Select.Root
				type="single"
				value={value?.related_module_id?.toString() || ''}
				onValueChange={(newValue) => {
					if (newValue) updateRelatedModule(Number(newValue));
				}}
			>
				<Select.Trigger>
					<span>{selectedModule?.name || 'Select module to link to'}</span>
				</Select.Trigger>
				<Select.Content>
					{#each availableModules as module}
						<Select.Item value={module.id.toString()}>
							{module.name}
							<Badge variant="outline" class="ml-2 text-xs">{module.api_name}</Badge>
						</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			<p class="flex items-center gap-1 text-xs text-muted-foreground">
				<Info class="h-3 w-3" />
				Records from this module will be available for selection
			</p>
		</div>

		{#if selectedModule}
			<!-- Relationship Type -->
			<div class="space-y-3">
				<Label>Relationship Type</Label>
				<div class="grid gap-2">
					{#each RELATIONSHIP_TYPES as relType}
						<button
							type="button"
							onclick={() => updateRelationshipType(relType.value as any)}
							class={`rounded-md border p-3 text-left transition-colors ${
								value?.relationship_type === relType.value
									? 'border-primary bg-primary/5'
									: 'border-border hover:bg-accent'
							}`}
						>
							<div class="flex items-start justify-between gap-2">
								<div>
									<div class="text-sm font-medium">{relType.label}</div>
									<div class="mt-0.5 text-xs text-muted-foreground">
										{relType.description}
									</div>
								</div>
								{#if value?.relationship_type === relType.value}
									<div class="mt-1.5 h-2 w-2 rounded-full bg-primary"></div>
								{/if}
							</div>
						</button>
					{/each}
				</div>
			</div>

			<!-- Display Field -->
			<div class="space-y-2">
				<Label>Display Field</Label>
				<Select.Root
					type="single"
					value={value?.display_field || ''}
					onValueChange={(newValue) => {
						if (newValue) updateDisplayField(newValue);
					}}
				>
					<Select.Trigger>
						<span>
							{availableFields.find((f) => f.api_name === value?.display_field)?.label ||
								'Select field to display'}
						</span>
					</Select.Trigger>
					<Select.Content>
						{#each availableFields as field}
							<Select.Item value={field.api_name}>
								{field.label}
								<Badge variant="outline" class="ml-2 text-xs">{field.type}</Badge>
							</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
				<p class="text-xs text-muted-foreground">
					This field will be shown in the dropdown and after selection
				</p>
			</div>

			<!-- Search Fields -->
			<div class="space-y-2">
				<Label>Search Fields</Label>
				<div class="space-y-2">
					<!-- Current Search Fields -->
					{#if value && value.search_fields.length > 0}
						<div class="flex flex-wrap gap-2">
							{#each value.search_fields as searchField}
								<Badge variant="secondary" class="pr-1 pl-2">
									{availableFields.find((f) => f.api_name === searchField)?.label || searchField}
									<Button
										type="button"
										variant="ghost"
										size="icon"
										onclick={() => removeSearchField(searchField)}
										class="ml-1 h-4 w-4 hover:bg-destructive/20"
									>
										<Trash2 class="h-3 w-3" />
									</Button>
								</Badge>
							{/each}
						</div>
					{/if}

					<!-- Add Search Field -->
					<div class="flex gap-2">
						<Select.Root
							type="single"
							value={newSearchField}
							onValueChange={(val) => {
								if (val) newSearchField = val;
							}}
						>
							<Select.Trigger class="flex-1">
								<span>{newSearchField || 'Add search field'}</span>
							</Select.Trigger>
							<Select.Content>
								{#each availableFields.filter((f) => !value?.search_fields.includes(f.api_name)) as field}
									<Select.Item value={field.api_name}>
										{field.label}
									</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
						<Button
							type="button"
							variant="outline"
							size="icon"
							onclick={addSearchField}
							disabled={!newSearchField}
						>
							<Plus class="h-4 w-4" />
						</Button>
					</div>
					<p class="text-xs text-muted-foreground">
						Fields to search when user types in the dropdown
					</p>
				</div>
			</div>

			<!-- Advanced Options -->
			<div class="space-y-4 border-t pt-4">
				<h4 class="text-sm font-semibold">Advanced Options</h4>

				<!-- Allow Create -->
				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label>Allow Quick Create</Label>
						<p class="text-xs text-muted-foreground">
							Let users create new {selectedModule.name.toLowerCase()} records inline
						</p>
					</div>
					<Switch checked={value?.allow_create || false} onCheckedChange={toggleAllowCreate} />
				</div>

				<!-- Cascading Dropdown -->
				<div class="space-y-2">
					<Label>Cascading Field (Optional)</Label>
					<Select.Root
						type="single"
						value={value?.cascading_field || ''}
						onValueChange={(val) => {
							updateCascadingField(val || undefined);
						}}
					>
						<Select.Trigger>
							<span>
								{value?.cascading_field
									? availableFields.find((f) => f.api_name === value?.cascading_field)?.label ||
										value.cascading_field
									: 'No cascading'}
							</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="">None</Select.Item>
							{#each availableFields.filter((f) => f.type === 'select' || f.type === 'lookup') as field}
								<Select.Item value={field.api_name}>
									{field.label}
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
					<p class="text-xs text-muted-foreground">
						Filter options based on another field's value (e.g., City depends on State)
					</p>
				</div>

				<!-- Static Filters (JSON) -->
				<div class="space-y-2">
					<Label>Static Filters (JSON)</Label>
					<Textarea
						value={value?.filters ? JSON.stringify(value.filters, null, 2) : ''}
						oninput={(e) => updateFilters(e.currentTarget.value)}
						placeholder={`{\n  "status": "active",\n  "country": "US"\n}`}
						class="min-h-[100px] font-mono text-xs"
					/>
					<p class="text-xs text-muted-foreground">
						Always filter related records by these conditions (optional)
					</p>
				</div>
			</div>
		{:else}
			<div class="rounded-md border bg-muted/30 p-8 text-center">
				<Search class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
				<p class="text-sm text-muted-foreground">Select a module to configure lookup options</p>
			</div>
		{/if}
	</Card.CardContent>
</Card.Root>
