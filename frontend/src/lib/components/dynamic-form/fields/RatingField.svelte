<script lang="ts">
	import { Star, Heart, Circle } from 'lucide-svelte';
	import type { FieldSettings } from '$lib/types/modules';
	import { cn } from '$lib/utils';

	interface Props {
		value: number | null;
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: Partial<FieldSettings>;
		onchange: (value: number | null) => void;
	}

	let {
		value = $bindable(null),
		error,
		disabled = false,
		required,
		settings,
		onchange
	}: Props = $props();

	const maxRating = $derived(settings?.max_rating ?? 5);
	const allowHalf = $derived(settings?.allow_half ?? false);
	const ratingIcon = $derived(settings?.rating_icon ?? 'star');

	let hoverValue = $state<number | null>(null);

	// Calculate display value (for half star support)
	function getDisplayValue(index: number): 'full' | 'half' | 'empty' {
		const compareValue = hoverValue ?? value ?? 0;
		if (compareValue >= index + 1) return 'full';
		if (allowHalf && compareValue >= index + 0.5) return 'half';
		return 'empty';
	}

	function handleClick(index: number, isHalf: boolean) {
		if (disabled) return;
		const newValue = isHalf && allowHalf ? index + 0.5 : index + 1;
		// Allow clearing by clicking on the same value
		if (value === newValue) {
			value = null;
			onchange(null);
		} else {
			value = newValue;
			onchange(newValue);
		}
	}

	function handleMouseMove(event: MouseEvent, index: number) {
		if (disabled || !allowHalf) return;
		const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
		const isHalf = event.clientX - rect.left < rect.width / 2;
		hoverValue = isHalf ? index + 0.5 : index + 1;
	}

	function handleMouseEnter(index: number) {
		if (disabled) return;
		hoverValue = index + 1;
	}

	function handleMouseLeave() {
		hoverValue = null;
	}

	function handleKeyDown(event: KeyboardEvent) {
		if (disabled) return;
		const current = value ?? 0;
		const step = allowHalf ? 0.5 : 1;

		switch (event.key) {
			case 'ArrowRight':
			case 'ArrowUp':
				event.preventDefault();
				const nextValue = Math.min(current + step, maxRating);
				value = nextValue;
				onchange(nextValue);
				break;
			case 'ArrowLeft':
			case 'ArrowDown':
				event.preventDefault();
				const prevValue = Math.max(current - step, 0);
				value = prevValue > 0 ? prevValue : null;
				onchange(prevValue > 0 ? prevValue : null);
				break;
			case 'Home':
				event.preventDefault();
				value = allowHalf ? 0.5 : 1;
				onchange(value);
				break;
			case 'End':
				event.preventDefault();
				value = maxRating;
				onchange(maxRating);
				break;
			case 'Delete':
			case 'Backspace':
				event.preventDefault();
				value = null;
				onchange(null);
				break;
		}
	}

	const IconComponent = $derived.by(() => {
		switch (ratingIcon) {
			case 'heart':
				return Heart;
			case 'circle':
				return Circle;
			default:
				return Star;
		}
	});
</script>

<div
	class={cn(
		'flex items-center gap-1',
		disabled && 'cursor-not-allowed opacity-50',
		error && 'text-destructive'
	)}
	role="slider"
	aria-valuemin={0}
	aria-valuemax={maxRating}
	aria-valuenow={value ?? 0}
	aria-label="Rating"
	tabindex={disabled ? -1 : 0}
	onkeydown={handleKeyDown}
>
	{#each Array(maxRating) as _, index}
		{@const displayValue = getDisplayValue(index)}
		<button
			type="button"
			class={cn(
				'relative h-8 w-8 transition-colors focus:outline-none',
				!disabled && 'cursor-pointer hover:scale-110'
			)}
			{disabled}
			onclick={(e) => {
				const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
				const isHalf = allowHalf && e.clientX - rect.left < rect.width / 2;
				handleClick(index, isHalf);
			}}
			onmouseenter={() => handleMouseEnter(index)}
			onmousemove={(e) => handleMouseMove(e, index)}
			onmouseleave={handleMouseLeave}
			aria-label={`Rate ${index + 1} out of ${maxRating}`}
		>
			<!-- Empty icon (background) -->
			<svelte:component
				this={IconComponent}
				class="absolute inset-0 h-8 w-8 text-muted-foreground/30"
			/>

			<!-- Filled icon -->
			{#if displayValue === 'full'}
				<svelte:component
					this={IconComponent}
					class="absolute inset-0 h-8 w-8 fill-amber-400 text-amber-400"
				/>
			{:else if displayValue === 'half'}
				<div class="absolute inset-0 h-8 w-4 overflow-hidden">
					<svelte:component this={IconComponent} class="h-8 w-8 fill-amber-400 text-amber-400" />
				</div>
			{/if}
		</button>
	{/each}

	<!-- Display value -->
	{#if value !== null}
		<span class="ml-2 text-sm text-muted-foreground">
			{value}/{maxRating}
		</span>
	{/if}
</div>

{#if !disabled && !required}
	<button
		type="button"
		class="mt-1 text-xs text-muted-foreground hover:text-foreground"
		onclick={() => {
			value = null;
			onchange(null);
		}}
	>
		Clear rating
	</button>
{/if}
