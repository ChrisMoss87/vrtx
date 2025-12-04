<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Clock, Star, TrendingUp, Calendar, User } from 'lucide-svelte';
	import type { TableContext, FilterConfig } from './types';
	import { CalendarDate, getLocalTimeZone, today } from '@internationalized/date';

	interface FilterPreset {
		id: string;
		label: string;
		icon?: any;
		filters: FilterConfig[];
		description?: string;
	}

	interface Props {
		presets?: FilterPreset[];
		moduleType?: string; // 'contacts', 'deals', 'tasks', etc.
	}

	let { presets = [], moduleType }: Props = $props();

	const table = getContext<TableContext>('table');

	// Default presets based on module type
	const defaultPresets = $derived(getDefaultPresetsForModule(moduleType));

	// Combine custom and default presets
	const allPresets = $derived([...presets, ...defaultPresets]);

	function getDefaultPresetsForModule(type?: string): FilterPreset[] {
		const t = today(getLocalTimeZone());

		switch (type) {
			case 'tasks':
				return [
					{
						id: 'my-open-tasks',
						label: 'My Open Tasks',
						icon: User,
						filters: [
							{ field: 'assignee', operator: 'equals', value: 'current_user' },
							{ field: 'status', operator: 'not_equals', value: 'completed' }
						]
					},
					{
						id: 'due-soon',
						label: 'Due Soon',
						icon: Clock,
						filters: [
							{
								field: 'due_date',
								operator: 'between',
								value: {
									from: t.toString(),
									to: t.add({ days: 7 }).toString()
								}
							}
						]
					},
					{
						id: 'overdue',
						label: 'Overdue',
						icon: TrendingUp,
						filters: [
							{ field: 'due_date', operator: 'less_than', value: t.toString() },
							{ field: 'status', operator: 'not_equals', value: 'completed' }
						]
					}
				];

			case 'deals':
				return [
					{
						id: 'my-open-deals',
						label: 'My Open Deals',
						icon: User,
						filters: [
							{ field: 'owner', operator: 'equals', value: 'current_user' },
							{ field: 'stage', operator: 'not_in', value: ['won', 'lost'] }
						]
					},
					{
						id: 'hot-deals',
						label: 'Hot Deals',
						icon: TrendingUp,
						filters: [{ field: 'priority', operator: 'equals', value: 'high' }]
					},
					{
						id: 'closing-this-month',
						label: 'Closing This Month',
						icon: Calendar,
						filters: [
							{
								field: 'close_date',
								operator: 'between',
								value: {
									from: t.set({ day: 1 }).toString(),
									to: t
										.set({ day: t.add({ months: 1 }).set({ day: 1 }).subtract({ days: 1 }).day })
										.toString()
								}
							}
						]
					}
				];

			case 'contacts':
				return [
					{
						id: 'recent-contacts',
						label: 'Recent Contacts',
						icon: Clock,
						filters: [
							{
								field: 'created_at',
								operator: 'greater_than',
								value: t.subtract({ days: 30 }).toString()
							}
						]
					},
					{
						id: 'my-contacts',
						label: 'My Contacts',
						icon: User,
						filters: [{ field: 'owner', operator: 'equals', value: 'current_user' }]
					},
					{
						id: 'vip-contacts',
						label: 'VIP Contacts',
						icon: Star,
						filters: [{ field: 'vip', operator: 'equals', value: true }]
					}
				];

			default:
				return [
					{
						id: 'recent',
						label: 'Recent',
						icon: Clock,
						filters: [
							{
								field: 'created_at',
								operator: 'greater_than',
								value: t.subtract({ days: 7 }).toString()
							}
						]
					},
					{
						id: 'my-records',
						label: 'My Records',
						icon: User,
						filters: [{ field: 'owner', operator: 'equals', value: 'current_user' }]
					}
				];
		}
	}

	function applyPreset(preset: FilterPreset) {
		// Clear existing filters
		table.clearFilters();

		// Apply all filters from the preset
		preset.filters.forEach((filter) => {
			table.updateFilter(filter);
		});
	}

	// Check if a preset is currently active
	function isPresetActive(preset: FilterPreset): boolean {
		if (table.state.filters.length !== preset.filters.length) return false;

		return preset.filters.every((presetFilter) => {
			return table.state.filters.some(
				(activeFilter) =>
					activeFilter.field === presetFilter.field &&
					activeFilter.operator === presetFilter.operator &&
					JSON.stringify(activeFilter.value) === JSON.stringify(presetFilter.value)
			);
		});
	}
</script>

{#if allPresets.length > 0}
	<div class="flex flex-wrap items-center gap-2">
		<span class="text-xs font-medium text-muted-foreground">Quick:</span>
		{#each allPresets as preset (preset.id)}
			{@const isActive = isPresetActive(preset)}
			<Button
				variant={isActive ? 'default' : 'outline'}
				size="sm"
				onclick={() => applyPreset(preset)}
				class="h-7 gap-1.5"
			>
				{#if preset.icon}
					<svelte:component this={preset.icon} class="h-3 w-3" />
				{/if}
				{preset.label}
				{#if isActive}
					<Badge variant="secondary" class="ml-1 h-4 px-1 text-xs">Active</Badge>
				{/if}
			</Button>
		{/each}
	</div>
{/if}
