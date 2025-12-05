<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import * as Popover from '$lib/components/ui/popover';
	import { Calendar } from '$lib/components/ui/calendar';
	import { X, CalendarIcon } from 'lucide-svelte';
	import { cn } from '$lib/utils';
	import {
		DateFormatter,
		type DateValue,
		parseDate,
		today,
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

	const operators = [
		{ value: 'equals', label: 'Equals' },
		{ value: 'before', label: 'Before' },
		{ value: 'after', label: 'After' },
		{ value: 'between', label: 'Between' },
		{ value: 'today', label: 'Today' },
		{ value: 'yesterday', label: 'Yesterday' },
		{ value: 'last_7_days', label: 'Last 7 days' },
		{ value: 'last_30_days', label: 'Last 30 days' },
		{ value: 'this_month', label: 'This month' },
		{ value: 'last_month', label: 'Last month' },
		{ value: 'is_empty', label: 'Is empty' },
		{ value: 'is_not_empty', label: 'Is not empty' }
	];

	let selectedOperator = $state(value?.operator || 'equals');
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

	function handleApply() {
		if (
			[
				'is_empty',
				'is_not_empty',
				'today',
				'yesterday',
				'last_7_days',
				'last_30_days',
				'this_month',
				'last_month'
			].includes(selectedOperator)
		) {
			onApply({ operator: selectedOperator, value: '' });
		} else if (selectedOperator === 'between' && date1 && date2) {
			onApply({ operator: selectedOperator, value: [date1.toString(), date2.toString()] });
		} else if (date1) {
			onApply({ operator: selectedOperator, value: date1.toString() });
		}
		onClose?.();
	}

	function handleClear() {
		selectedOperator = 'equals';
		date1 = undefined;
		date2 = undefined;
		onApply(null);
		onClose?.();
	}

	const needsDatePicker = $derived(
		![
			'is_empty',
			'is_not_empty',
			'today',
			'yesterday',
			'last_7_days',
			'last_30_days',
			'this_month',
			'last_month'
		].includes(selectedOperator)
	);
	const isBetween = $derived(selectedOperator === 'between');

	function formatDate(date: DateValue | undefined): string {
		if (!date) return '';
		return df.format(date.toDate(getLocalTimeZone()));
	}
</script>

<div class="w-[280px] space-y-4 p-4">
	<div class="space-y-2">
		<Label>Operator</Label>
		<Select.Root
			type="single"
			value={selectedOperator}
			onValueChange={(value) => {
				if (value) selectedOperator = value;
			}}
		>
			<Select.Trigger class="w-full">
				<span
					>{operators.find((o) => o.value === selectedOperator)?.label || 'Select operator'}</span
				>
			</Select.Trigger>
			<Select.Content>
				{#each operators as operator (operator.value)}
					<Select.Item value={operator.value}>
						{operator.label}
					</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	</div>

	{#if needsDatePicker}
		<div class="space-y-2">
			<Label>{isBetween ? 'From' : 'Date'}</Label>
			<Popover.Root bind:open={date1Open}>
				<Popover.Trigger>
					{#snippet child({ props })}
						<Button
							{...props}
							variant="outline"
							class={cn(
								'w-full justify-start text-left font-normal',
								!date1 && 'text-muted-foreground'
							)}
						>
							<CalendarIcon class="mr-2 h-4 w-4" />
							{date1 ? formatDate(date1) : 'Pick a date'}
						</Button>
					{/snippet}
				</Popover.Trigger>
				<Popover.Content class="w-auto p-0" align="start">
					<Calendar
						type="single"
						value={date1}
						onValueChange={(d) => {
							date1 = d;
							date1Open = false;
						}}
					/>
				</Popover.Content>
			</Popover.Root>
		</div>

		{#if isBetween}
			<div class="space-y-2">
				<Label>To</Label>
				<Popover.Root bind:open={date2Open}>
					<Popover.Trigger>
						{#snippet child({ props })}
							<Button
								{...props}
								variant="outline"
								class={cn(
									'w-full justify-start text-left font-normal',
									!date2 && 'text-muted-foreground'
								)}
							>
								<CalendarIcon class="mr-2 h-4 w-4" />
								{date2 ? formatDate(date2) : 'Pick a date'}
							</Button>
						{/snippet}
					</Popover.Trigger>
					<Popover.Content class="w-auto p-0" align="start">
						<Calendar
							type="single"
							value={date2}
							onValueChange={(d) => {
								date2 = d;
								date2Open = false;
							}}
						/>
					</Popover.Content>
				</Popover.Root>
			</div>
		{/if}
	{/if}

	<div class="flex items-center justify-between gap-2">
		<Button variant="ghost" size="sm" onclick={handleClear}>
			<X class="mr-2 h-4 w-4" />
			Clear
		</Button>
		<Button
			size="sm"
			onclick={handleApply}
			disabled={needsDatePicker && (!date1 || (isBetween && !date2))}
		>
			Apply Filter
		</Button>
	</div>
</div>
