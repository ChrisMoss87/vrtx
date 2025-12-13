<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import * as Select from '$lib/components/ui/select';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { X } from 'lucide-svelte';
	import { getFieldTypeMetadata } from '$lib/constants/fieldTypes';
	import type { CreateFieldRequest, Module } from '$lib/api/modules';
	import FieldOptionsEditor from './FieldOptionsEditor.svelte';
	import FieldTypeSelector from './FieldTypeSelector.svelte';
	import ConditionalVisibilityBuilder from './ConditionalVisibilityBuilder.svelte';
	import FormulaEditor from './FormulaEditor.svelte';
	import LookupFieldConfig from './LookupFieldConfig.svelte';
	import type { FieldType } from '$lib/constants/fieldTypes';

	interface Props {
		field: CreateFieldRequest;
		onFieldChange: (field: CreateFieldRequest) => void;
		onClose: () => void;
		availableFields?: Array<{ api_name: string; label: string; type: string }>;
		availableModules?: Pick<Module, 'id' | 'name' | 'api_name'>[];
	}

	let {
		field = $bindable(),
		onFieldChange,
		onClose,
		availableFields = [],
		availableModules = []
	}: Props = $props();

	const metadata = $derived(getFieldTypeMetadata(field.type as any));

	function generateApiName(label: string): string {
		return (
			label
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '_')
				.replace(/^_|_$/g, '') || 'field'
		);
	}

	function updateField(updates: Partial<CreateFieldRequest>) {
		// Auto-generate api_name from label if label changed and api_name wasn't explicitly set
		if (updates.label && !updates.api_name) {
			const currentApiName = field.api_name || '';
			const expectedApiName = generateApiName(field.label);
			// Only auto-update if api_name was auto-generated (matches the label pattern)
			if (
				!currentApiName ||
				currentApiName === expectedApiName ||
				currentApiName.startsWith('new_')
			) {
				updates.api_name = generateApiName(updates.label);
			}
		}
		onFieldChange({ ...field, ...updates });
	}

	function updateSettings(settingsUpdates: any) {
		updateField({
			settings: {
				...field.settings,
				...settingsUpdates
			}
		});
	}

	function handleTypeChange(newType: FieldType) {
		updateField({ type: newType });
	}
</script>

<div
	class="field-config-panel flex h-full flex-col border-l bg-background shadow-lg lg:shadow-none"
>
	<!-- Header -->
	<div class="sticky top-0 z-10 flex items-center justify-between border-b bg-card p-4">
		<div class="min-w-0 flex-1">
			<h3 class="truncate text-lg font-semibold">Field Settings</h3>
			<p class="truncate text-sm text-muted-foreground">{metadata?.label || field.type}</p>
		</div>
		<Button
			variant="ghost"
			size="icon"
			onclick={onClose}
			data-testid="close-field-config"
			class="shrink-0 hover:bg-destructive/10 hover:text-destructive"
		>
			<X class="h-4 w-4" />
		</Button>
	</div>

	<!-- Content -->
	<div class="scrollbar-thin flex-1 space-y-6 overflow-y-auto p-4">
		<!-- Basic Settings -->
		<div class="space-y-4">
			<div class="mb-3 flex items-center gap-2">
				<div class="h-5 w-1 rounded-full bg-primary"></div>
				<h4 class="text-base font-semibold">Basic Information</h4>
			</div>

			<!-- Field Type Selector -->
			<div class="space-y-2">
				<Label class="text-sm font-medium">Field Type *</Label>
				<FieldTypeSelector value={field.type as FieldType} onchange={handleTypeChange} />
			</div>

			<div class="space-y-2">
				<Label for="field-label" class="text-sm font-medium">Label *</Label>
				<Input
					id="field-label"
					value={field.label}
					oninput={(e) => updateField({ label: e.currentTarget.value })}
					placeholder="Field label"
					data-testid="field-label-input"
					class="transition-all focus:ring-2 focus:ring-primary/20"
				/>
			</div>

			<div class="space-y-2">
				<Label for="field-api-name" class="text-sm font-medium">API Name</Label>
				<Input
					id="field-api-name"
					value={field.api_name || ''}
					oninput={(e) =>
						updateField({
							api_name: e.currentTarget.value.toLowerCase().replace(/[^a-z0-9_]/g, '')
						})}
					placeholder="field_name"
					data-testid="field-api-name-input"
					class="font-mono text-sm transition-all focus:ring-2 focus:ring-primary/20"
				/>
				<p class="text-xs text-muted-foreground">
					Used for API requests and data storage. Auto-generated from label.
				</p>
			</div>

			<div class="space-y-2">
				<Label for="field-description" class="text-sm font-medium">Description</Label>
				<Textarea
					id="field-description"
					value={field.description || ''}
					oninput={(e) => updateField({ description: e.currentTarget.value })}
					placeholder="Brief description"
					rows={2}
					data-testid="field-description-input"
					class="resize-none transition-all focus:ring-2 focus:ring-primary/20"
				/>
			</div>

			<div class="space-y-2">
				<Label for="field-help" class="text-sm font-medium">Help Text</Label>
				<Input
					id="field-help"
					value={field.help_text || ''}
					oninput={(e) => updateField({ help_text: e.currentTarget.value })}
					placeholder="Helpful hint for users"
					data-testid="field-help-input"
					class="transition-all focus:ring-2 focus:ring-primary/20"
				/>
				<p class="text-xs text-muted-foreground">Shows below the field</p>
			</div>

			<div class="space-y-2">
				<Label for="field-placeholder" class="text-sm font-medium">Placeholder</Label>
				<Input
					id="field-placeholder"
					value={field.placeholder || ''}
					oninput={(e) => updateField({ placeholder: e.currentTarget.value })}
					placeholder="e.g., Enter your email"
					data-testid="field-placeholder-input"
					class="transition-all focus:ring-2 focus:ring-primary/20"
				/>
				<p class="text-xs text-muted-foreground">Shows inside the empty field</p>
			</div>
		</div>

		<!-- Layout -->
		<Card.Root class="shadow-sm">
			<Card.Header class="pb-3">
				<Card.Title class="flex items-center gap-2 text-base">
					<div class="h-4 w-1 rounded-full bg-blue-500"></div>
					Layout
				</Card.Title>
			</Card.Header>
			<Card.Content class="space-y-4">
				<div class="space-y-2">
					<Label for="field-width">Width</Label>
					<Select.Root
						type="single"
						value={field.width?.toString() || '100'}
						onValueChange={(val) => {
							if (val) updateField({ width: parseInt(val) });
						}}
					>
						<Select.Trigger id="field-width" data-testid="field-width-select">
							<span>{field.width || 100}%</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="25">25% (Quarter)</Select.Item>
							<Select.Item value="33">33% (Third)</Select.Item>
							<Select.Item value="50">50% (Half)</Select.Item>
							<Select.Item value="100">100% (Full)</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>
			</Card.Content>
		</Card.Root>

		<!-- Validation -->
		<Card.Root class="shadow-sm">
			<Card.Header class="pb-3">
				<Card.Title class="flex items-center gap-2 text-base">
					<div class="h-4 w-1 rounded-full bg-orange-500"></div>
					Validation
				</Card.Title>
			</Card.Header>
			<Card.Content class="space-y-3">
				<div class="flex items-center space-x-2">
					<Checkbox
						id="field-required"
						checked={field.is_required}
						onCheckedChange={(checked) => updateField({ is_required: !!checked })}
						data-testid="field-required-checkbox"
					/>
					<Label for="field-required" class="cursor-pointer font-normal">Required field</Label>
				</div>

				<div class="flex items-center space-x-2">
					<Checkbox
						id="field-unique"
						checked={field.is_unique}
						onCheckedChange={(checked) => updateField({ is_unique: !!checked })}
						data-testid="field-unique-checkbox"
					/>
					<Label for="field-unique" class="cursor-pointer font-normal">Unique values only</Label>
				</div>
			</Card.Content>
		</Card.Root>

		<!-- Search & Filter -->
		<Card.Root class="shadow-sm">
			<Card.Header class="pb-3">
				<Card.Title class="flex items-center gap-2 text-base">
					<div class="h-4 w-1 rounded-full bg-green-500"></div>
					Search & Filter
				</Card.Title>
			</Card.Header>
			<Card.Content class="space-y-3">
				<div class="flex items-center space-x-2">
					<Checkbox
						id="field-searchable"
						checked={field.is_searchable}
						onCheckedChange={(checked) => updateField({ is_searchable: !!checked })}
						data-testid="field-searchable-checkbox"
					/>
					<Label for="field-searchable" class="cursor-pointer font-normal">Searchable</Label>
				</div>

				<div class="flex items-center space-x-2">
					<Checkbox
						id="field-filterable"
						checked={field.is_filterable}
						onCheckedChange={(checked) => updateField({ is_filterable: !!checked })}
						data-testid="field-filterable-checkbox"
					/>
					<Label for="field-filterable" class="cursor-pointer font-normal">Filterable</Label>
				</div>

				<div class="flex items-center space-x-2">
					<Checkbox
						id="field-sortable"
						checked={field.is_sortable}
						onCheckedChange={(checked) => updateField({ is_sortable: !!checked })}
						data-testid="field-sortable-checkbox"
					/>
					<Label for="field-sortable" class="cursor-pointer font-normal">Sortable</Label>
				</div>
			</Card.Content>
		</Card.Root>

		<!-- Mass Actions -->
		<Card.Root class="shadow-sm">
			<Card.Header class="pb-3">
				<Card.Title class="flex items-center gap-2 text-base">
					<div class="h-4 w-1 rounded-full bg-amber-500"></div>
					Mass Actions
				</Card.Title>
			</Card.Header>
			<Card.Content class="space-y-3">
				<div class="flex items-center space-x-2">
					<Checkbox
						id="field-mass-updatable"
						checked={field.is_mass_updatable ?? true}
						onCheckedChange={(checked) => updateField({ is_mass_updatable: !!checked })}
						disabled={field.type === 'formula'}
						data-testid="field-mass-updatable-checkbox"
					/>
					<Label for="field-mass-updatable" class="cursor-pointer font-normal">
						Allow Mass Update
					</Label>
				</div>
				{#if field.type === 'formula'}
					<p class="text-xs text-muted-foreground">
						Formula fields cannot be mass updated as they are calculated automatically.
					</p>
				{:else}
					<p class="text-xs text-muted-foreground">
						When enabled, this field can be updated for multiple records at once.
					</p>
				{/if}
			</Card.Content>
		</Card.Root>

		<!-- Field-specific settings -->
		{#if metadata?.isNumeric}
			<Card.Root class="shadow-sm">
				<Card.Header class="pb-3">
					<Card.Title class="flex items-center gap-2 text-base">
						<div class="h-4 w-1 rounded-full bg-purple-500"></div>
						Number Settings
					</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
					<div class="grid grid-cols-2 gap-4">
						<div class="space-y-2">
							<Label for="min-value">Min Value</Label>
							<Input
								id="min-value"
								type="number"
								value={field.settings?.min_value?.toString() || ''}
								oninput={(e) =>
									updateSettings({
										min_value: e.currentTarget.value ? parseFloat(e.currentTarget.value) : null
									})}
								placeholder="Minimum"
								data-testid="field-min-value"
							/>
						</div>
						<div class="space-y-2">
							<Label for="max-value">Max Value</Label>
							<Input
								id="max-value"
								type="number"
								value={field.settings?.max_value?.toString() || ''}
								oninput={(e) =>
									updateSettings({
										max_value: e.currentTarget.value ? parseFloat(e.currentTarget.value) : null
									})}
								placeholder="Maximum"
								data-testid="field-max-value"
							/>
						</div>
					</div>
				</Card.Content>
			</Card.Root>
		{/if}

		{#if field.type === 'text' || field.type === 'textarea'}
			<Card.Root class="shadow-sm">
				<Card.Header class="pb-3">
					<Card.Title class="flex items-center gap-2 text-base">
						<div class="h-4 w-1 rounded-full bg-cyan-500"></div>
						Text Settings
					</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
					<div class="grid grid-cols-2 gap-4">
						<div class="space-y-2">
							<Label for="min-length">Min Length</Label>
							<Input
								id="min-length"
								type="number"
								value={field.settings?.min_length?.toString() || ''}
								oninput={(e) =>
									updateSettings({
										min_length: e.currentTarget.value ? parseInt(e.currentTarget.value) : null
									})}
								placeholder="Minimum"
								data-testid="field-min-length"
							/>
						</div>
						<div class="space-y-2">
							<Label for="max-length">Max Length</Label>
							<Input
								id="max-length"
								type="number"
								value={field.settings?.max_length?.toString() || ''}
								oninput={(e) =>
									updateSettings({
										max_length: e.currentTarget.value ? parseInt(e.currentTarget.value) : null
									})}
								placeholder="Maximum"
								data-testid="field-max-length"
							/>
						</div>
					</div>
				</Card.Content>
			</Card.Root>
		{/if}

		{#if field.type === 'currency'}
			<Card.Root class="shadow-sm">
				<Card.Header class="pb-3">
					<Card.Title class="flex items-center gap-2 text-base">
						<div class="h-4 w-1 rounded-full bg-emerald-500"></div>
						Currency Settings
					</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
					<div class="space-y-2">
						<Label for="currency-code">Currency Code</Label>
						<Input
							id="currency-code"
							value={field.settings?.currency_code || 'USD'}
							oninput={(e) => updateSettings({ currency_code: e.currentTarget.value })}
							placeholder="USD"
							maxlength={3}
							data-testid="field-currency-code"
						/>
					</div>
					<div class="space-y-2">
						<Label for="precision">Decimal Places</Label>
						<Input
							id="precision"
							type="number"
							value={field.settings?.precision?.toString() || '2'}
							oninput={(e) => updateSettings({ precision: parseInt(e.currentTarget.value) })}
							min="0"
							max="4"
							data-testid="field-precision"
						/>
					</div>
				</Card.Content>
			</Card.Root>
		{/if}

		<!-- Options Editor for select, multiselect, radio -->
		{#if metadata?.requiresOptions}
			<FieldOptionsEditor
				options={field.options || []}
				onOptionsChange={(options) => updateField({ options })}
			/>
		{/if}

		<!-- Formula Editor for formula fields -->
		{#if field.type === 'formula'}
			<FormulaEditor
				value={field.settings?.formula_definition || null}
				{availableFields}
				onchange={(formula) => updateSettings({ formula_definition: formula })}
			/>
		{/if}

		<!-- Lookup Configuration for lookup fields -->
		{#if field.type === 'lookup'}
			<LookupFieldConfig
				value={field.settings?.lookup_configuration || null}
				{availableModules}
				onchange={(config) => updateSettings({ lookup_configuration: config })}
			/>
		{/if}

		<!-- Conditional Visibility (for all field types) -->
		<ConditionalVisibilityBuilder
			value={field.settings?.conditional_visibility as any || null}
			{availableFields}
			onchange={(visibility) => updateSettings({ conditional_visibility: visibility })}
		/>
	</div>
</div>

<style>
	.field-config-panel {
		width: 420px;
		max-width: 100%;
	}
</style>
