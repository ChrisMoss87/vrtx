<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import {
		type WebFormField,
		type WebFormFieldOption,
		FIELD_TYPES,
		fieldTypeHasOptions
	} from '$lib/api/web-forms';

	interface ModuleField {
		id: number;
		label: string;
		api_name: string;
		field_type: string;
		is_required: boolean;
	}

	interface Props {
		field: WebFormField;
		moduleFields: ModuleField[];
		onUpdate: (updates: Partial<WebFormField>) => void;
	}

	let { field, moduleFields, onUpdate }: Props = $props();

	// Options management
	function addOption() {
		const options = [...(field.options ?? []), { value: '', label: '' }];
		onUpdate({ options });
	}

	function updateOption(index: number, key: 'value' | 'label', value: string) {
		const options = [...(field.options ?? [])];
		options[index] = { ...options[index], [key]: value };
		onUpdate({ options });
	}

	function removeOption(index: number) {
		const options = (field.options ?? []).filter((_, i) => i !== index);
		onUpdate({ options });
	}

	// Get compatible module fields based on form field type
	const compatibleModuleFields = $derived(() => {
		const typeMap: Record<string, string[]> = {
			text: ['text', 'varchar', 'string'],
			email: ['email', 'text'],
			phone: ['phone', 'text'],
			textarea: ['textarea', 'text', 'longtext'],
			select: ['select', 'picklist'],
			multi_select: ['multi_select', 'multiselect'],
			checkbox: ['boolean', 'checkbox'],
			radio: ['select', 'picklist'],
			date: ['date'],
			datetime: ['datetime'],
			number: ['number', 'integer', 'decimal'],
			currency: ['currency', 'decimal', 'number'],
			file: ['file', 'attachment'],
			hidden: ['text', 'varchar', 'string'],
			url: ['url', 'text']
		};

		const compatibleTypes = typeMap[field.field_type] ?? ['text'];
		return moduleFields.filter((f) => compatibleTypes.some((t) => f.field_type.includes(t)));
	});
</script>

<Card.Root>
	<Card.Header class="py-3">
		<Card.Title class="text-sm">Field Settings: {FIELD_TYPES[field.field_type]}</Card.Title>
	</Card.Header>
	<Card.Content class="space-y-4">
		<!-- Basic Settings -->
		<div class="grid gap-4 sm:grid-cols-2">
			<div class="space-y-2">
				<Label for="field_label">Label *</Label>
				<Input
					id="field_label"
					value={field.label}
					oninput={(e: Event) => onUpdate({ label: (e.target as HTMLInputElement).value })}
					placeholder="Full Name"
				/>
			</div>
			<div class="space-y-2">
				<Label for="field_name">Field Name</Label>
				<Input
					id="field_name"
					value={field.name ?? ''}
					oninput={(e: Event) =>
						onUpdate({ name: (e.target as HTMLInputElement).value || undefined })}
					placeholder="full_name"
				/>
				<p class="text-xs text-muted-foreground">Auto-generated from label if empty</p>
			</div>
		</div>

		{#if field.field_type !== 'hidden'}
			<div class="space-y-2">
				<Label for="field_placeholder">Placeholder</Label>
				<Input
					id="field_placeholder"
					value={field.placeholder ?? ''}
					oninput={(e: Event) =>
						onUpdate({ placeholder: (e.target as HTMLInputElement).value || undefined })}
					placeholder="Enter your full name..."
				/>
			</div>
		{/if}

		<!-- Module Field Mapping -->
		<div class="space-y-2">
			<Label for="module_field">Map to Module Field</Label>
			<Select.Root
				type="single"
				value={field.module_field_id ? String(field.module_field_id) : undefined}
				onValueChange={(v) => onUpdate({ module_field_id: v ? parseInt(v) : null })}
			>
				<Select.Trigger id="module_field" class="w-full">
					{#if field.module_field_id}
						{moduleFields.find((f) => f.id === field.module_field_id)?.label ?? 'Unknown field'}
					{:else}
						Select module field...
					{/if}
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="">None</Select.Item>
					{#each compatibleModuleFields() as mf}
						<Select.Item value={String(mf.id)}>{mf.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			<p class="text-xs text-muted-foreground">The CRM field this form field will populate</p>
		</div>

		<!-- Required Toggle -->
		<div class="flex items-center justify-between">
			<div>
				<Label>Required Field</Label>
				<p class="text-xs text-muted-foreground">User must fill this field to submit</p>
			</div>
			<Switch
				checked={field.is_required}
				onCheckedChange={(checked) => onUpdate({ is_required: checked })}
			/>
		</div>

		<!-- Options (for select, radio, checkbox, multi_select) -->
		{#if fieldTypeHasOptions(field.field_type)}
			<div class="space-y-2">
				<div class="flex items-center justify-between">
					<Label>Options</Label>
					<Button variant="outline" size="sm" onclick={addOption}>Add Option</Button>
				</div>
				<div class="space-y-2">
					{#each field.options ?? [] as option, index}
						<div class="flex items-center gap-2">
							<Input
								value={option.value}
								oninput={(e: Event) =>
									updateOption(index, 'value', (e.target as HTMLInputElement).value)}
								placeholder="Value"
								class="flex-1"
							/>
							<Input
								value={option.label}
								oninput={(e: Event) =>
									updateOption(index, 'label', (e.target as HTMLInputElement).value)}
								placeholder="Label"
								class="flex-1"
							/>
							<Button variant="ghost" size="sm" onclick={() => removeOption(index)}>
								<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
									<path d="M6 18L18 6M6 6l12 12" stroke-width="2" />
								</svg>
							</Button>
						</div>
					{/each}
					{#if (field.options ?? []).length === 0}
						<p class="text-sm text-muted-foreground py-2">No options added yet</p>
					{/if}
				</div>
			</div>
		{/if}

		<!-- Validation Rules -->
		{#if field.field_type === 'text' || field.field_type === 'textarea' || field.field_type === 'email'}
			<div class="space-y-2">
				<Label>Validation</Label>
				<div class="grid gap-4 sm:grid-cols-2">
					<div class="space-y-2">
						<Label for="min_length" class="text-xs">Min Length</Label>
						<Input
							id="min_length"
							type="number"
							min="0"
							value={field.validation_rules?.min_length ?? ''}
							oninput={(e: Event) => {
								const val = (e.target as HTMLInputElement).value;
								onUpdate({
									validation_rules: {
										...field.validation_rules,
										min_length: val ? parseInt(val) : undefined
									}
								});
							}}
						/>
					</div>
					<div class="space-y-2">
						<Label for="max_length" class="text-xs">Max Length</Label>
						<Input
							id="max_length"
							type="number"
							min="0"
							value={field.validation_rules?.max_length ?? ''}
							oninput={(e: Event) => {
								const val = (e.target as HTMLInputElement).value;
								onUpdate({
									validation_rules: {
										...field.validation_rules,
										max_length: val ? parseInt(val) : undefined
									}
								});
							}}
						/>
					</div>
				</div>
			</div>
		{/if}

		{#if field.field_type === 'file'}
			<div class="space-y-2">
				<Label>File Upload Settings</Label>
				<div class="grid gap-4 sm:grid-cols-2">
					<div class="space-y-2">
						<Label for="max_size" class="text-xs">Max Size (KB)</Label>
						<Input
							id="max_size"
							type="number"
							min="0"
							value={field.validation_rules?.max_size ?? 10240}
							oninput={(e: Event) => {
								const val = (e.target as HTMLInputElement).value;
								onUpdate({
									validation_rules: {
										...field.validation_rules,
										max_size: val ? parseInt(val) : 10240
									}
								});
							}}
						/>
					</div>
					<div class="space-y-2">
						<Label for="allowed_types" class="text-xs">Allowed Types</Label>
						<Input
							id="allowed_types"
							value={(field.validation_rules?.allowed_types ?? []).join(', ')}
							oninput={(e: Event) => {
								const val = (e.target as HTMLInputElement).value;
								onUpdate({
									validation_rules: {
										...field.validation_rules,
										allowed_types: val
											? val.split(',').map((t) => t.trim().toLowerCase())
											: undefined
									}
								});
							}}
							placeholder="pdf, jpg, png"
						/>
					</div>
				</div>
			</div>
		{/if}

		<!-- Hidden Field Value -->
		{#if field.field_type === 'hidden'}
			<div class="space-y-2">
				<Label for="hidden_value">Default Value</Label>
				<Input
					id="hidden_value"
					value={field.placeholder ?? ''}
					oninput={(e: Event) =>
						onUpdate({ placeholder: (e.target as HTMLInputElement).value || undefined })}
					placeholder="utm_source=website"
				/>
				<p class="text-xs text-muted-foreground">
					Hidden fields can capture UTM parameters or other data
				</p>
			</div>
		{/if}
	</Card.Content>
</Card.Root>
