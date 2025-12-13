<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Calendar } from '$lib/components/ui/calendar';
	import * as Popover from '$lib/components/ui/popover';
	import { X, Calendar as CalendarIcon } from 'lucide-svelte';
	import type { FilterConfig } from '../types';
	import { cn } from '$lib/utils';
	import {
		CalendarDate,
		getLocalTimeZone,
		today,
		parseDate,
		type DateValue
	} from '@internationalized/date';

	interface Props {
		field: string;
		initialValue?: FilterConfig;
		onApply: (filter: FilterConfig | null) => void;
		onClose: () => void;
	}

	let { field, initialValue, onApply, onClose }: Props = $props();

	// Parse initial dates if available - handle object with from/to properties
	function getFromValue(): DateValue | undefined {
		if (initialValue?.value && typeof initialValue.value === 'object' && !Array.isArray(initialValue.value) && 'from' in initialValue.value) {
			const val = initialValue.value as { from?: string | number; to?: string | number };
			return val.from ? parseDate(String(val.from)) : undefined;
		}
		return undefined;
	}
	function getToValue(): DateValue | undefined {
		if (initialValue?.value && typeof initialValue.value === 'object' && !Array.isArray(initialValue.value) && 'to' in initialValue.value) {
			const val = initialValue.value as { from?: string | number; to?: string | number };
			return val.to ? parseDate(String(val.to)) : undefined;
		}
		return undefined;
	}
	let fromDate = $state<DateValue | undefined>(getFromValue());
	let toDate = $state<DateValue | undefined>(getToValue());

	const presets = [
		{
			label: 'Today',
			getValue: () => {
				const t = today(getLocalTimeZone());
				return { from: t, to: t };
			}
		},
		{
			label: 'Yesterday',
			getValue: () => {
				const t = today(getLocalTimeZone()).subtract({ days: 1 });
				return { from: t, to: t };
			}
		},
		{
			label: 'Last 7 days',
			getValue: () => ({
				from: today(getLocalTimeZone()).subtract({ days: 6 }),
				to: today(getLocalTimeZone())
			})
		},
		{
			label: 'Last 30 days',
			getValue: () => ({
				from: today(getLocalTimeZone()).subtract({ days: 29 }),
				to: today(getLocalTimeZone())
			})
		},
		{
			label: 'This month',
			getValue: () => {
				const t = today(getLocalTimeZone());
				return {
					from: t.set({ day: 1 }),
					to: t
				};
			}
		},
		{
			label: 'Last month',
			getValue: () => {
				const t = today(getLocalTimeZone());
				const lastMonth = t.subtract({ months: 1 });
				const firstDay = lastMonth.set({ day: 1 });
				const lastDay = t.set({ day: 1 }).subtract({ days: 1 });
				return {
					from: firstDay,
					to: lastDay
				};
			}
		}
	];

	function handlePreset(preset: (typeof presets)[0]) {
		const value = preset.getValue();
		fromDate = value.from;
		toDate = value.to;
	}

	function handleApply() {
		if (!fromDate || !toDate) return;

		onApply({
			field,
			operator: 'between',
			value: {
				from: fromDate.toString(),
				to: toDate.toString()
			}
		});
		onClose();
	}

	function handleClear() {
		onApply(null);
		onClose();
	}

	const displayValue = $derived(() => {
		if (fromDate && toDate) {
			return `${fromDate.toString()} - ${toDate.toString()}`;
		} else if (fromDate) {
			return fromDate.toString();
		}
		return 'Select date range';
	});
</script>

<div class="w-[320px] space-y-3 p-3">
	<div class="space-y-2">
		<label class="text-xs font-medium">Quick Presets</label>
		<div class="grid grid-cols-2 gap-2">
			{#each presets as preset}
				<Button
					variant="outline"
					size="sm"
					onclick={() => handlePreset(preset)}
					class="justify-start"
				>
					{preset.label}
				</Button>
			{/each}
		</div>
	</div>

	<div class="space-y-2">
		<label class="text-xs font-medium">Custom Range</label>
		<div class="grid gap-2">
			<Popover.Root>
				<Popover.Trigger>
					{#snippet child({ props })}
						<Button
							{...props}
							variant="outline"
							class={cn(
								'justify-start text-left font-normal',
								!fromDate && 'text-muted-foreground'
							)}
						>
							<CalendarIcon class="mr-2 h-4 w-4" />
							{fromDate ? fromDate.toString() : 'From date'}
						</Button>
					{/snippet}
				</Popover.Trigger>
				<Popover.Content class="w-auto p-0" align="start">
					<Calendar type="single" bind:value={fromDate} />
				</Popover.Content>
			</Popover.Root>

			<Popover.Root>
				<Popover.Trigger>
					{#snippet child({ props })}
						<Button
							{...props}
							variant="outline"
							class={cn('justify-start text-left font-normal', !toDate && 'text-muted-foreground')}
						>
							<CalendarIcon class="mr-2 h-4 w-4" />
							{toDate ? toDate.toString() : 'To date'}
						</Button>
					{/snippet}
				</Popover.Trigger>
				<Popover.Content class="w-auto p-0" align="start">
					<Calendar type="single" bind:value={toDate} />
				</Popover.Content>
			</Popover.Root>
		</div>
	</div>

	<div class="flex gap-2">
		<Button size="sm" onclick={handleApply} class="flex-1" disabled={!fromDate || !toDate}>
			Apply
		</Button>
		<Button size="sm" variant="outline" onclick={handleClear}>
			<X class="h-3 w-3" />
		</Button>
	</div>
</div>
