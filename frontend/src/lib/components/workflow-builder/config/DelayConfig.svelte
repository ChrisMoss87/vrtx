<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], onConfigChange }: Props = $props();

	// Local state
	let delayType = $state<string>((config.delay_type as string) || 'fixed');
	let delayValue = $state<number>((config.delay_value as number) || 0);
	let delayUnit = $state<string>((config.delay_unit as string) || 'minutes');
	let untilField = $state<string>((config.until_field as string) || '');
	let untilTime = $state<string>((config.until_time as string) || '09:00');

	function emitChange() {
		onConfigChange?.({
			delay_type: delayType,
			delay_value: delayValue,
			delay_unit: delayUnit,
			until_field: untilField,
			until_time: untilTime
		});
	}

	// Date/datetime fields for "until field" option
	const dateFields = $derived(moduleFields.filter((f) => ['date', 'datetime'].includes(f.type)));
</script>

<div class="space-y-4">
	<h4 class="font-medium">Delay Configuration</h4>

	<!-- Delay Type -->
	<div class="space-y-2">
		<Label>Delay Type</Label>
		<Select.Root
			type="single"
			value={delayType}
			onValueChange={(v) => {
				if (v) {
					delayType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{delayType === 'fixed'
					? 'Fixed Duration'
					: delayType === 'until_field'
						? 'Until Date/Time Field'
						: delayType === 'until_time'
							? 'Until Specific Time'
							: 'Select delay type'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="fixed">Fixed Duration</Select.Item>
				<Select.Item value="until_field">Until Date/Time Field</Select.Item>
				<Select.Item value="until_time">Until Specific Time of Day</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Fixed Duration -->
	{#if delayType === 'fixed'}
		<div class="grid gap-4 sm:grid-cols-2">
			<div class="space-y-2">
				<Label>Duration</Label>
				<Input
					type="number"
					min="0"
					value={String(delayValue)}
					oninput={(e) => {
						delayValue = parseInt(e.currentTarget.value) || 0;
						emitChange();
					}}
				/>
			</div>
			<div class="space-y-2">
				<Label>Unit</Label>
				<Select.Root
					type="single"
					value={delayUnit}
					onValueChange={(v) => {
						if (v) {
							delayUnit = v;
							emitChange();
						}
					}}
				>
					<Select.Trigger>
						{delayUnit === 'seconds'
							? 'Seconds'
							: delayUnit === 'minutes'
								? 'Minutes'
								: delayUnit === 'hours'
									? 'Hours'
									: delayUnit === 'days'
										? 'Days'
										: 'Select unit'}
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="seconds">Seconds</Select.Item>
						<Select.Item value="minutes">Minutes</Select.Item>
						<Select.Item value="hours">Hours</Select.Item>
						<Select.Item value="days">Days</Select.Item>
					</Select.Content>
				</Select.Root>
			</div>
		</div>
		<p class="text-xs text-muted-foreground">
			Wait for {delayValue} {delayUnit} before continuing to the next step
		</p>
	{/if}

	<!-- Until Field -->
	{#if delayType === 'until_field'}
		<div class="space-y-2">
			<Label>Date/Time Field</Label>
			<Select.Root
				type="single"
				value={untilField}
				onValueChange={(v) => {
					if (v) {
						untilField = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{dateFields.find((f) => f.api_name === untilField)?.label || 'Select date field'}
				</Select.Trigger>
				<Select.Content>
					{#each dateFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			{#if dateFields.length === 0}
				<p class="text-xs text-muted-foreground">
					No date or datetime fields found in this module
				</p>
			{/if}
		</div>

		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The workflow will pause until the date/time in this field is reached.
				If the date is in the past, the workflow will continue immediately.
			</p>
		</div>
	{/if}

	<!-- Until Time of Day -->
	{#if delayType === 'until_time'}
		<div class="space-y-2">
			<Label>Time of Day</Label>
			<Input
				type="time"
				value={untilTime}
				oninput={(e) => {
					untilTime = e.currentTarget.value;
					emitChange();
				}}
			/>
		</div>

		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The workflow will pause until this time of day is reached.
				If the time has already passed today, it will wait until tomorrow.
				Time is based on the server timezone.
			</p>
		</div>
	{/if}

	<!-- General Info -->
	<div class="flex items-start gap-2 rounded-lg bg-yellow-50 p-3 dark:bg-yellow-950">
		<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-yellow-600 dark:text-yellow-400" />
		<p class="text-xs text-yellow-700 dark:text-yellow-300">
			Delayed workflows are queued and executed by the background job system.
			Ensure your queue workers are running for delays to work correctly.
		</p>
	</div>
</div>
