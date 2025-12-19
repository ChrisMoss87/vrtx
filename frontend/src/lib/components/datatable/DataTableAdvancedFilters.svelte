<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Dialog from '$lib/components/ui/dialog';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Save, Trash2, Copy } from 'lucide-svelte';
	import type { TableContext, FilterConfig, ColumnDef, FilterGroupData } from './types';
	import FilterGroup from './FilterGroup.svelte';

	interface Props {
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
		onSaveAsTemplate?: (name: string, group: FilterGroupData) => void;
		moduleApiName?: string;
	}

	let { open = $bindable(false), onOpenChange, onSaveAsTemplate, moduleApiName }: Props = $props();

	const table = getContext<TableContext>('table');

	// Root filter group
	let rootGroup = $state<FilterGroupData>({
		id: 'root',
		logic: 'AND',
		conditions: [...table.state.filters],
		groups: []
	});

	// Sync with table state when dialog opens
	$effect(() => {
		if (open) {
			rootGroup = {
				id: 'root',
				logic: 'AND',
				conditions: [...table.state.filters],
				groups: []
			};
		}
	});

	// Get filterable columns
	const filterableColumns = $derived(
		table.columns.filter((col) => col.filterable !== false && col.type !== 'actions')
	);

	function generateId(): string {
		return `group-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
	}

	function addCondition(group: FilterGroupData) {
		const firstColumn = filterableColumns[0];
		if (!firstColumn) return;

		group.conditions = [
			...group.conditions,
			{
				field: firstColumn.id,
				operator: 'contains',
				value: ''
			}
		];
	}

	function removeCondition(group: FilterGroupData, index: number) {
		group.conditions = group.conditions.filter((_, i) => i !== index);
	}

	function updateCondition(group: FilterGroupData, index: number, condition: FilterConfig) {
		group.conditions = group.conditions.map((c, i) => (i === index ? condition : c));
	}

	function addGroup(parentGroup: FilterGroupData) {
		parentGroup.groups = [
			...parentGroup.groups,
			{
				id: generateId(),
				logic: 'AND',
				conditions: [],
				groups: []
			}
		];
	}

	function removeGroup(parentGroup: FilterGroupData, groupId: string) {
		parentGroup.groups = parentGroup.groups.filter((g) => g.id !== groupId);
	}

	function toggleLogic(group: FilterGroupData) {
		group.logic = group.logic === 'AND' ? 'OR' : 'AND';
	}

	function flattenFilters(group: FilterGroupData): FilterConfig[] {
		// For now, we'll flatten to simple AND logic
		// In a full implementation, you'd need to encode the group structure
		const conditions = [...group.conditions];
		group.groups.forEach((subGroup) => {
			conditions.push(...flattenFilters(subGroup));
		});
		return conditions.filter((c) => c.value !== '' && c.value !== null && c.value !== undefined);
	}

	function applyFilters() {
		// Clear existing filters
		table.clearFilters();

		// Flatten and apply all filters
		const filters = flattenFilters(rootGroup);
		filters.forEach((filter) => {
			table.updateFilter(filter);
		});

		// Close dialog
		open = false;
		onOpenChange?.(false);
	}

	function clearAllFilters() {
		rootGroup = {
			id: 'root',
			logic: 'AND',
			conditions: [],
			groups: []
		};
	}

	function handleSaveAsTemplate() {
		if (onSaveAsTemplate) {
			// Prompt for name (you can use a dialog for this)
			const name = prompt('Enter template name:');
			if (name) {
				onSaveAsTemplate(name, rootGroup);
			}
		}
	}

	// Count total conditions
	const totalConditions = $derived(() => {
		function count(group: FilterGroupData): number {
			let total = group.conditions.length;
			group.groups.forEach((g) => {
				total += count(g);
			});
			return total;
		}
		return count(rootGroup);
	});
</script>

<Dialog.Root bind:open {onOpenChange}>
	<Dialog.Content class="max-h-[90vh] max-w-4xl">
		<Dialog.Header>
			<Dialog.Title>Advanced Filter Builder</Dialog.Title>
			<Dialog.Description>
				Create complex filter combinations with AND/OR logic. Changes won't be applied until you
				click "Apply Filters".
			</Dialog.Description>
		</Dialog.Header>

		<ScrollArea class="max-h-[60vh] pr-4">
			<div class="space-y-4 py-4">
				<!-- Filter Group Info -->
				<div class="flex items-center justify-between rounded-lg border bg-muted/50 p-3">
					<div class="flex items-center gap-2">
						<span class="text-sm font-medium">Filter Logic:</span>
						<Button variant="outline" size="sm" onclick={() => toggleLogic(rootGroup)} class="h-7">
							<Badge variant={rootGroup.logic === 'AND' ? 'default' : 'secondary'}>
								{rootGroup.logic}
							</Badge>
						</Button>
						<span class="text-xs text-muted-foreground">
							{rootGroup.logic === 'AND' ? 'All conditions must match' : 'Any condition can match'}
						</span>
					</div>
					<div class="flex items-center gap-2 text-sm text-muted-foreground">
						{totalConditions()} condition{totalConditions() === 1 ? '' : 's'}
					</div>
				</div>

				<!-- Root Filter Group -->
				<FilterGroup
					group={rootGroup}
					columns={filterableColumns}
					level={0}
					{addCondition}
					{removeCondition}
					{updateCondition}
					{addGroup}
					{removeGroup}
					{toggleLogic}
				/>

				<!-- Actions -->
				<div class="flex items-center gap-2 pt-2">
					<Button variant="outline" size="sm" onclick={() => addCondition(rootGroup)}>
						<Plus class="mr-1 h-3 w-3" />
						Add Condition
					</Button>
					<Button variant="outline" size="sm" onclick={() => addGroup(rootGroup)}>
						<Plus class="mr-1 h-3 w-3" />
						Add Group
					</Button>
					{#if totalConditions() > 0}
						<Button variant="ghost" size="sm" onclick={clearAllFilters}>
							<Trash2 class="mr-1 h-3 w-3" />
							Clear All
						</Button>
					{/if}
				</div>
			</div>
		</ScrollArea>

		<Dialog.Footer class="flex-col gap-2 sm:flex-row">
			<div class="order-1 flex flex-1 gap-2 sm:order-2">
				{#if onSaveAsTemplate && totalConditions() > 0}
					<Button variant="outline" onclick={handleSaveAsTemplate} class="flex-shrink-0">
						<Save class="mr-1 h-4 w-4" />
						Save as Template
					</Button>
				{/if}
				<Button onclick={applyFilters} class="flex-1" disabled={totalConditions() === 0}>
					Apply {totalConditions() > 0 ? `${totalConditions()} ` : ''}Filter{totalConditions() === 1
						? ''
						: 's'}
				</Button>
			</div>
			<Button
				variant="ghost"
				onclick={() => {
					open = false;
					onOpenChange?.(false);
				}}
				class="order-2 sm:order-1"
			>
				Cancel
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
