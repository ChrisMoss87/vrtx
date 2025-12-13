<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Switch } from '$lib/components/ui/switch';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import { Plus, Trash2, Info } from 'lucide-svelte';
	import type { Field, Module } from '$lib/api/modules';

	interface FieldUpdate {
		field: string;
		value_type: 'static' | 'field' | 'formula' | 'current_date' | 'current_datetime' | 'current_user';
		value: string;
	}

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		modules?: Module[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], modules = [], onConfigChange }: Props = $props();

	// Local state
	let relationType = $state<string>((config.relation_type as string) || 'linked');
	let relatedModule = $state<string>((config.related_module as string) || '');
	let relationField = $state<string>((config.relation_field as string) || '');
	let updateAll = $state<boolean>((config.update_all as boolean) || false);
	let fieldUpdates = $state<FieldUpdate[]>((config.field_updates as FieldUpdate[]) || [
		{ field: '', value_type: 'static', value: '' }
	]);

	function emitChange() {
		onConfigChange?.({
			relation_type: relationType,
			related_module: relatedModule,
			relation_field: relationField,
			update_all: updateAll,
			field_updates: fieldUpdates.filter(u => u.field)
		});
	}

	function addFieldUpdate() {
		fieldUpdates = [...fieldUpdates, { field: '', value_type: 'static', value: '' }];
	}

	function removeFieldUpdate(index: number) {
		fieldUpdates = fieldUpdates.filter((_, i) => i !== index);
		emitChange();
	}

	function updateFieldUpdate(index: number, key: keyof FieldUpdate, value: string | boolean) {
		fieldUpdates = fieldUpdates.map((update, i) => {
			if (i === index) {
				return { ...update, [key]: value };
			}
			return update;
		});
		emitChange();
	}

	// Get lookup fields for relation selection
	const lookupFields = $derived(moduleFields.filter((f) => f.type === 'lookup' || f.type === 'user'));

	// Get the selected related module's fields
	const relatedModuleObj = $derived(modules.find(m => m.api_name === relatedModule));
	const relatedFields = $derived<Field[]>(relatedModuleObj?.fields || []);
</script>

<div class="space-y-4">
	<h4 class="font-medium">Update Related Record Configuration</h4>

	<!-- Relation Type -->
	<div class="space-y-2">
		<Label>Relation Type</Label>
		<Select.Root
			type="single"
			value={relationType}
			onValueChange={(v) => {
				if (v) {
					relationType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{relationType === 'parent'
					? 'Parent Record (via lookup)'
					: relationType === 'child'
						? 'Child Records'
						: 'Linked Record'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="parent">Parent Record (via lookup)</Select.Item>
				<Select.Item value="child">Child Records</Select.Item>
				<Select.Item value="linked">Linked Record</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Related Module (for child/linked) -->
	{#if relationType !== 'parent'}
		<div class="space-y-2">
			<Label>Related Module</Label>
			<Select.Root
				type="single"
				value={relatedModule}
				onValueChange={(v) => {
					if (v) {
						relatedModule = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{relatedModuleObj?.name || 'Select module'}
				</Select.Trigger>
				<Select.Content>
					{#each modules as module}
						<Select.Item value={module.api_name}>{module.name}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
	{/if}

	<!-- Relation Field -->
	<div class="space-y-2">
		<Label>Relation Field</Label>
		{#if relationType === 'parent'}
			<Select.Root
				type="single"
				value={relationField}
				onValueChange={(v) => {
					if (v) {
						relationField = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{lookupFields.find((f) => f.api_name === relationField)?.label || 'Select lookup field'}
				</Select.Trigger>
				<Select.Content>
					{#each lookupFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			<p class="text-xs text-muted-foreground">
				Select the lookup field that references the parent record
			</p>
		{:else}
			<Input
				value={relationField}
				oninput={(e) => {
					relationField = e.currentTarget.value;
					emitChange();
				}}
				placeholder="e.g., contact_id"
			/>
			<p class="text-xs text-muted-foreground">
				Field on related records that links back to this record
			</p>
		{/if}
	</div>

	<!-- Update All -->
	{#if relationType === 'child'}
		<div class="flex items-center justify-between">
			<div>
				<Label>Update All Matching Records</Label>
				<p class="text-xs text-muted-foreground">
					If unchecked, only updates the first matching record
				</p>
			</div>
			<Switch
				checked={updateAll}
				onCheckedChange={(v) => {
					updateAll = v;
					emitChange();
				}}
			/>
		</div>
	{/if}

	<!-- Field Updates -->
	<div class="space-y-3">
		<div class="flex items-center justify-between">
			<Label>Field Updates</Label>
			<Button variant="outline" size="sm" onclick={addFieldUpdate}>
				<Plus class="mr-1 h-3 w-3" />
				Add Field
			</Button>
		</div>

		{#each fieldUpdates as update, index}
			<div class="rounded-lg border p-3 space-y-3">
				<div class="flex items-start justify-between">
					<span class="text-xs font-medium text-muted-foreground">Update #{index + 1}</span>
					{#if fieldUpdates.length > 1}
						<Button
							variant="ghost"
							size="icon"
							class="h-6 w-6"
							onclick={() => removeFieldUpdate(index)}
						>
							<Trash2 class="h-3 w-3" />
						</Button>
					{/if}
				</div>

				<div class="grid gap-3 sm:grid-cols-3">
					<!-- Field to Update -->
					<div class="space-y-1">
						<Label class="text-xs">Field</Label>
						{#if relatedFields.length > 0}
							<Select.Root
								type="single"
								value={update.field}
								onValueChange={(v) => v && updateFieldUpdate(index, 'field', v)}
							>
								<Select.Trigger class="h-8 text-sm">
									{relatedFields.find((f) => f.api_name === update.field)?.label || 'Select'}
								</Select.Trigger>
								<Select.Content>
									{#each relatedFields as field}
										<Select.Item value={field.api_name}>{field.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						{:else}
							<Input
								value={update.field}
								oninput={(e) => updateFieldUpdate(index, 'field', e.currentTarget.value)}
								placeholder="field_name"
								class="h-8 text-sm"
							/>
						{/if}
					</div>

					<!-- Value Type -->
					<div class="space-y-1">
						<Label class="text-xs">Value Type</Label>
						<Select.Root
							type="single"
							value={update.value_type}
							onValueChange={(v) => v && updateFieldUpdate(index, 'value_type', v)}
						>
							<Select.Trigger class="h-8 text-sm">
								{update.value_type === 'static' ? 'Static' :
								 update.value_type === 'field' ? 'From Field' :
								 update.value_type === 'formula' ? 'Formula' :
								 update.value_type === 'current_date' ? 'Current Date' :
								 update.value_type === 'current_datetime' ? 'Current Date/Time' :
								 'Current User'}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="static">Static Value</Select.Item>
								<Select.Item value="field">From Source Field</Select.Item>
								<Select.Item value="formula">Formula</Select.Item>
								<Select.Item value="current_date">Current Date</Select.Item>
								<Select.Item value="current_datetime">Current Date/Time</Select.Item>
								<Select.Item value="current_user">Current User</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>

					<!-- Value -->
					{#if !['current_date', 'current_datetime', 'current_user'].includes(update.value_type)}
						<div class="space-y-1">
							<Label class="text-xs">Value</Label>
							{#if update.value_type === 'field'}
								<Select.Root
									type="single"
									value={update.value}
									onValueChange={(v) => v && updateFieldUpdate(index, 'value', v)}
								>
									<Select.Trigger class="h-8 text-sm">
										{moduleFields.find((f) => f.api_name === update.value)?.label || 'Select'}
									</Select.Trigger>
									<Select.Content>
										{#each moduleFields as field}
											<Select.Item value={field.api_name}>{field.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							{:else}
								<Input
									value={update.value}
									oninput={(e) => updateFieldUpdate(index, 'value', e.currentTarget.value)}
									placeholder={update.value_type === 'formula' ? '{{record.amount}} * 1.1' : 'Enter value'}
									class="h-8 text-sm"
								/>
							{/if}
						</div>
					{/if}
				</div>
			</div>
		{/each}
	</div>

	<!-- Help Text -->
	<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
		<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
		<div class="text-xs text-muted-foreground space-y-1">
			<p><strong>Parent:</strong> Updates the record referenced by a lookup field</p>
			<p><strong>Child:</strong> Updates records that reference this record</p>
			<p><strong>Linked:</strong> Updates a specific related record by ID</p>
		</div>
	</div>
</div>
