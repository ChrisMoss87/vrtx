<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trash2, Info, GitBranch } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface BranchCondition {
		field: string;
		operator: string;
		value: string;
	}

	interface Branch {
		name: string;
		conditions: BranchCondition[];
		logic: 'and' | 'or';
	}

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], onConfigChange }: Props = $props();

	// Local state
	let branches = $state<Branch[]>((config.branches as Branch[]) || [
		{ name: 'Branch 1', conditions: [], logic: 'and' }
	]);
	let defaultBranchName = $state<string>((config.default_branch as string) || 'Default');

	const OPERATORS = [
		{ value: 'equals', label: 'Equals' },
		{ value: 'not_equals', label: 'Not Equals' },
		{ value: 'contains', label: 'Contains' },
		{ value: 'greater_than', label: 'Greater Than' },
		{ value: 'less_than', label: 'Less Than' },
		{ value: 'is_empty', label: 'Is Empty' },
		{ value: 'is_not_empty', label: 'Is Not Empty' }
	];

	function emitChange() {
		onConfigChange?.({
			branches,
			default_branch: defaultBranchName
		});
	}

	function addBranch() {
		branches = [...branches, { name: `Branch ${branches.length + 1}`, conditions: [], logic: 'and' }];
		emitChange();
	}

	function removeBranch(index: number) {
		branches = branches.filter((_, i) => i !== index);
		emitChange();
	}

	function updateBranchName(index: number, name: string) {
		branches = branches.map((b, i) => (i === index ? { ...b, name } : b));
		emitChange();
	}

	function updateBranchLogic(index: number, logic: 'and' | 'or') {
		branches = branches.map((b, i) => (i === index ? { ...b, logic } : b));
		emitChange();
	}

	function addCondition(branchIndex: number) {
		branches = branches.map((b, i) =>
			i === branchIndex
				? {
						...b,
						conditions: [...b.conditions, { field: '', operator: 'equals', value: '' }]
					}
				: b
		);
		emitChange();
	}

	function removeCondition(branchIndex: number, conditionIndex: number) {
		branches = branches.map((b, i) =>
			i === branchIndex
				? { ...b, conditions: b.conditions.filter((_, ci) => ci !== conditionIndex) }
				: b
		);
		emitChange();
	}

	function updateCondition(
		branchIndex: number,
		conditionIndex: number,
		updates: Partial<BranchCondition>
	) {
		branches = branches.map((b, i) =>
			i === branchIndex
				? {
						...b,
						conditions: b.conditions.map((c, ci) =>
							ci === conditionIndex ? { ...c, ...updates } : c
						)
					}
				: b
		);
		emitChange();
	}
</script>

<div class="space-y-4">
	<div class="flex items-center gap-2">
		<GitBranch class="h-5 w-5 text-muted-foreground" />
		<h4 class="font-medium">Condition Branch Configuration</h4>
	</div>

	<p class="text-sm text-muted-foreground">
		Create multiple branches with different conditions. The first branch whose conditions match will be executed.
	</p>

	<!-- Branches -->
	<div class="space-y-4">
		{#each branches as branch, branchIndex}
			<div class="rounded-lg border bg-card p-4">
				<div class="mb-3 flex items-center justify-between">
					<div class="flex items-center gap-2">
						<Badge variant="outline">{branchIndex + 1}</Badge>
						<Input
							value={branch.name}
							oninput={(e) => updateBranchName(branchIndex, e.currentTarget.value)}
							class="h-8 w-40"
							placeholder="Branch name"
						/>
					</div>
					{#if branches.length > 1}
						<Button
							type="button"
							variant="ghost"
							size="icon"
							class="h-8 w-8 text-destructive"
							onclick={() => removeBranch(branchIndex)}
						>
							<Trash2 class="h-4 w-4" />
						</Button>
					{/if}
				</div>

				<!-- Branch Logic -->
				{#if branch.conditions.length > 1}
					<div class="mb-3 flex gap-2">
						<Button
							type="button"
							variant={branch.logic === 'and' ? 'secondary' : 'ghost'}
							size="sm"
							class="h-7"
							onclick={() => updateBranchLogic(branchIndex, 'and')}
						>
							AND (All)
						</Button>
						<Button
							type="button"
							variant={branch.logic === 'or' ? 'secondary' : 'ghost'}
							size="sm"
							class="h-7"
							onclick={() => updateBranchLogic(branchIndex, 'or')}
						>
							OR (Any)
						</Button>
					</div>
				{/if}

				<!-- Conditions -->
				<div class="space-y-2">
					{#each branch.conditions as condition, condIndex}
						<div class="flex items-center gap-2">
							<Select.Root
								type="single"
								value={condition.field}
								onValueChange={(v) =>
									v && updateCondition(branchIndex, condIndex, { field: v })}
							>
								<Select.Trigger class="h-8 flex-1">
									{moduleFields.find((f) => f.api_name === condition.field)?.label ||
										'Select field'}
								</Select.Trigger>
								<Select.Content>
									{#each moduleFields as field}
										<Select.Item value={field.api_name}>{field.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>

							<Select.Root
								type="single"
								value={condition.operator}
								onValueChange={(v) =>
									v && updateCondition(branchIndex, condIndex, { operator: v })}
							>
								<Select.Trigger class="h-8 w-36">
									{OPERATORS.find((o) => o.value === condition.operator)?.label || 'Operator'}
								</Select.Trigger>
								<Select.Content>
									{#each OPERATORS as operator}
										<Select.Item value={operator.value}>{operator.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>

							{#if !['is_empty', 'is_not_empty'].includes(condition.operator)}
								<Input
									value={condition.value}
									oninput={(e) =>
										updateCondition(branchIndex, condIndex, { value: e.currentTarget.value })}
									placeholder="Value"
									class="h-8 flex-1"
								/>
							{/if}

							<Button
								type="button"
								variant="ghost"
								size="icon"
								class="h-8 w-8"
								onclick={() => removeCondition(branchIndex, condIndex)}
							>
								<Trash2 class="h-3.5 w-3.5" />
							</Button>
						</div>

						{#if condIndex < branch.conditions.length - 1}
							<div class="flex justify-center">
								<Badge variant="secondary" class="text-xs uppercase">{branch.logic}</Badge>
							</div>
						{/if}
					{/each}

					<Button
						type="button"
						variant="ghost"
						size="sm"
						class="w-full"
						onclick={() => addCondition(branchIndex)}
					>
						<Plus class="mr-2 h-3.5 w-3.5" />
						Add condition
					</Button>
				</div>
			</div>
		{/each}
	</div>

	<Button type="button" variant="outline" size="sm" onclick={addBranch}>
		<Plus class="mr-2 h-4 w-4" />
		Add Branch
	</Button>

	<!-- Default Branch -->
	<div class="space-y-2">
		<Label>Default Branch Name</Label>
		<Input
			value={defaultBranchName}
			oninput={(e) => {
				defaultBranchName = e.currentTarget.value;
				emitChange();
			}}
			placeholder="Default"
		/>
		<p class="text-xs text-muted-foreground">
			Executed when no other branch conditions match
		</p>
	</div>

	<!-- Info -->
	<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
		<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
		<p class="text-xs text-muted-foreground">
			Branches are evaluated in order. The first matching branch will be taken.
			Use the branch names to reference them in subsequent steps.
		</p>
	</div>
</div>
