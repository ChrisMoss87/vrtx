<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Calendar from '$lib/components/ui/calendar';
	import * as Popover from '$lib/components/ui/popover';
	import * as Select from '$lib/components/ui/select';
	import { CalendarIcon } from 'lucide-svelte';
	import { cn } from '$lib/utils';
	import { DateFormatter, type DateValue, parseDate } from '@internationalized/date';

	interface Props {
		label?: string;
		name: string;
		value?: string;
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		placeholder?: string;
		class?: string;
		onchange?: (value: string) => void;
	}

	let {
		label,
		name,
		value = $bindable(),
		description,
		error,
		required = false,
		disabled = false,
		placeholder = 'Pick a date and time',
		class: className,
		onchange
	}: Props = $props();

	const df = new DateFormatter('en-US', {
		dateStyle: 'long'
	});

	// Parse existing value
	let dateValue = $state<DateValue | undefined>();
	let hour = $state<string>('12');
	let minute = $state<string>('00');
	let period = $state<'AM' | 'PM'>('AM');

	// Initialize from existing value
	if (value) {
		const date = new Date(value);
		dateValue = parseDate(value.split('T')[0]);
		let hours = date.getHours();
		period = hours >= 12 ? 'PM' : 'AM';
		hours = hours % 12 || 12;
		hour = hours.toString().padStart(2, '0');
		minute = date.getMinutes().toString().padStart(2, '0');
	}

	// Generate hour and minute options
	const hours = Array.from({ length: 12 }, (_, i) => {
		const h = (i + 1).toString().padStart(2, '0');
		return { label: h, value: h };
	});

	const minutes = Array.from({ length: 60 }, (_, i) => {
		const m = i.toString().padStart(2, '0');
		return { label: m, value: m };
	});

	function updateValue() {
		if (dateValue && hour && minute && period) {
			let hours24 = parseInt(hour);
			if (period === 'PM' && hours24 !== 12) {
				hours24 += 12;
			} else if (period === 'AM' && hours24 === 12) {
				hours24 = 0;
			}

			// Create ISO datetime string
			const dateStr = dateValue.toString();
			const timeStr = `${hours24.toString().padStart(2, '0')}:${minute}:00`;
			value = `${dateStr}T${timeStr}`;
			onchange?.(value);
		}
	}

	function handleDateChange(newValue: DateValue | undefined) {
		dateValue = newValue;
		updateValue();
	}

	function handleHourChange(newValue: string) {
		hour = newValue;
		updateValue();
	}

	function handleMinuteChange(newValue: string) {
		minute = newValue;
		updateValue();
	}

	function handlePeriodChange(newValue: string) {
		if (newValue === 'AM' || newValue === 'PM') {
			period = newValue;
			updateValue();
		}
	}

	const displayValue = $derived(() => {
		if (dateValue) {
			return `${df.format(dateValue.toDate('UTC'))} at ${hour}:${minute} ${period}`;
		}
		return placeholder;
	});
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} class={className}>
	{#snippet children(props)}
		<Popover.Root>
			<Popover.Trigger>
				<Button
					{...props}
					variant="outline"
					class={cn(
						'w-full justify-start text-left font-normal',
						!dateValue && 'text-muted-foreground'
					)}
				>
					<CalendarIcon class="mr-2 h-4 w-4" />
					{displayValue()}
				</Button>
			</Popover.Trigger>
			<Popover.Content class="w-auto p-0" align="start">
				<div class="p-3">
					<Calendar.Calendar type="single" value={dateValue} onValueChange={handleDateChange} initialFocus />
					<div class="mt-3 flex items-center gap-2 border-t pt-3">
						<Select.Root type="single" value={hour} onValueChange={handleHourChange}>
							<Select.Trigger class="w-[70px]">
								{hour}
							</Select.Trigger>
							<Select.Content>
								{#each hours as h}
									<Select.Item value={h.value}>{h.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
						<span>:</span>
						<Select.Root type="single" value={minute} onValueChange={handleMinuteChange}>
							<Select.Trigger class="w-[70px]">
								{minute}
							</Select.Trigger>
							<Select.Content class="max-h-[200px]">
								{#each minutes as m}
									<Select.Item value={m.value}>{m.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
						<Select.Root type="single" value={period} onValueChange={handlePeriodChange}>
							<Select.Trigger class="w-[70px]">
								{period}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="AM">AM</Select.Item>
								<Select.Item value="PM">PM</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>
				</div>
			</Popover.Content>
		</Popover.Root>
	{/snippet}
</FieldBase>
