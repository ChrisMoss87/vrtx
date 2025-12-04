<script lang="ts">
	import * as Select from '$lib/components/ui/select';
	import { Progress } from '$lib/components/ui/progress';
	import type { FieldSettings, FieldOption, ProgressStage } from '$lib/types/modules';
	import { cn } from '$lib/utils';

	interface Props {
		value: string;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: Partial<FieldSettings>;
		options?: FieldOption[];
		onchange: (value: string) => void;
	}

	let {
		value = $bindable(''),
		error,
		disabled = false,
		placeholder = 'Select a stage...',
		required,
		settings,
		options = [],
		onchange
	}: Props = $props();

	// Get stages from progress_mapping settings or fall back to options
	const stages = $derived.by<ProgressStage[]>(() => {
		if (settings?.progress_mapping?.stages) {
			return settings.progress_mapping.stages;
		}
		// Fall back to converting options to stages
		if (options.length > 0) {
			return options.map((opt, idx) => ({
				value: opt.value,
				label: opt.label,
				percentage: Math.round((idx / (options.length - 1)) * 100) || 0,
				color: opt.metadata?.color
			}));
		}
		return [];
	});

	const displayStyle = $derived(settings?.progress_mapping?.display_style ?? 'bar');
	const showPercentage = $derived(settings?.progress_mapping?.show_percentage ?? true);
	const showLabel = $derived(settings?.progress_mapping?.show_label ?? true);

	// Get current stage info
	const currentStage = $derived.by(() => {
		return stages.find((s) => s.value === value) ?? null;
	});

	const currentPercentage = $derived(currentStage?.percentage ?? 0);
	const currentColor = $derived(currentStage?.color ?? '#3b82f6');

	function handleValueChange(val: string | undefined) {
		if (val) {
			value = val;
			onchange(val);
		}
	}
</script>

<div class="space-y-3">
	<!-- Stage Selector -->
	<Select.Root type="single" value={value || undefined} onValueChange={handleValueChange} {disabled}>
		<Select.Trigger class={cn('w-full', error ? 'border-destructive' : '')}>
			<span class="flex items-center gap-2">
				{#if currentStage}
					<span
						class="h-3 w-3 rounded-full"
						style="background-color: {currentStage.color ?? '#94a3b8'}"
					></span>
					<span>{currentStage.label}</span>
					{#if showPercentage}
						<span class="ml-auto text-muted-foreground">({currentStage.percentage}%)</span>
					{/if}
				{:else}
					{placeholder}
				{/if}
			</span>
		</Select.Trigger>
		<Select.Content>
			<Select.Group>
				{#each stages as stage}
					<Select.Item value={stage.value}>
						<span class="flex items-center gap-2">
							<span
								class="h-3 w-3 rounded-full"
								style="background-color: {stage.color ?? '#94a3b8'}"
							></span>
							<span>{stage.label}</span>
							{#if showPercentage}
								<span class="ml-auto text-muted-foreground">({stage.percentage}%)</span>
							{/if}
						</span>
					</Select.Item>
				{/each}
				{#if stages.length === 0}
					<Select.Item value="" disabled>No stages configured</Select.Item>
				{/if}
			</Select.Group>
		</Select.Content>
	</Select.Root>

	<!-- Progress Visualization -->
	{#if displayStyle === 'bar'}
		<div class="relative">
			<Progress value={currentPercentage} max={100} class="h-3" />
			<div
				class="absolute inset-0 h-3 rounded-full transition-all"
				style="width: {currentPercentage}%; background-color: {currentColor};"
			></div>
		</div>
	{:else if displayStyle === 'steps'}
		<div class="flex items-center gap-1">
			{#each stages as stage, i}
				{@const isActive = stages.findIndex((s) => s.value === value) >= i || stage.value === value}
				{@const isCurrent = stage.value === value}
				<div class="flex flex-1 flex-col items-center gap-1">
					<div
						class={cn('h-2 w-full rounded-full transition-colors', isActive ? '' : 'bg-muted')}
						style={isActive ? `background-color: ${stage.color ?? '#3b82f6'}` : ''}
					></div>
					{#if showLabel}
						<span
							class={cn(
								'max-w-full truncate text-xs',
								isCurrent ? 'font-medium' : 'text-muted-foreground'
							)}
						>
							{stage.label}
						</span>
					{/if}
				</div>
				{#if i < stages.length - 1}
					<div class="w-1"></div>
				{/if}
			{/each}
		</div>
	{:else if displayStyle === 'funnel'}
		<div class="space-y-1">
			{#each stages as stage, i}
				{@const isActive = stages.findIndex((s) => s.value === value) >= i || stage.value === value}
				{@const isCurrent = stage.value === value}
				{@const width = 100 - i * (50 / stages.length)}
				<div class="flex items-center gap-2" style="padding-left: {i * 8}px;">
					<div
						class={cn(
							'flex h-6 items-center justify-center rounded transition-colors',
							isActive ? '' : 'bg-muted'
						)}
						style="width: {width}%; {isActive
							? `background-color: ${stage.color ?? '#3b82f6'}`
							: ''}"
					>
						{#if showLabel && isCurrent}
							<span class="truncate px-2 text-xs font-medium text-white">
								{stage.label}
							</span>
						{/if}
					</div>
				</div>
			{/each}
		</div>
	{/if}

	<!-- Summary -->
	{#if showPercentage && value}
		<div class="flex items-center justify-between text-sm">
			<span class="text-muted-foreground">Progress</span>
			<span class="font-medium" style="color: {currentColor}">
				{currentPercentage}%
			</span>
		</div>
	{/if}
</div>
