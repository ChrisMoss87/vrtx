<script lang="ts">
	import type { DuplicateRule, ConditionGroup, MatchType, DuplicateAction } from '$lib/api/duplicates';
	import { createDuplicateRule, updateDuplicateRule } from '$lib/api/duplicates';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Select from '$lib/components/ui/select';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Loader2, Plus, Trash2, Layers } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';

	interface Props {
		open?: boolean;
		moduleId: number;
		fields: { name: string; label: string }[];
		rule?: DuplicateRule | null;
		onClose?: () => void;
		onSaved?: (rule: DuplicateRule) => void;
	}

	let {
		open = $bindable(false),
		moduleId,
		fields,
		rule = null,
		onClose,
		onSaved
	}: Props = $props();

	let saving = $state(false);
	let name = $state('');
	let description = $state('');
	let isActive = $state(true);
	let action = $state<DuplicateAction>('warn');
	let priority = $state(0);
	let conditions = $state<ConditionGroup>({
		logic: 'or',
		rules: [{ field: '', match_type: 'exact' }]
	});

	const matchTypes: { value: MatchType; label: string; description: string }[] = [
		{ value: 'exact', label: 'Exact Match', description: 'Values must be identical' },
		{ value: 'fuzzy', label: 'Fuzzy Match', description: 'Similar values (Levenshtein)' },
		{ value: 'phonetic', label: 'Phonetic Match', description: 'Sounds alike (for names)' },
		{ value: 'email_domain', label: 'Email Domain', description: 'Same email domain' }
	];

	function initializeForm() {
		if (rule) {
			name = rule.name;
			description = rule.description || '';
			isActive = rule.is_active;
			action = rule.action;
			priority = rule.priority;
			conditions = JSON.parse(JSON.stringify(rule.conditions));
		} else {
			name = '';
			description = '';
			isActive = true;
			action = 'warn';
			priority = 0;
			conditions = {
				logic: 'or',
				rules: [{ field: fields[0]?.name || '', match_type: 'exact' }]
			};
		}
	}

	function addCondition() {
		conditions.rules = [
			...conditions.rules,
			{ field: fields[0]?.name || '', match_type: 'exact' }
		];
	}

	function removeCondition(index: number) {
		if (conditions.rules.length > 1) {
			conditions.rules = conditions.rules.filter((_, i) => i !== index);
		}
	}

	function updateConditionField(index: number, field: string) {
		const newRules = [...conditions.rules];
		if ('field' in newRules[index]) {
			(newRules[index] as { field: string; match_type: MatchType; threshold?: number }).field = field;
		}
		conditions.rules = newRules;
	}

	function updateConditionMatchType(index: number, matchType: MatchType) {
		const newRules = [...conditions.rules];
		if ('match_type' in newRules[index]) {
			(newRules[index] as { field: string; match_type: MatchType; threshold?: number }).match_type = matchType;
			if (matchType === 'fuzzy' || matchType === 'phonetic') {
				(newRules[index] as { field: string; match_type: MatchType; threshold?: number }).threshold = 0.8;
			} else {
				delete (newRules[index] as { field: string; match_type: MatchType; threshold?: number }).threshold;
			}
		}
		conditions.rules = newRules;
	}

	function updateConditionThreshold(index: number, threshold: number) {
		const newRules = [...conditions.rules];
		if ('threshold' in newRules[index]) {
			(newRules[index] as { field: string; match_type: MatchType; threshold?: number }).threshold = threshold;
		}
		conditions.rules = newRules;
	}

	async function handleSave() {
		if (!name.trim()) {
			toast.error('Please enter a rule name');
			return;
		}

		if (conditions.rules.length === 0) {
			toast.error('Please add at least one condition');
			return;
		}

		// Validate conditions
		for (const cond of conditions.rules) {
			if ('field' in cond && !cond.field) {
				toast.error('Please select a field for all conditions');
				return;
			}
		}

		saving = true;
		try {
			let savedRule: DuplicateRule;

			if (rule) {
				savedRule = await updateDuplicateRule(rule.id, {
					name,
					description: description || undefined,
					is_active: isActive,
					action,
					conditions,
					priority
				});
				toast.success('Rule updated successfully');
			} else {
				savedRule = await createDuplicateRule({
					module_id: moduleId,
					name,
					description: description || undefined,
					is_active: isActive,
					action,
					conditions,
					priority
				});
				toast.success('Rule created successfully');
			}

			onSaved?.(savedRule);
			open = false;
		} catch {
			toast.error('Failed to save rule');
		} finally {
			saving = false;
		}
	}

	function handleOpenChange(isOpen: boolean) {
		open = isOpen;
		if (!isOpen) {
			onClose?.();
		}
	}

	$effect(() => {
		if (open) {
			initializeForm();
		}
	});
</script>

<Dialog.Root {open} onOpenChange={handleOpenChange}>
	<Dialog.Content class="sm:max-w-xl max-h-[90vh] overflow-hidden flex flex-col">
		<Dialog.Header>
			<Dialog.Title class="flex items-center gap-2">
				<Layers class="h-5 w-5" />
				{rule ? 'Edit' : 'Create'} Duplicate Detection Rule
			</Dialog.Title>
			<Dialog.Description>
				Define how to detect duplicate records in this module.
			</Dialog.Description>
		</Dialog.Header>

		<div class="flex-1 overflow-y-auto space-y-6 py-4">
			<!-- Basic Info -->
			<div class="space-y-4">
				<div class="space-y-2">
					<Label for="name">Rule Name</Label>
					<Input id="name" placeholder="e.g., Email Match" bind:value={name} />
				</div>

				<div class="space-y-2">
					<Label for="description">Description (optional)</Label>
					<Textarea
						id="description"
						placeholder="Describe what this rule does..."
						rows={2}
						bind:value={description}
					/>
				</div>

				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label>Active</Label>
						<p class="text-sm text-muted-foreground">Enable this rule for duplicate detection</p>
					</div>
					<Switch bind:checked={isActive} />
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label>Action</Label>
						<Select.Root
							type="single"
							value={action}
							onValueChange={(v) => {
								if (v) action = v as DuplicateAction;
							}}
						>
							<Select.Trigger>
								<span class="capitalize">{action}</span>
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="warn">Warn (allow creation)</Select.Item>
								<Select.Item value="block">Block (prevent creation)</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>

					<div class="space-y-2">
						<Label for="priority">Priority</Label>
						<Input
							id="priority"
							type="number"
							min={0}
							max={1000}
							bind:value={priority}
						/>
					</div>
				</div>
			</div>

			<!-- Conditions -->
			<div class="space-y-4">
				<div class="flex items-center justify-between">
					<Label>Matching Conditions</Label>
					<Select.Root
						type="single"
						value={conditions.logic}
						onValueChange={(v) => {
							if (v) conditions.logic = v as 'and' | 'or';
						}}
					>
						<Select.Trigger class="w-[100px]">
							<span class="uppercase text-xs font-medium">{conditions.logic}</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="or">OR (any match)</Select.Item>
							<Select.Item value="and">AND (all match)</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-3">
					{#each conditions.rules as cond, index}
						{#if 'field' in cond}
							<div class="flex items-start gap-2 p-3 rounded-lg border bg-muted/50">
								<div class="flex-1 grid grid-cols-3 gap-2">
									<Select.Root
										type="single"
										value={cond.field}
										onValueChange={(v) => {
											if (v) updateConditionField(index, v);
										}}
									>
										<Select.Trigger>
											<span>{fields.find((f) => f.name === cond.field)?.label || 'Select field'}</span>
										</Select.Trigger>
										<Select.Content>
											{#each fields as field}
												<Select.Item value={field.name}>{field.label}</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>

									<Select.Root
										type="single"
										value={cond.match_type}
										onValueChange={(v) => {
											if (v) updateConditionMatchType(index, v as MatchType);
										}}
									>
										<Select.Trigger>
											<span>{matchTypes.find((m) => m.value === cond.match_type)?.label}</span>
										</Select.Trigger>
										<Select.Content>
											{#each matchTypes as mt}
												<Select.Item value={mt.value}>
													<div>
														<div>{mt.label}</div>
														<div class="text-xs text-muted-foreground">{mt.description}</div>
													</div>
												</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>

									{#if cond.match_type === 'fuzzy' || cond.match_type === 'phonetic'}
										<div class="flex items-center gap-2">
											<Input
												type="number"
												min={0}
												max={1}
												step={0.05}
												value={cond.threshold ?? 0.8}
												onchange={(e) => {
													const target = e.target as HTMLInputElement;
													updateConditionThreshold(index, parseFloat(target.value));
												}}
											/>
											<span class="text-xs text-muted-foreground whitespace-nowrap">threshold</span>
										</div>
									{/if}
								</div>

								<Button
									variant="ghost"
									size="icon"
									class="h-8 w-8 flex-shrink-0"
									disabled={conditions.rules.length <= 1}
									onclick={() => removeCondition(index)}
								>
									<Trash2 class="h-4 w-4" />
								</Button>
							</div>
						{/if}
					{/each}

					<Button variant="outline" size="sm" onclick={addCondition}>
						<Plus class="h-4 w-4 mr-1" />
						Add Condition
					</Button>
				</div>
			</div>
		</div>

		<Dialog.Footer class="border-t pt-4">
			<Button variant="outline" onclick={() => handleOpenChange(false)}>
				Cancel
			</Button>
			<Button onclick={handleSave} disabled={saving}>
				{#if saving}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				{rule ? 'Update' : 'Create'} Rule
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
