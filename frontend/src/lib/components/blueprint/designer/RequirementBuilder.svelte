<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import type { BlueprintTransitionRequirement } from '$lib/api/blueprints';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import TrashIcon from '@lucide/svelte/icons/trash-2';
	import ListChecksIcon from '@lucide/svelte/icons/list-checks';
	import GripVerticalIcon from '@lucide/svelte/icons/grip-vertical';
	import FileIcon from '@lucide/svelte/icons/file';
	import MessageSquareIcon from '@lucide/svelte/icons/message-square';
	import CheckSquareIcon from '@lucide/svelte/icons/check-square';
	import FormInputIcon from '@lucide/svelte/icons/form-input';

	interface Field {
		id: number;
		api_name: string;
		label: string;
		type: string;
	}

	interface Props {
		requirements: BlueprintTransitionRequirement[];
		fields: Field[];
		readonly?: boolean;
		onAdd?: (requirement: Partial<BlueprintTransitionRequirement>) => void;
		onUpdate?: (id: number, requirement: Partial<BlueprintTransitionRequirement>) => void;
		onDelete?: (id: number) => void;
	}

	let {
		requirements = [],
		fields = [],
		readonly = false,
		onAdd,
		onUpdate,
		onDelete
	}: Props = $props();

	let showAddForm = $state(false);
	let newRequirement = $state<{
		type: string;
		field_id?: number;
		label: string;
		description: string;
		is_required: boolean;
		config: Record<string, unknown>;
	}>({
		type: 'mandatory_field',
		field_id: undefined,
		label: '',
		description: '',
		is_required: true,
		config: {}
	});

	// Checklist items for checklist type
	let checklistItems = $state<Array<{ label: string; required: boolean }>>([]);
	let newChecklistItem = $state('');

	const requirementTypes = [
		{
			value: 'mandatory_field',
			label: 'Mandatory Field',
			description: 'User must fill in a specific field',
			icon: FormInputIcon,
			color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
		},
		{
			value: 'attachment',
			label: 'Attachment',
			description: 'User must upload a file',
			icon: FileIcon,
			color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
		},
		{
			value: 'note',
			label: 'Note',
			description: 'User must add a note or comment',
			icon: MessageSquareIcon,
			color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
		},
		{
			value: 'checklist',
			label: 'Checklist',
			description: 'User must complete a checklist',
			icon: CheckSquareIcon,
			color: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
		}
	];

	function getFieldById(id: number | null | undefined): Field | undefined {
		if (!id) return undefined;
		return fields.find((f) => f.id === id);
	}

	function getTypeInfo(type: string) {
		return requirementTypes.find((t) => t.value === type);
	}

	function handleAddRequirement() {
		const config: Record<string, unknown> = { ...newRequirement.config };

		// Add type-specific config
		if (newRequirement.type === 'checklist') {
			config.items = checklistItems.map((item, index) => ({
				id: `item_${index}`,
				label: item.label,
				required: item.required
			}));
		}

		onAdd?.({
			type: newRequirement.type as BlueprintTransitionRequirement['type'],
			field_id: newRequirement.type === 'mandatory_field' ? newRequirement.field_id : undefined,
			label: newRequirement.label || undefined,
			description: newRequirement.description || undefined,
			is_required: newRequirement.is_required,
			config: Object.keys(config).length > 0 ? config : undefined,
			display_order: requirements.length
		});

		// Reset form
		resetForm();
	}

	function resetForm() {
		newRequirement = {
			type: 'mandatory_field',
			field_id: undefined,
			label: '',
			description: '',
			is_required: true,
			config: {}
		};
		checklistItems = [];
		newChecklistItem = '';
		showAddForm = false;
	}

	function handleDeleteRequirement(id: number) {
		if (confirm('Delete this requirement?')) {
			onDelete?.(id);
		}
	}

	function addChecklistItem() {
		if (newChecklistItem.trim()) {
			checklistItems = [...checklistItems, { label: newChecklistItem.trim(), required: false }];
			newChecklistItem = '';
		}
	}

	function removeChecklistItem(index: number) {
		checklistItems = checklistItems.filter((_, i) => i !== index);
	}

	function toggleChecklistItemRequired(index: number) {
		checklistItems = checklistItems.map((item, i) =>
			i === index ? { ...item, required: !item.required } : item
		);
	}

	const selectedField = $derived(getFieldById(newRequirement.field_id));
	const canAdd = $derived(() => {
		if (newRequirement.type === 'mandatory_field') {
			return !!newRequirement.field_id;
		}
		if (newRequirement.type === 'checklist') {
			return checklistItems.length > 0;
		}
		return true;
	});
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<div class="flex items-center gap-2">
			<ListChecksIcon class="h-5 w-5 text-blue-500" />
			<Card.Title class="text-base">During-Phase Requirements</Card.Title>
		</div>
		<Card.Description>
			What users must provide to complete this transition.
		</Card.Description>
	</Card.Header>

	<Card.Content class="space-y-4">
		{#if requirements.length === 0 && !showAddForm}
			<div class="rounded-lg border border-dashed p-4 text-center">
				<p class="text-sm text-muted-foreground">No requirements configured</p>
				<p class="mt-1 text-xs text-muted-foreground">
					Users can complete this transition immediately without additional input.
				</p>
				{#if !readonly}
					<Button variant="outline" size="sm" class="mt-3" onclick={() => (showAddForm = true)}>
						<PlusIcon class="mr-2 h-4 w-4" />
						Add Requirement
					</Button>
				{/if}
			</div>
		{:else}
			<!-- Existing requirements -->
			<div class="space-y-2">
				{#each requirements as requirement (requirement.id)}
					{@const typeInfo = getTypeInfo(requirement.type)}
					{@const field = getFieldById(requirement.field_id)}
					{@const TypeIcon = typeInfo?.icon || ListChecksIcon}
					<div class="flex items-center gap-2 rounded-lg border bg-card p-3">
						{#if !readonly}
							<GripVerticalIcon class="h-4 w-4 cursor-move text-muted-foreground" />
						{/if}

						<div class="flex h-8 w-8 shrink-0 items-center justify-center rounded {typeInfo?.color}">
							<TypeIcon class="h-4 w-4" />
						</div>

						<div class="flex-1">
							<div class="flex items-center gap-2">
								<span class="font-medium">
									{requirement.label || field?.label || typeInfo?.label || 'Requirement'}
								</span>
								{#if requirement.is_required}
									<Badge variant="destructive" class="h-5 text-[10px]">Required</Badge>
								{:else}
									<Badge variant="outline" class="h-5 text-[10px]">Optional</Badge>
								{/if}
							</div>
							{#if requirement.description}
								<p class="mt-0.5 text-xs text-muted-foreground">{requirement.description}</p>
							{/if}
							{#if requirement.type === 'checklist' && requirement.config?.items}
								<div class="mt-1 text-xs text-muted-foreground">
									{(requirement.config.items as Array<unknown>).length} checklist items
								</div>
							{/if}
						</div>

						{#if !readonly}
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8 shrink-0 text-destructive hover:bg-destructive/10"
								onclick={() => handleDeleteRequirement(requirement.id)}
							>
								<TrashIcon class="h-4 w-4" />
							</Button>
						{/if}
					</div>
				{/each}
			</div>

			<!-- Add new requirement form -->
			{#if showAddForm && !readonly}
				<div class="rounded-lg border bg-muted/50 p-4">
					<div class="mb-3 text-sm font-medium">New Requirement</div>

					<div class="grid gap-4">
						<!-- Requirement Type -->
						<div class="space-y-1.5">
							<Label class="text-xs">Type</Label>
							<Select.Root
								type="single"
								value={newRequirement.type}
								onValueChange={(v) => (newRequirement.type = v)}
							>
								<Select.Trigger>
									{getTypeInfo(newRequirement.type)?.label || 'Select type...'}
								</Select.Trigger>
								<Select.Content>
									{#each requirementTypes as type}
										{@const TypeIcon = type.icon}
										<Select.Item value={type.value}>
											<div class="flex items-center gap-2">
												<TypeIcon class="h-4 w-4" />
												<div>
													<div>{type.label}</div>
													<div class="text-xs text-muted-foreground">{type.description}</div>
												</div>
											</div>
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>

						<!-- Type-specific fields -->
						{#if newRequirement.type === 'mandatory_field'}
							<div class="space-y-1.5">
								<Label class="text-xs">Field</Label>
								<Select.Root
									type="single"
									value={newRequirement.field_id?.toString() || ''}
									onValueChange={(v) => (newRequirement.field_id = parseInt(v))}
								>
									<Select.Trigger>
										{selectedField?.label || 'Select field...'}
									</Select.Trigger>
									<Select.Content class="max-h-[300px]">
										{#each fields as field}
											<Select.Item value={field.id.toString()}>
												<div class="flex items-center gap-2">
													<span>{field.label}</span>
													<span class="text-xs text-muted-foreground">({field.type})</span>
												</div>
											</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>
						{:else if newRequirement.type === 'attachment'}
							<div class="space-y-1.5">
								<Label class="text-xs">Label</Label>
								<Input
									placeholder="e.g., Upload signed contract"
									bind:value={newRequirement.label}
								/>
							</div>
							<div class="grid grid-cols-2 gap-3">
								<div class="space-y-1.5">
									<Label class="text-xs">Allowed types (comma separated)</Label>
									<Input
										placeholder="pdf,doc,docx"
										value={(newRequirement.config.allowed_types as string) || ''}
										oninput={(e) =>
											(newRequirement.config = {
												...newRequirement.config,
												allowed_types: e.currentTarget.value
											})}
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Max size (MB)</Label>
									<Input
										type="number"
										placeholder="10"
										value={(newRequirement.config.max_size_mb as string) || ''}
										oninput={(e) =>
											(newRequirement.config = {
												...newRequirement.config,
												max_size_mb: e.currentTarget.value
											})}
									/>
								</div>
							</div>
						{:else if newRequirement.type === 'note'}
							<div class="space-y-1.5">
								<Label class="text-xs">Label</Label>
								<Input
									placeholder="e.g., Add rejection reason"
									bind:value={newRequirement.label}
								/>
							</div>
							<div class="space-y-1.5">
								<Label class="text-xs">Minimum length</Label>
								<Input
									type="number"
									placeholder="10"
									value={(newRequirement.config.min_length as string) || ''}
									oninput={(e) =>
										(newRequirement.config = {
											...newRequirement.config,
											min_length: parseInt(e.currentTarget.value) || 0
										})}
								/>
							</div>
						{:else if newRequirement.type === 'checklist'}
							<div class="space-y-1.5">
								<Label class="text-xs">Label</Label>
								<Input
									placeholder="e.g., Quality checklist"
									bind:value={newRequirement.label}
								/>
							</div>
							<div class="space-y-2">
								<Label class="text-xs">Checklist Items</Label>
								{#if checklistItems.length > 0}
									<div class="space-y-1.5 rounded-lg border bg-background p-2">
										{#each checklistItems as item, index}
											<div class="flex items-center gap-2">
												<CheckSquareIcon class="h-4 w-4 text-muted-foreground" />
												<span class="flex-1 text-sm">{item.label}</span>
												<button
													type="button"
													class="text-xs {item.required
														? 'text-red-500'
														: 'text-muted-foreground'}"
													onclick={() => toggleChecklistItemRequired(index)}
												>
													{item.required ? 'Required' : 'Optional'}
												</button>
												<Button
													variant="ghost"
													size="icon"
													class="h-6 w-6"
													onclick={() => removeChecklistItem(index)}
												>
													<TrashIcon class="h-3 w-3" />
												</Button>
											</div>
										{/each}
									</div>
								{/if}
								<div class="flex gap-2">
									<Input
										placeholder="Add checklist item..."
										bind:value={newChecklistItem}
										onkeydown={(e) => e.key === 'Enter' && addChecklistItem()}
									/>
									<Button variant="outline" size="icon" onclick={addChecklistItem}>
										<PlusIcon class="h-4 w-4" />
									</Button>
								</div>
							</div>
						{/if}

						<!-- Common fields -->
						<div class="space-y-1.5">
							<Label class="text-xs">Description (optional)</Label>
							<Textarea
								placeholder="Help text for the user..."
								bind:value={newRequirement.description}
								rows={2}
							/>
						</div>

						<div class="flex items-center justify-between">
							<div>
								<Label>Required</Label>
								<p class="text-xs text-muted-foreground">User must complete this</p>
							</div>
							<Switch
								checked={newRequirement.is_required}
								onCheckedChange={(checked) => (newRequirement.is_required = checked)}
							/>
						</div>
					</div>

					<div class="mt-4 flex justify-end gap-2">
						<Button variant="ghost" size="sm" onclick={resetForm}>
							Cancel
						</Button>
						<Button size="sm" onclick={handleAddRequirement} disabled={!canAdd()}>
							Add Requirement
						</Button>
					</div>
				</div>
			{:else if !readonly}
				<Button variant="outline" size="sm" onclick={() => (showAddForm = true)}>
					<PlusIcon class="mr-2 h-4 w-4" />
					Add Requirement
				</Button>
			{/if}
		{/if}
	</Card.Content>
</Card.Root>
