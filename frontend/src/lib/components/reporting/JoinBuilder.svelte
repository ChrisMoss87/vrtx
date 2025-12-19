<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trash2, Link2, ArrowRight, Database } from 'lucide-svelte';
	import type { ReportJoin, AvailableJoin, JoinType } from '$lib/api/reports';

	interface Props {
		joins?: ReportJoin[];
		availableJoins?: AvailableJoin[];
		onJoinsChange?: (joins: ReportJoin[]) => void;
	}

	let { joins = $bindable([]), availableJoins = [], onJoinsChange }: Props = $props();

	const joinTypes: { value: JoinType; label: string; description: string }[] = [
		{
			value: 'left',
			label: 'Left Join',
			description: 'Include all records from primary, matching from joined'
		},
		{
			value: 'inner',
			label: 'Inner Join',
			description: 'Only include records that match in both'
		},
		{
			value: 'right',
			label: 'Right Join',
			description: 'Include all records from joined, matching from primary'
		}
	];

	// Get available joins that haven't been added yet
	let unusedJoins = $derived.by(() => {
		const usedAliases = new Set(joins.map((j) => j.alias));
		return availableJoins.filter((j) => !usedAliases.has(j.suggested_alias));
	});

	function addJoin(available: AvailableJoin) {
		const newJoin: ReportJoin = {
			source_field: available.source_field,
			target_module_id: available.target_module_id,
			alias: available.suggested_alias,
			join_type: 'left'
		};
		joins = [...joins, newJoin];
		onJoinsChange?.(joins);
	}

	function removeJoin(index: number) {
		joins = joins.filter((_, i) => i !== index);
		onJoinsChange?.(joins);
	}

	function updateJoin(index: number, updates: Partial<ReportJoin>) {
		joins = joins.map((join, i) => (i === index ? { ...join, ...updates } : join));
		onJoinsChange?.(joins);
	}

	function getJoinInfo(join: ReportJoin): AvailableJoin | undefined {
		return availableJoins.find(
			(a) => a.source_field === join.source_field && a.target_module_id === join.target_module_id
		);
	}
</script>

<div class="space-y-4">
	<div class="flex items-center justify-between">
		<Label class="text-base font-medium">Related Modules</Label>
		{#if unusedJoins.length > 0}
			<Select.Root
				type="single"
				onValueChange={(v) => {
					if (v) {
						const available = unusedJoins.find((j) => j.suggested_alias === v);
						if (available) addJoin(available);
					}
				}}
			>
				<Select.Trigger class="w-[200px]">
					<Plus class="mr-2 h-4 w-4" />
					<span>Add Module</span>
				</Select.Trigger>
				<Select.Content>
					{#each unusedJoins as available}
						<Select.Item value={available.suggested_alias}>
							<div class="flex items-center gap-2">
								<Database class="h-4 w-4" />
								<span>{available.target_module_name}</span>
							</div>
						</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		{/if}
	</div>

	{#if joins.length === 0}
		<div class="rounded-lg border border-dashed p-6 text-center">
			<Link2 class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
			<p class="text-sm text-muted-foreground">No related modules added</p>
			<p class="text-xs text-muted-foreground">
				Add related modules to include their data in your report
			</p>
			{#if availableJoins.length === 0}
				<p class="text-xs text-muted-foreground mt-2">
					This module has no lookup fields to other modules
				</p>
			{/if}
		</div>
	{:else}
		<div class="space-y-3">
			{#each joins as join, index (join.alias)}
				{@const info = getJoinInfo(join)}
				<div class="flex items-center gap-3 rounded-lg border p-3">
					<!-- Join Visualization -->
					<div class="flex items-center gap-2 flex-1">
						<Badge variant="outline" class="flex items-center gap-1">
							<Database class="h-3 w-3" />
							Primary
						</Badge>
						<div class="flex items-center gap-1 text-muted-foreground">
							<span class="text-xs">{info?.source_field_label || join.source_field}</span>
							<ArrowRight class="h-4 w-4" />
						</div>
						<Badge variant="secondary" class="flex items-center gap-1">
							<Database class="h-3 w-3" />
							{info?.target_module_name || join.alias}
						</Badge>
					</div>

					<!-- Join Type -->
					<Select.Root
						type="single"
						value={join.join_type || 'left'}
						onValueChange={(v) => v && updateJoin(index, { join_type: v as JoinType })}
					>
						<Select.Trigger class="w-[130px]">
							<span>{joinTypes.find((t) => t.value === join.join_type)?.label || 'Left Join'}</span>
						</Select.Trigger>
						<Select.Content>
							{#each joinTypes as type}
								<Select.Item value={type.value}>
									<div>
										<div>{type.label}</div>
										<div class="text-xs text-muted-foreground">{type.description}</div>
									</div>
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>

					<!-- Alias -->
					<div class="w-32">
						<Input
							value={join.alias}
							onchange={(e) => updateJoin(index, { alias: e.currentTarget.value })}
							placeholder="Alias"
							class="h-8 text-sm"
						/>
					</div>

					<!-- Remove -->
					<Button
						variant="ghost"
						size="icon"
						class="h-8 w-8 text-destructive"
						onclick={() => removeJoin(index)}
					>
						<Trash2 class="h-4 w-4" />
					</Button>
				</div>
			{/each}
		</div>
	{/if}

	<!-- Help Text -->
	{#if joins.length > 0}
		<div class="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
			<p class="font-medium mb-1">Using Joined Data:</p>
			<ul class="list-disc list-inside space-y-1">
				<li>
					Reference joined fields as <code class="bg-muted px-1">{'{alias.field_name}'}</code>
				</li>
				<li>Use in filters, grouping, and calculated fields</li>
				<li>
					<span class="font-medium">Left Join:</span> Shows all primary records, even without matches
				</li>
				<li>
					<span class="font-medium">Inner Join:</span> Only shows records with matches in both tables
				</li>
			</ul>
		</div>
	{/if}
</div>
