<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import * as Select from '$lib/components/ui/select';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import { X, ChevronDown, Settings2 } from 'lucide-svelte';
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

	// Collapsible states
	let showAdvanced = $state(false);

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
			<h3 class="truncate text-lg font-semibold">{field.label || 'New Field'}</h3>
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
	<div class="scrollbar-thin flex-1 space-y-4 overflow-y-auto p-4">
		<!-- Essential Settings (Always Visible) -->
		<div class="space-y-4">
			<!-- Field Type -->
			<div class="space-y-2">
				<Label class="text-sm font-medium">Type</Label>
				<FieldTypeSelector value={field.type as FieldType} onchange={handleTypeChange} />
			</div>

			<!-- Label -->
			<div class="space-y-2">
				<Label for="field-label" class="text-sm font-medium">Label <span class="text-destructive">*</span></Label>
				<Input
					id="field-label"
					value={field.label}
					oninput={(e) => updateField({ label: e.currentTarget.value })}
					placeholder="e.g., Email Address"
					data-testid="field-label-input"
				/>
			</div>

			<!-- Required & Width in a row -->
			<div class="grid grid-cols-2 gap-3">
				<div class="flex items-center gap-2 rounded-lg border p-3">
					<Checkbox
						id="field-required"
						checked={field.is_required}
						onCheckedChange={(checked) => updateField({ is_required: !!checked })}
						data-testid="field-required-checkbox"
					/>
					<Label for="field-required" class="cursor-pointer text-sm font-normal">Required</Label>
				</div>

				<Select.Root
					type="single"
					value={field.width?.toString() || '100'}
					onValueChange={(val) => {
						if (val) updateField({ width: parseInt(val) });
					}}
				>
					<Select.Trigger data-testid="field-width-select" class="h-full">
						<span class="text-sm">Width: {field.width || 100}%</span>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="25">25%</Select.Item>
						<Select.Item value="33">33%</Select.Item>
						<Select.Item value="50">50%</Select.Item>
						<Select.Item value="66">66%</Select.Item>
						<Select.Item value="75">75%</Select.Item>
						<Select.Item value="100">100%</Select.Item>
					</Select.Content>
				</Select.Root>
			</div>

			<!-- Options Editor (for select/multiselect/radio - show inline) -->
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
		</div>

		<!-- Advanced Settings (Collapsible) -->
		<Collapsible.Root bind:open={showAdvanced}>
			<Collapsible.Trigger>
				{#snippet child({ props })}
					<Button {...props} variant="ghost" class="w-full justify-between gap-2 text-muted-foreground hover:text-foreground">
						<span class="flex items-center gap-2">
							<Settings2 class="h-4 w-4" />
							Advanced Settings
						</span>
						<ChevronDown class="h-4 w-4 transition-transform {showAdvanced ? 'rotate-180' : ''}" />
					</Button>
				{/snippet}
			</Collapsible.Trigger>
			<Collapsible.Content class="mt-3 space-y-4">
				<!-- API Name -->
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
						class="font-mono text-sm"
					/>
				</div>

				<!-- Placeholder & Help Text -->
				<div class="grid gap-3">
					<div class="space-y-2">
						<Label for="field-placeholder" class="text-sm font-medium">Placeholder</Label>
						<Input
							id="field-placeholder"
							value={field.placeholder || ''}
							oninput={(e) => updateField({ placeholder: e.currentTarget.value })}
							placeholder="Shown inside empty field"
							data-testid="field-placeholder-input"
						/>
					</div>
					<div class="space-y-2">
						<Label for="field-help" class="text-sm font-medium">Help Text</Label>
						<Input
							id="field-help"
							value={field.help_text || ''}
							oninput={(e) => updateField({ help_text: e.currentTarget.value })}
							placeholder="Hint shown below field"
							data-testid="field-help-input"
						/>
					</div>
				</div>

				<!-- Default Value (simplified) -->
				{#if field.type !== 'formula' && field.type !== 'auto_number' && field.type !== 'lookup' && field.type !== 'file' && field.type !== 'image' && field.type !== 'signature'}
					<div class="space-y-2">
						<Label for="default-value" class="text-sm font-medium">Default Value</Label>
						{#if field.type === 'checkbox' || field.type === 'toggle'}
							<div class="flex items-center gap-2 rounded-lg border p-3">
								<Checkbox
									id="default-value-bool"
									checked={field.default_value === 'true'}
									onCheckedChange={(checked) => updateField({ default_value: checked ? 'true' : 'false' })}
								/>
								<Label for="default-value-bool" class="cursor-pointer text-sm font-normal">Default to on</Label>
							</div>
						{:else if field.type === 'select' || field.type === 'radio'}
							<Select.Root
								type="single"
								value={field.default_value || ''}
								onValueChange={(val) => updateField({ default_value: val || undefined })}
							>
								<Select.Trigger><span>{field.default_value || 'None'}</span></Select.Trigger>
								<Select.Content>
									<Select.Item value="">None</Select.Item>
									{#each field.options || [] as option}
										<Select.Item value={option.value}>{option.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						{:else if field.type === 'date' || field.type === 'datetime'}
							<Select.Root
								type="single"
								value={field.default_value || ''}
								onValueChange={(val) => updateField({ default_value: val || undefined })}
							>
								<Select.Trigger><span>{field.default_value === 'today' || field.default_value === 'now' ? 'Current' : field.default_value || 'None'}</span></Select.Trigger>
								<Select.Content>
									<Select.Item value="">None</Select.Item>
									<Select.Item value={field.type === 'date' ? 'today' : 'now'}>{field.type === 'date' ? 'Today' : 'Now'}</Select.Item>
								</Select.Content>
							</Select.Root>
						{:else}
							<Input
								id="default-value"
								type={metadata?.isNumeric ? 'number' : 'text'}
								value={field.default_value || ''}
								oninput={(e) => updateField({ default_value: e.currentTarget.value || undefined })}
								placeholder="Enter default..."
							/>
						{/if}
					</div>
				{/if}

				<!-- Validation Options -->
				<div class="space-y-2">
					<Label class="text-sm font-medium">Validation</Label>
					<div class="grid grid-cols-2 gap-2">
						<div class="flex items-center gap-2 rounded border p-2">
							<Checkbox
								id="field-unique"
								checked={field.is_unique}
								onCheckedChange={(checked) => updateField({ is_unique: !!checked })}
							/>
							<Label for="field-unique" class="cursor-pointer text-xs">Unique</Label>
						</div>
						<div class="flex items-center gap-2 rounded border p-2">
							<Checkbox
								id="field-quick-create"
								checked={field.settings?.show_in_quick_create ?? false}
								onCheckedChange={(checked) => updateSettings({ show_in_quick_create: !!checked })}
							/>
							<Label for="field-quick-create" class="cursor-pointer text-xs">Quick Create</Label>
						</div>
					</div>
				</div>

				<!-- Table Options -->
				<div class="space-y-2">
					<Label class="text-sm font-medium">Table Behavior</Label>
					<div class="grid grid-cols-3 gap-2">
						<div class="flex items-center gap-2 rounded border p-2">
							<Checkbox
								id="field-searchable"
								checked={field.is_searchable}
								onCheckedChange={(checked) => updateField({ is_searchable: !!checked })}
							/>
							<Label for="field-searchable" class="cursor-pointer text-xs">Search</Label>
						</div>
						<div class="flex items-center gap-2 rounded border p-2">
							<Checkbox
								id="field-filterable"
								checked={field.is_filterable}
								onCheckedChange={(checked) => updateField({ is_filterable: !!checked })}
							/>
							<Label for="field-filterable" class="cursor-pointer text-xs">Filter</Label>
						</div>
						<div class="flex items-center gap-2 rounded border p-2">
							<Checkbox
								id="field-sortable"
								checked={field.is_sortable}
								onCheckedChange={(checked) => updateField({ is_sortable: !!checked })}
							/>
							<Label for="field-sortable" class="cursor-pointer text-xs">Sort</Label>
						</div>
					</div>
				</div>

				<!-- Conditional Visibility -->
				<ConditionalVisibilityBuilder
					value={field.settings?.conditional_visibility as any || null}
					{availableFields}
					onchange={(visibility) => updateSettings({ conditional_visibility: visibility })}
				/>

				<!-- Type-specific Settings -->
				{#if metadata?.isNumeric}
					<div class="space-y-2">
						<Label class="text-sm font-medium">Number Limits</Label>
						<div class="grid grid-cols-2 gap-2">
							<Input
								type="number"
								value={field.settings?.min_value?.toString() || ''}
								oninput={(e) => updateSettings({ min_value: e.currentTarget.value ? parseFloat(e.currentTarget.value) : null })}
								placeholder="Min"
							/>
							<Input
								type="number"
								value={field.settings?.max_value?.toString() || ''}
								oninput={(e) => updateSettings({ max_value: e.currentTarget.value ? parseFloat(e.currentTarget.value) : null })}
								placeholder="Max"
							/>
						</div>
					</div>
				{/if}

				{#if field.type === 'text' || field.type === 'textarea'}
					<div class="space-y-2">
						<Label class="text-sm font-medium">Length Limits</Label>
						<div class="grid grid-cols-2 gap-2">
							<Input
								type="number"
								value={field.settings?.min_length?.toString() || ''}
								oninput={(e) => updateSettings({ min_length: e.currentTarget.value ? parseInt(e.currentTarget.value) : null })}
								placeholder="Min chars"
							/>
							<Input
								type="number"
								value={field.settings?.max_length?.toString() || ''}
								oninput={(e) => updateSettings({ max_length: e.currentTarget.value ? parseInt(e.currentTarget.value) : null })}
								placeholder="Max chars"
							/>
						</div>
					</div>
				{/if}

				{#if field.type === 'currency'}
					<div class="space-y-2">
						<Label class="text-sm font-medium">Currency Settings</Label>
						<div class="grid grid-cols-2 gap-2">
							<Input
								value={field.settings?.currency_code || 'USD'}
								oninput={(e) => updateSettings({ currency_code: e.currentTarget.value })}
								placeholder="Currency code"
								maxlength={3}
							/>
							<Input
								type="number"
								value={field.settings?.precision?.toString() || '2'}
								oninput={(e) => updateSettings({ precision: parseInt(e.currentTarget.value) })}
								placeholder="Decimals"
								min="0"
								max="4"
							/>
						</div>
					</div>
				{/if}

				<!-- Mass Update Option -->
				<div class="flex items-center gap-2 rounded border p-2">
					<Checkbox
						id="field-mass-updatable"
						checked={field.is_mass_updatable ?? true}
						onCheckedChange={(checked) => updateField({ is_mass_updatable: !!checked })}
						disabled={field.type === 'formula'}
					/>
					<Label for="field-mass-updatable" class="cursor-pointer text-xs">Allow Mass Update</Label>
				</div>
			</Collapsible.Content>
		</Collapsible.Root>
	</div>
</div>

<style>
	.field-config-panel {
		width: 420px;
		max-width: 100%;
	}
</style>
