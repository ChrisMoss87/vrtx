<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trash2, Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import type { ActionType } from '$lib/api/workflows';
	import VariableInserter from '../VariableInserter.svelte';

	interface FieldMapping {
		target_field: string;
		value_type: 'static' | 'field' | 'formula';
		value: string;
	}

	interface Props {
		config: Record<string, unknown>;
		actionType: ActionType;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, actionType, moduleFields = [], onConfigChange }: Props = $props();

	const isUpdate = actionType === 'update_record';

	// Local state
	let targetModuleId = $state<number | null>((config.target_module_id as number) || null);
	let fieldMappings = $state<FieldMapping[]>((config.field_mappings as FieldMapping[]) || []);
	let linkToRecord = $state<boolean>((config.link_to_record as boolean) ?? true);

	function emitChange() {
		onConfigChange?.({
			target_module_id: targetModuleId,
			field_mappings: fieldMappings,
			link_to_record: linkToRecord
		});
	}

	function addFieldMapping() {
		fieldMappings = [
			...fieldMappings,
			{ target_field: '', value_type: 'static', value: '' }
		];
		emitChange();
	}

	function removeFieldMapping(index: number) {
		fieldMappings = fieldMappings.filter((_, i) => i !== index);
		emitChange();
	}

	function updateFieldMapping(index: number, updates: Partial<FieldMapping>) {
		fieldMappings = fieldMappings.map((m, i) =>
			i === index ? { ...m, ...updates } : m
		);
		emitChange();
	}

	// For now, use the same module fields as target (in real app, would fetch target module fields)
	const targetFields = $derived(moduleFields);
</script>

<div class="space-y-4">
	<h4 class="font-medium">{isUpdate ? 'Update Record' : 'Create Record'} Configuration</h4>

	{#if !isUpdate}
		<!-- Target Module Selection (for create only) -->
		<div class="space-y-2">
			<Label>Target Module</Label>
			<Select.Root
				type="single"
				value={targetModuleId ? String(targetModuleId) : ''}
				onValueChange={(v) => {
					targetModuleId = v ? parseInt(v) : null;
					emitChange();
				}}
			>
				<Select.Trigger>
					{targetModuleId ? 'Same Module (Self)' : 'Select target module'}
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="self">Same Module (Self)</Select.Item>
					<!-- In real implementation, list all modules here -->
				</Select.Content>
			</Select.Root>
			<p class="text-xs text-muted-foreground">
				Select which module to create the new record in
			</p>
		</div>
	{/if}

	<!-- Field Mappings -->
	<div class="space-y-2">
		<Label>Field Values</Label>
		<p class="text-xs text-muted-foreground">
			{isUpdate ? 'Set field values to update' : 'Set field values for the new record'}
		</p>

		{#if fieldMappings.length > 0}
			<div class="space-y-3">
				{#each fieldMappings as mapping, index}
					<div class="flex items-start gap-2 rounded-lg border bg-background p-3">
						<div class="grid flex-1 gap-2 sm:grid-cols-3">
							<!-- Target Field -->
							<div class="space-y-1">
								<Label class="text-xs text-muted-foreground">Field</Label>
								<Select.Root
									type="single"
									value={mapping.target_field}
									onValueChange={(v) => v && updateFieldMapping(index, { target_field: v })}
								>
									<Select.Trigger class="h-9">
										{targetFields.find((f) => f.api_name === mapping.target_field)?.label ||
											'Select field'}
									</Select.Trigger>
									<Select.Content>
										{#each targetFields as field}
											<Select.Item value={field.api_name}>{field.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>

							<!-- Value Type -->
							<div class="space-y-1">
								<Label class="text-xs text-muted-foreground">Value Type</Label>
								<Select.Root
									type="single"
									value={mapping.value_type}
									onValueChange={(v) =>
										v && updateFieldMapping(index, { value_type: v as FieldMapping['value_type'] })}
								>
									<Select.Trigger class="h-9">
										{mapping.value_type === 'static'
											? 'Static Value'
											: mapping.value_type === 'field'
												? 'From Field'
												: 'Formula'}
									</Select.Trigger>
									<Select.Content>
										<Select.Item value="static">Static Value</Select.Item>
										<Select.Item value="field">From Record Field</Select.Item>
										<Select.Item value="formula">Formula</Select.Item>
									</Select.Content>
								</Select.Root>
							</div>

							<!-- Value -->
							<div class="space-y-1">
								<div class="flex items-center justify-between">
									<Label class="text-xs text-muted-foreground">Value</Label>
									{#if mapping.value_type === 'static'}
										<VariableInserter
											fields={moduleFields}
											onInsert={(v) => updateFieldMapping(index, { value: `{{${v}}}` })}
										/>
									{/if}
								</div>
								{#if mapping.value_type === 'field'}
									<Select.Root
										type="single"
										value={mapping.value}
										onValueChange={(v) => v && updateFieldMapping(index, { value: v })}
									>
										<Select.Trigger class="h-9">
											{moduleFields.find((f) => f.api_name === mapping.value)?.label ||
												'Select source field'}
										</Select.Trigger>
										<Select.Content>
											{#each moduleFields as field}
												<Select.Item value={field.api_name}>{field.label}</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>
								{:else}
									<Input
										class="h-9"
										value={mapping.value}
										oninput={(e) => updateFieldMapping(index, { value: e.currentTarget.value })}
										placeholder={mapping.value_type === 'formula' ? 'e.g., {{amount}} * 1.1' : 'Enter value'}
									/>
								{/if}
							</div>
						</div>

						<Button
							type="button"
							variant="ghost"
							size="icon"
							class="mt-5 h-7 w-7"
							onclick={() => removeFieldMapping(index)}
						>
							<Trash2 class="h-3.5 w-3.5" />
						</Button>
					</div>
				{/each}
			</div>
		{/if}

		<Button type="button" variant="outline" size="sm" onclick={addFieldMapping}>
			<Plus class="mr-2 h-4 w-4" />
			Add Field Mapping
		</Button>
	</div>

	{#if !isUpdate}
		<!-- Link to Record -->
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The new record will be automatically linked to the triggering record if a relationship exists.
			</p>
		</div>
	{/if}
</div>
