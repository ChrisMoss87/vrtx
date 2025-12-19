<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Drawer from '$lib/components/ui/drawer';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import { Plus, Trash2, Save, X } from 'lucide-svelte';

	interface FieldOption {
		id?: number;
		label: string;
		value: string;
		color: string | null;
		order: number;
		is_default: boolean;
	}

	interface Field {
		id?: number;
		type: string;
		api_name: string;
		label: string;
		description: string | null;
		help_text: string | null;
		is_required: boolean;
		is_unique: boolean;
		is_searchable: boolean;
		order: number;
		default_value: string | null;
		validation_rules: Record<string, any>;
		settings: Record<string, any>;
		width: number;
		options: FieldOption[];
	}

	interface Props {
		open: boolean;
		field: Field | null;
		fieldTypes: string[];
		isNew?: boolean;
		onSave: (field: Field) => void;
		onCancel: () => void;
	}

	let { open = $bindable(), field, fieldTypes, isNew = false, onSave, onCancel }: Props = $props();

	// Create a working copy of the field
	let editField = $state<Field | null>(null);

	// Initialize or reset editField when field changes
	$effect(() => {
		if (field && open) {
			editField = JSON.parse(JSON.stringify(field));
		}
	});

	// Helper to generate API name from label
	function generateApiName(label: string): string {
		return label
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '')
			.replace(/_{2,}/g, '_');
	}

	// Handle label change to auto-generate API name for new fields
	function handleLabelChange() {
		if (!editField) return;
		if (isNew && editField.label) {
			editField.api_name = generateApiName(editField.label);
		}
	}

	// Add option for select/radio/multiselect fields
	function addOption() {
		if (!editField) return;
		if (!editField.options) editField.options = [];
		editField.options.push({
			label: '',
			value: '',
			color: null,
			order: editField.options.length,
			is_default: false
		});
	}

	// Remove option
	function removeOption(index: number) {
		if (!editField) return;
		editField.options = editField.options.filter((_, i) => i !== index);
	}

	// Handle save
	function handleSave() {
		if (!editField) return;
		onSave(editField);
		open = false;
	}

	// Handle cancel
	function handleCancel() {
		onCancel();
		open = false;
	}

	// Check if field type needs options
	const needsOptions = $derived(['select', 'radio', 'multiselect'].includes(editField?.type || ''));

	// Check if field type is numeric
	const isNumericType = $derived(
		['number', 'decimal', 'currency', 'percent'].includes(editField?.type || '')
	);

	// Check if field type is text-based
	const isTextType = $derived(
		['text', 'textarea', 'email', 'phone', 'url'].includes(editField?.type || '')
	);

	// Check if field type is date-based
	const isDateType = $derived(['date', 'datetime', 'time'].includes(editField?.type || ''));
</script>

<Drawer.Root bind:open>
	<Drawer.Content class="max-h-[90vh]">
		<Drawer.Header>
			<Drawer.Title>{isNew ? 'Add New Field' : 'Edit Field'}</Drawer.Title>
			<Drawer.Description>Configure field properties and type-specific settings</Drawer.Description>
		</Drawer.Header>

		{#if editField}
			<div class="overflow-y-auto px-6 pb-6">
				<div class="space-y-6">
					<!-- Basic Field Properties -->
					<div class="space-y-4">
						<h3 class="text-sm font-medium">Basic Information</h3>

						<!-- Label -->
						<div class="space-y-2">
							<Label for="field-label">Label *</Label>
							<Input
								id="field-label"
								bind:value={editField.label}
								placeholder="e.g., First Name, Email Address"
								oninput={handleLabelChange}
								required
							/>
						</div>

						<!-- API Name -->
						<div class="space-y-2">
							<Label for="field-api-name">API Name *</Label>
							<Input
								id="field-api-name"
								bind:value={editField.api_name}
								placeholder="e.g., first_name, email_address"
								pattern="[a-z][a-z0-9_]*"
								required
							/>
							<p class="text-xs text-muted-foreground">
								Lowercase letters, numbers, and underscores only
							</p>
						</div>

						<!-- Field Type -->
						<div class="space-y-2">
							<Label for="field-type">Field Type *</Label>
							<select
								id="field-type"
								bind:value={editField.type}
								class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
								required
							>
								{#each fieldTypes as fieldType}
									<option value={fieldType}>{fieldType}</option>
								{/each}
							</select>
						</div>

						<!-- Width -->
						<div class="space-y-2">
							<Label for="field-width">Field Width</Label>
							<select
								id="field-width"
								bind:value={editField.width}
								class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
							>
								<option value={25}>25% - Quarter width</option>
								<option value={33}>33% - Third width</option>
								<option value={50}>50% - Half width</option>
								<option value={66}>66% - Two thirds width</option>
								<option value={75}>75% - Three quarters width</option>
								<option value={100}>100% - Full width</option>
							</select>
						</div>

						<!-- Description -->
						<div class="space-y-2">
							<Label for="field-description">Description</Label>
							<Input
								id="field-description"
								bind:value={editField.description}
								placeholder="Brief description of this field"
							/>
						</div>

						<!-- Help Text -->
						<div class="space-y-2">
							<Label for="field-help">Help Text</Label>
							<Textarea
								id="field-help"
								bind:value={editField.help_text}
								placeholder="Instructions or guidance for users filling this field"
								rows={2}
							/>
						</div>
					</div>

					<!-- Field Flags -->
					<div class="space-y-4">
						<h3 class="text-sm font-medium">Field Options</h3>

						<div class="flex items-center justify-between space-x-2 rounded-lg border p-3">
							<div class="space-y-0.5">
								<Label for="field-required">Required</Label>
								<p class="text-xs text-muted-foreground">This field must be filled in</p>
							</div>
							<Switch id="field-required" bind:checked={editField.is_required} />
						</div>

						<div class="flex items-center justify-between space-x-2 rounded-lg border p-3">
							<div class="space-y-0.5">
								<Label for="field-unique">Unique</Label>
								<p class="text-xs text-muted-foreground">No duplicate values allowed</p>
							</div>
							<Switch id="field-unique" bind:checked={editField.is_unique} />
						</div>

						<div class="flex items-center justify-between space-x-2 rounded-lg border p-3">
							<div class="space-y-0.5">
								<Label for="field-searchable">Searchable</Label>
								<p class="text-xs text-muted-foreground">Include in global search results</p>
							</div>
							<Switch id="field-searchable" bind:checked={editField.is_searchable} />
						</div>
					</div>

					<!-- Text Field Settings -->
					{#if isTextType}
						<div class="space-y-4">
							<h3 class="text-sm font-medium">Text Settings</h3>

							<div class="grid grid-cols-2 gap-4">
								<div class="space-y-2">
									<Label for="min-length">Minimum Length</Label>
									<Input
										id="min-length"
										type="number"
										bind:value={editField.validation_rules.min_length}
										placeholder="e.g., 3"
										min="0"
									/>
								</div>

								<div class="space-y-2">
									<Label for="max-length">Maximum Length</Label>
									<Input
										id="max-length"
										type="number"
										bind:value={editField.validation_rules.max_length}
										placeholder="e.g., 255"
										min="0"
									/>
								</div>
							</div>

							<div class="space-y-2">
								<Label for="placeholder">Placeholder Text</Label>
								<Input
									id="placeholder"
									bind:value={editField.settings.placeholder}
									placeholder="e.g., Enter your name"
								/>
							</div>
						</div>
					{/if}

					<!-- Number Field Settings -->
					{#if isNumericType}
						<div class="space-y-4">
							<h3 class="text-sm font-medium">Number Settings</h3>

							<div class="grid grid-cols-2 gap-4">
								<div class="space-y-2">
									<Label for="min-value">Minimum Value</Label>
									<Input
										id="min-value"
										type="number"
										bind:value={editField.validation_rules.min}
										placeholder="e.g., 0"
										step="any"
									/>
								</div>

								<div class="space-y-2">
									<Label for="max-value">Maximum Value</Label>
									<Input
										id="max-value"
										type="number"
										bind:value={editField.validation_rules.max}
										placeholder="e.g., 100"
										step="any"
									/>
								</div>
							</div>

							{#if ['decimal', 'currency', 'percent'].includes(editField.type)}
								<div class="space-y-2">
									<Label for="decimal-places">Decimal Places</Label>
									<Input
										id="decimal-places"
										type="number"
										bind:value={editField.settings.decimal_places}
										placeholder="e.g., 2"
										min="0"
										max="10"
									/>
								</div>
							{/if}

							<div class="space-y-2">
								<Label for="step">Step Value</Label>
								<Input
									id="step"
									type="number"
									bind:value={editField.settings.step}
									placeholder="e.g., 1 or 0.01"
									step="any"
								/>
								<p class="text-xs text-muted-foreground">
									Increment/decrement amount for number controls
								</p>
							</div>
						</div>
					{/if}

					<!-- Date Field Settings -->
					{#if isDateType}
						<div class="space-y-4">
							<h3 class="text-sm font-medium">Date/Time Settings</h3>

							{#if editField.type === 'date' || editField.type === 'datetime'}
								<div class="grid grid-cols-2 gap-4">
									<div class="space-y-2">
										<Label for="min-date">Minimum Date</Label>
										<Input
											id="min-date"
											type="date"
											bind:value={editField.validation_rules.min_date}
										/>
									</div>

									<div class="space-y-2">
										<Label for="max-date">Maximum Date</Label>
										<Input
											id="max-date"
											type="date"
											bind:value={editField.validation_rules.max_date}
										/>
									</div>
								</div>
							{/if}

							<div class="flex items-center justify-between space-x-2 rounded-lg border p-3">
								<div class="space-y-0.5">
									<Label for="default-today">Default to Today</Label>
									<p class="text-xs text-muted-foreground">Auto-fill with current date</p>
								</div>
								<Switch id="default-today" bind:checked={editField.settings.default_to_today} />
							</div>
						</div>
					{/if}

					<!-- Select/Radio/Multiselect Options -->
					{#if needsOptions}
						<div class="space-y-4">
							<div class="flex items-center justify-between">
								<h3 class="text-sm font-medium">Options</h3>
								<Button variant="outline" size="sm" onclick={addOption}>
									<Plus class="mr-2 h-4 w-4" />
									Add Option
								</Button>
							</div>

							{#if !editField.options || editField.options.length === 0}
								<p class="text-sm text-muted-foreground">
									No options defined. Add at least one option.
								</p>
							{:else}
								<div class="space-y-2">
									{#each editField.options as option, optIndex}
										<div class="flex items-start gap-2 rounded-lg border p-3">
											<div class="grid flex-1 grid-cols-2 gap-2">
												<div class="space-y-1">
													<Label class="text-xs">Label</Label>
													<Input bind:value={option.label} placeholder="Display text" class="h-8" />
												</div>

												<div class="space-y-1">
													<Label class="text-xs">Value</Label>
													<Input bind:value={option.value} placeholder="Saved value" class="h-8" />
												</div>

												<div class="col-span-2 flex items-center gap-4">
													<label class="flex items-center gap-2 text-xs">
														<input
															type="checkbox"
															bind:checked={option.is_default}
															class="rounded"
														/>
														Default
													</label>

													<div class="flex flex-1 items-center gap-2">
														<Label class="text-xs">Color</Label>
														<input
															type="color"
															bind:value={option.color}
															class="h-6 w-12 rounded border"
														/>
													</div>
												</div>
											</div>

											<Button
												variant="ghost"
												size="icon"
												onclick={() => removeOption(optIndex)}
												class="h-8 w-8"
											>
												<Trash2 class="h-4 w-4" />
											</Button>
										</div>
									{/each}
								</div>
							{/if}
						</div>
					{/if}
				</div>
			</div>

			<Drawer.Footer class="border-t pt-4">
				<Button onclick={handleSave} class="w-full">
					<Save class="mr-2 h-4 w-4" />
					Save Field
				</Button>
				<Button variant="outline" onclick={handleCancel} class="w-full">
					<X class="mr-2 h-4 w-4" />
					Cancel
				</Button>
			</Drawer.Footer>
		{/if}
	</Drawer.Content>
</Drawer.Root>
