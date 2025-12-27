<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import * as Popover from '$lib/components/ui/popover';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Calendar } from '$lib/components/ui/calendar';
	import { X, CalendarIcon, CalendarRange, Clock } from 'lucide-svelte';
	import { cn } from '$lib/utils';
	import {
		DateFormatter,
		type DateValue,
		parseDate,
		getLocalTimeZone
	} from '@internationalized/date';

	interface Props {
		field?: string;
		value?: {
			operator: string;
			value: string | string[];
		};
		initialValue?: any;
		onApply: (filter: { operator: string; value: string | string[] } | null) => void;
		onClose?: () => void;
	}

	let { field, value, initialValue, onApply, onClose }: Props = $props();

	const df = new DateFormatter('en-US', { dateStyle: 'medium' });

	// Relative date presets (what all major CRMs use)
	const relativePresets = [
		{ value: 'today', label: 'Today' },
		{ value: 'yesterday', label: 'Yesterday' },
		{ value: 'last_7_days', label: 'Last 7 days' },
		{ value: 'last_14_days', label: 'Last 14 days' },
		{ value: 'last_30_days', label: 'Last 30 days' },
		{ value: 'last_90_days', label: 'Last 90 days' },
		{ value: 'this_week', label: 'This week' },
		{ value: 'last_week', label: 'Last week' },
		{ value: 'this_month', label: 'This month' },
		{ value: 'last_month', label: 'Last month' },
		{ value: 'this_quarter', label: 'This quarter' },
		{ value: 'last_quarter', label: 'Last quarter' },
		{ value: 'this_year', label: 'This year' },
		{ value: 'last_year', label: 'Last year' }
	];

	// Custom date operators for specific date selection
	const customOperators = [
		{ value: 'equals', label: 'Is exactly' },
		{ value: 'not_equals', label: 'Is not' },
		{ value: 'before', label: 'Before' },
		{ value: 'on_or_before', label: 'On or before' },
		{ value: 'after', label: 'After' },
		{ value: 'on_or_after', label: 'On or after' },
		{ value: 'between', label: 'Between' }
	];

	// Check if current operator is a preset
	const isPresetOperator = (op: string) => relativePresets.some(p => p.value === op) || ['is_empty', 'is_not_empty'].includes(op);

	let selectedOperator = $state(value?.operator || '');
	let activeTab = $state(isPresetOperator(value?.operator || '') ? 'presets' : (value?.operator ? 'custom' : 'presets'));
	let customOperator = $state(isPresetOperator(value?.operator || '') ? 'equals' : (value?.operator || 'equals'));

	let date1 = $state<DateValue | undefined>(
		typeof value?.value === 'string' && value.value
			? parseDate(value.value)
			: Array.isArray(value?.value) && value.value[0]
				? parseDate(value.value[0])
				: undefined
	);
	let date2 = $state<DateValue | undefined>(
		Array.isArray(value?.value) && value.value[1] ? parseDate(value.value[1]) : undefined
	);

	let date1Open = $state(false);
	let date2Open = $state(false);

	function handlePresetSelect(preset: string) {
		selectedOperator = preset;
		onApply({ operator: preset, value: '' });
		onClose?.();
	}

	function handleApply() {
		if (['is_empty', 'is_not_empty'].includes(selectedOperator)) {
			onApply({ operator: selectedOperator, value: '' });
		} else if (customOperator === 'between' && date1 && date2) {
			onApply({ operator: 'between', value: [date1.toString(), date2.toString()] });
		} else if (date1) {
			onApply({ operator: customOperator, value: date1.toString() });
		}
		onClose?.();
	}

	function handleClear() {
		selectedOperator = '';
		customOperator = 'equals';
		date1 = undefined;
		date2 = undefined;
		onApply(null);
		onClose?.();
	}

	const isBetween = $derived(customOperator === 'between');

	function formatDate(date: DateValue | undefined): string {
		if (!date) return '';
		return df.format(date.toDate(getLocalTimeZone()));
	}

	// Check if apply button should be enabled
	const canApply = $derived(() => {
		if (isBetween) return date1 && date2;
		return !!date1;
	});
</script>

<div class="w-[320px]">
	<Tabs.Root value={activeTab} onValueChange={(v) => { if (v) activeTab = v; }}>
		<Tabs.List class="w-full grid grid-cols-2">
			<Tabs.Trigger value="presets">
				<Clock class="h-3.5 w-3.5 mr-1.5" />
				Relative
			</Tabs.Trigger>
			<Tabs.Trigger value="custom">
				<CalendarIcon class="h-3.5 w-3.5 mr-1.5" />
				Custom
			</Tabs.Trigger>
		</Tabs.List>

		<!-- Relative Presets Tab -->
		<Tabs.Content value="presets" class="p-3 space-y-3">
			<div class="grid grid-cols-2 gap-1.5">
				{#each relativePresets as preset (preset.value)}
					<Button
						variant={selectedOperator === preset.value ? 'default' : 'outline'}
						size="sm"
						class="h-8 justify-start text-xs"
						onclick={() => handlePresetSelect(preset.value)}
					>
						{preset.label}
					</Button>
				{/each}
			</div>

			<div class="border-t pt-3 grid grid-cols-2 gap-1.5">
				<Button
					variant={selectedOperator === 'is_empty' ? 'default' : 'outline'}
					size="sm"
					class="h-8 justify-start text-xs"
					onclick={() => handlePresetSelect('is_empty')}
				>
					Is empty
				</Button>
				<Button
					variant={selectedOperator === 'is_not_empty' ? 'default' : 'outline'}
					size="sm"
					class="h-8 justify-start text-xs"
					onclick={() => handlePresetSelect('is_not_empty')}
				>
					Has value
				</Button>
			</div>

			<div class="border-t pt-3">
				<Button variant="ghost" size="sm" class="w-full" onclick={handleClear}>
					<X class="mr-1.5 h-3.5 w-3.5" />
					Clear Filter
				</Button>
			</div>
		</Tabs.Content>

		<!-- Custom Date Tab -->
		<Tabs.Content value="custom" class="p-3 space-y-3">
			<!-- Operator Selection -->
			<div class="space-y-1.5">
				<Label class="text-xs text-muted-foreground">Condition</Label>
				<Select.Root
					type="single"
					value={customOperator}
					onValueChange={(v) => { if (v) customOperator = v; }}
				>
					<Select.Trigger class="w-full h-8">
						{customOperators.find(o => o.value === customOperator)?.label || 'Select'}
					</Select.Trigger>
					<Select.Content>
						{#each customOperators as op (op.value)}
							<Select.Item value={op.value}>{op.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<!-- Date Picker(s) -->
			{#if isBetween}
				<div class="flex items-center gap-2">
					<div class="flex-1 space-y-1.5">
						<Label class="text-xs text-muted-foreground">From</Label>
						<Popover.Root bind:open={date1Open}>
							<Popover.Trigger>
								{#snippet child({ props })}
									<Button
										{...props}
										variant="outline"
										size="sm"
										class={cn('w-full justify-start text-left font-normal h-8', !date1 && 'text-muted-foreground')}
									>
										<CalendarIcon class="mr-1.5 h-3.5 w-3.5" />
										{date1 ? formatDate(date1) : 'Start'}
									</Button>
								{/snippet}
							</Popover.Trigger>
							<Popover.Content class="w-auto p-0" align="start">
								<Calendar type="single" value={date1} onValueChange={(d) => { date1 = d; date1Open = false; }} />
							</Popover.Content>
						</Popover.Root>
					</div>
					<CalendarRange class="h-4 w-4 text-muted-foreground mt-5 flex-shrink-0" />
					<div class="flex-1 space-y-1.5">
						<Label class="text-xs text-muted-foreground">To</Label>
						<Popover.Root bind:open={date2Open}>
							<Popover.Trigger>
								{#snippet child({ props })}
									<Button
										{...props}
										variant="outline"
										size="sm"
										class={cn('w-full justify-start text-left font-normal h-8', !date2 && 'text-muted-foreground')}
									>
										<CalendarIcon class="mr-1.5 h-3.5 w-3.5" />
										{date2 ? formatDate(date2) : 'End'}
									</Button>
								{/snippet}
							</Popover.Trigger>
							<Popover.Content class="w-auto p-0" align="end">
								<Calendar type="single" value={date2} onValueChange={(d) => { date2 = d; date2Open = false; }} />
							</Popover.Content>
						</Popover.Root>
					</div>
				</div>
			{:else}
				<div class="space-y-1.5">
					<Label class="text-xs text-muted-foreground">Date</Label>
					<Popover.Root bind:open={date1Open}>
						<Popover.Trigger>
							{#snippet child({ props })}
								<Button
									{...props}
									variant="outline"
									class={cn('w-full justify-start text-left font-normal', !date1 && 'text-muted-foreground')}
								>
									<CalendarIcon class="mr-2 h-4 w-4" />
									{date1 ? formatDate(date1) : 'Select a date'}
								</Button>
							{/snippet}
						</Popover.Trigger>
						<Popover.Content class="w-auto p-0" align="start">
							<Calendar type="single" value={date1} onValueChange={(d) => { date1 = d; date1Open = false; }} />
						</Popover.Content>
					</Popover.Root>
				</div>
			{/if}

			<!-- Actions -->
			<div class="flex items-center justify-between gap-2 pt-2 border-t">
				<Button variant="ghost" size="sm" onclick={handleClear}>
					<X class="mr-1.5 h-3.5 w-3.5" />
					Clear
				</Button>
				<Button size="sm" onclick={handleApply} disabled={!canApply()}>
					Apply
				</Button>
			</div>
		</Tabs.Content>
	</Tabs.Root>
</div>
