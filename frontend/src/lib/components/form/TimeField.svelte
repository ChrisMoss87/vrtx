<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import * as Select from '$lib/components/ui/select';
	import { cn } from '$lib/lib/utils';

	interface Props {
		label?: string;
		name: string;
		value?: string;
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		placeholder?: string;
		width?: 25 | 50 | 75 | 100;
		class?: string;
		use24Hour?: boolean;
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
		placeholder = 'Select time',
		width = 100,
		class: className,
		use24Hour = false,
		onchange
	}: Props = $props();

	// Parse existing value
	let hour = $state<string>('12');
	let minute = $state<string>('00');
	let period = $state<'AM' | 'PM'>('AM');

	if (value) {
		const [h, m] = value.split(':');
		let hours = parseInt(h);

		if (use24Hour) {
			hour = hours.toString().padStart(2, '0');
		} else {
			period = hours >= 12 ? 'PM' : 'AM';
			hours = hours % 12 || 12;
			hour = hours.toString().padStart(2, '0');
		}
		minute = m || '00';
	}

	// Generate hour options
	const hours = $derived(() => {
		if (use24Hour) {
			return Array.from({ length: 24 }, (_, i) => {
				const h = i.toString().padStart(2, '0');
				return { label: h, value: h };
			});
		} else {
			return Array.from({ length: 12 }, (_, i) => {
				const h = (i + 1).toString().padStart(2, '0');
				return { label: h, value: h };
			});
		}
	});

	// Generate minute options
	const minutes = Array.from({ length: 60 }, (_, i) => {
		const m = i.toString().padStart(2, '0');
		return { label: m, value: m };
	});

	function updateValue() {
		if (hour && minute) {
			if (use24Hour) {
				value = `${hour}:${minute}`;
			} else if (period) {
				let hours24 = parseInt(hour);
				if (period === 'PM' && hours24 !== 12) {
					hours24 += 12;
				} else if (period === 'AM' && hours24 === 12) {
					hours24 = 0;
				}
				value = `${hours24.toString().padStart(2, '0')}:${minute}`;
			}
			onchange?.(value);
		}
	}

	function handleHourChange(newValue: string | undefined) {
		if (newValue) {
			hour = newValue;
			updateValue();
		}
	}

	function handleMinuteChange(newValue: string | undefined) {
		if (newValue) {
			minute = newValue;
			updateValue();
		}
	}

	function handlePeriodChange(newValue: string | undefined) {
		if (newValue === 'AM' || newValue === 'PM') {
			period = newValue;
			updateValue();
		}
	}
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} {width} class={className}>
	{#snippet children(props)}
		<div class="flex items-center gap-2">
			<Select.Root selected={{ value: hour, label: hour }} onSelectedChange={(v) => handleHourChange(v?.value)}>
				<Select.Trigger {...props} class="w-[80px]">
					{hour}
				</Select.Trigger>
				<Select.Content>
					{#each hours() as h}
						<Select.Item value={h.value}>{h.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>

			<span class="text-muted-foreground">:</span>

			<Select.Root selected={{ value: minute, label: minute }} onSelectedChange={(v) => handleMinuteChange(v?.value)}>
				<Select.Trigger {...props} class="w-[80px]">
					{minute}
				</Select.Trigger>
				<Select.Content class="max-h-[200px]">
					{#each minutes as m}
						<Select.Item value={m.value}>{m.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>

			{#if !use24Hour}
				<Select.Root selected={{ value: period, label: period }} onSelectedChange={(v) => handlePeriodChange(v?.value)}>
					<Select.Trigger {...props} class="w-[80px]">
						{period}
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="AM">AM</Select.Item>
						<Select.Item value="PM">PM</Select.Item>
					</Select.Content>
				</Select.Root>
			{/if}
		</div>
	{/snippet}
</FieldBase>
