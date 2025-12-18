<script lang="ts">
	import { CalendarDate, today, getLocalTimeZone, parseDate } from '@internationalized/date';
	import { Calendar, ChevronDown } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Popover from '$lib/components/ui/popover';
	import * as RangeCalendar from '$lib/components/ui/range-calendar';
	import { cn } from '$lib/utils';
	import type { DateRange } from 'bits-ui';

	export type DatePreset = {
		label: string;
		getValue: () => { start: CalendarDate; end: CalendarDate };
	};

	export interface DateRangeValue {
		start: string | null;
		end: string | null;
		preset?: string;
	}

	interface Props {
		value?: DateRangeValue;
		onchange?: (value: DateRangeValue) => void;
		placeholder?: string;
		showPresets?: boolean;
		customPresets?: DatePreset[];
		class?: string;
		disabled?: boolean;
		align?: 'start' | 'center' | 'end';
	}

	let {
		value = { start: null, end: null },
		onchange,
		placeholder = 'Select date range',
		showPresets = true,
		customPresets,
		class: className,
		disabled = false,
		align = 'start'
	}: Props = $props();

	let open = $state(false);

	const tz = getLocalTimeZone();
	const todayDate = today(tz);

	// Default presets
	const defaultPresets: DatePreset[] = [
		{
			label: 'Today',
			getValue: () => ({ start: todayDate, end: todayDate })
		},
		{
			label: 'Yesterday',
			getValue: () => {
				const yesterday = todayDate.subtract({ days: 1 });
				return { start: yesterday, end: yesterday };
			}
		},
		{
			label: 'Last 7 days',
			getValue: () => ({
				start: todayDate.subtract({ days: 6 }),
				end: todayDate
			})
		},
		{
			label: 'Last 14 days',
			getValue: () => ({
				start: todayDate.subtract({ days: 13 }),
				end: todayDate
			})
		},
		{
			label: 'Last 30 days',
			getValue: () => ({
				start: todayDate.subtract({ days: 29 }),
				end: todayDate
			})
		},
		{
			label: 'Last 90 days',
			getValue: () => ({
				start: todayDate.subtract({ days: 89 }),
				end: todayDate
			})
		},
		{
			label: 'This week',
			getValue: () => {
				const dayOfWeek = todayDate.toDate(tz).getDay();
				const startOfWeek = todayDate.subtract({ days: dayOfWeek });
				return { start: startOfWeek, end: todayDate };
			}
		},
		{
			label: 'Last week',
			getValue: () => {
				const dayOfWeek = todayDate.toDate(tz).getDay();
				const endOfLastWeek = todayDate.subtract({ days: dayOfWeek + 1 });
				const startOfLastWeek = endOfLastWeek.subtract({ days: 6 });
				return { start: startOfLastWeek, end: endOfLastWeek };
			}
		},
		{
			label: 'This month',
			getValue: () => {
				const startOfMonth = todayDate.set({ day: 1 });
				return { start: startOfMonth, end: todayDate };
			}
		},
		{
			label: 'Last month',
			getValue: () => {
				const startOfThisMonth = todayDate.set({ day: 1 });
				const endOfLastMonth = startOfThisMonth.subtract({ days: 1 });
				const startOfLastMonth = endOfLastMonth.set({ day: 1 });
				return { start: startOfLastMonth, end: endOfLastMonth };
			}
		},
		{
			label: 'This quarter',
			getValue: () => {
				const month = todayDate.month;
				const quarterStart = Math.floor((month - 1) / 3) * 3 + 1;
				const startOfQuarter = todayDate.set({ month: quarterStart, day: 1 });
				return { start: startOfQuarter, end: todayDate };
			}
		},
		{
			label: 'This year',
			getValue: () => {
				const startOfYear = todayDate.set({ month: 1, day: 1 });
				return { start: startOfYear, end: todayDate };
			}
		},
		{
			label: 'Last year',
			getValue: () => {
				const startOfLastYear = new CalendarDate(todayDate.year - 1, 1, 1);
				const endOfLastYear = new CalendarDate(todayDate.year - 1, 12, 31);
				return { start: startOfLastYear, end: endOfLastYear };
			}
		}
	];

	const presets = $derived(customPresets || defaultPresets);

	// Convert string dates to CalendarDate for the calendar
	let calendarValue = $state<DateRange | undefined>(undefined);

	$effect(() => {
		if (!value.start && !value.end) {
			calendarValue = undefined;
		} else {
			try {
				calendarValue = {
					start: value.start ? parseDate(value.start) : undefined,
					end: value.end ? parseDate(value.end) : undefined
				};
			} catch {
				calendarValue = undefined;
			}
		}
	});

	// Format date for display
	function formatDate(date: CalendarDate): string {
		return date.toDate(tz).toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric',
			year: 'numeric'
		});
	}

	// Display text
	const displayText = $derived.by(() => {
		if (value.preset) {
			return value.preset;
		}
		if (value.start && value.end) {
			try {
				const start = parseDate(value.start);
				const end = parseDate(value.end);
				if (start.toString() === end.toString()) {
					return formatDate(start);
				}
				return `${formatDate(start)} - ${formatDate(end)}`;
			} catch {
				return placeholder;
			}
		}
		return placeholder;
	});

	function handlePresetClick(preset: DatePreset) {
		const { start, end } = preset.getValue();
		onchange?.({
			start: start.toString(),
			end: end.toString(),
			preset: preset.label
		});
		open = false;
	}

	function handleCalendarChange(range: DateRange | undefined) {
		if (range?.start && range?.end) {
			onchange?.({
				start: range.start.toString(),
				end: range.end.toString(),
				preset: undefined
			});
		}
	}

	function handleClear() {
		onchange?.({ start: null, end: null, preset: undefined });
		open = false;
	}
</script>

<Popover.Root bind:open>
	<Popover.Trigger {disabled}>
		{#snippet child({ props })}
			<Button
				{...props}
				variant="outline"
				class={cn(
					'justify-start text-left font-normal',
					!value.start && 'text-muted-foreground',
					className
				)}
				{disabled}
			>
				<Calendar class="mr-2 h-4 w-4" />
				<span class="flex-1 truncate">{displayText}</span>
				<ChevronDown class="ml-2 h-4 w-4 opacity-50" />
			</Button>
		{/snippet}
	</Popover.Trigger>
	<Popover.Content class="w-auto p-0" {align}>
		<div class="flex">
			<!-- Presets sidebar -->
			{#if showPresets}
				<div class="border-r p-2 space-y-1 w-36 max-h-[400px] overflow-y-auto">
					<div class="px-2 py-1.5 text-xs font-medium text-muted-foreground">Quick Select</div>
					{#each presets as preset}
						<Button
							variant={value.preset === preset.label ? 'secondary' : 'ghost'}
							size="sm"
							class="w-full justify-start text-xs h-7"
							onclick={() => handlePresetClick(preset)}
						>
							{preset.label}
						</Button>
					{/each}
				</div>
			{/if}

			<!-- Calendar -->
			<div class="p-3">
				<RangeCalendar.RangeCalendar
					bind:value={calendarValue}
					onValueChange={handleCalendarChange}
					numberOfMonths={2}
				/>

				<!-- Footer -->
				<div class="flex items-center justify-between border-t pt-3 mt-3">
					<Button variant="ghost" size="sm" onclick={handleClear}>
						Clear
					</Button>
					<Button size="sm" onclick={() => (open = false)}>
						Done
					</Button>
				</div>
			</div>
		</div>
	</Popover.Content>
</Popover.Root>
