<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Calendar from '$lib/components/ui/calendar';
	import * as Popover from '$lib/components/ui/popover';
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
		minDate?: string;
		maxDate?: string;
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
		placeholder = 'Pick a date',
		class: className,
		minDate,
		maxDate,
		onchange
	}: Props = $props();

	const df = new DateFormatter('en-US', {
		dateStyle: 'long'
	});

	let dateValue = $state<DateValue | undefined>(value ? parseDate(value) : undefined);

	function handleDateChange(newValue: DateValue | undefined) {
		dateValue = newValue;
		if (newValue) {
			// Convert DateValue to ISO string (YYYY-MM-DD)
			value = newValue.toString();
			onchange?.(value);
		} else {
			value = '';
			onchange?.('');
		}
	}
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
					{dateValue ? df.format(dateValue.toDate('UTC')) : placeholder}
				</Button>
			</Popover.Trigger>
			<Popover.Content class="w-auto p-0" align="start">
				<Calendar.Calendar type="single" value={dateValue} onValueChange={handleDateChange} initialFocus />
			</Popover.Content>
		</Popover.Root>
	{/snippet}
</FieldBase>
