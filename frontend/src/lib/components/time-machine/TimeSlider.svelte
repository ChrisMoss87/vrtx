<script lang="ts">
	import type { TimelineMarker } from '$lib/api/time-machine';

	interface Props {
		markers?: TimelineMarker[];
		selectedTimestamp?: string | null;
		minDate?: Date | null;
		maxDate?: Date | null;
		onSelect?: (data: { timestamp: string; marker: TimelineMarker | null }) => void;
	}

	let {
		markers = [],
		selectedTimestamp = $bindable(null),
		minDate = null,
		maxDate = null,
		onSelect,
	}: Props = $props();

	const sortedMarkers = $derived([...markers].sort(
		(a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime()
	));

	const effectiveMinDate = $derived(minDate ?? (sortedMarkers[0] ? new Date(sortedMarkers[0].timestamp) : new Date()));
	const effectiveMaxDate = $derived(maxDate ?? new Date());
	const totalRange = $derived(effectiveMaxDate.getTime() - effectiveMinDate.getTime());

	function getMarkerPosition(timestamp: string): number {
		const time = new Date(timestamp).getTime();
		const position = ((time - effectiveMinDate.getTime()) / totalRange) * 100;
		return Math.max(0, Math.min(100, position));
	}

	function handleMarkerClick(marker: TimelineMarker) {
		selectedTimestamp = marker.timestamp;
		onSelect?.({ timestamp: marker.timestamp, marker });
	}

	function handleSliderChange(event: Event) {
		const target = event.target as HTMLInputElement;
		const percentage = parseFloat(target.value);
		const timestamp = new Date(
			effectiveMinDate.getTime() + (percentage / 100) * totalRange
		).toISOString();
		selectedTimestamp = timestamp;
		onSelect?.({ timestamp, marker: null });
	}

	const sliderValue = $derived(selectedTimestamp
		? getMarkerPosition(selectedTimestamp)
		: 100);

	function formatDate(timestamp: string): string {
		return new Date(timestamp).toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric'
		});
	}

	function formatTime(timestamp: string): string {
		return new Date(timestamp).toLocaleTimeString('en-US', {
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function getMarkerTypeColor(type: string): string {
		switch (type) {
			case 'stage_change':
				return 'bg-purple-500';
			case 'field_change':
				return 'bg-blue-500';
			case 'manual':
				return 'bg-green-500';
			case 'daily':
				return 'bg-gray-400';
			default:
				return 'bg-gray-500';
		}
	}
</script>

<div class="relative w-full py-4">
	<!-- Timeline track -->
	<div class="relative h-2 bg-muted rounded-full">
		<!-- Progress bar -->
		<div
			class="absolute h-full bg-primary rounded-full transition-all"
			style="width: {sliderValue}%"
		></div>

		<!-- Markers -->
		{#each sortedMarkers as marker}
			{@const position = getMarkerPosition(marker.timestamp)}
			<button
				type="button"
				class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-3 h-3 rounded-full border-2 border-background cursor-pointer hover:scale-125 transition-transform {getMarkerTypeColor(
					marker.type
				)} {selectedTimestamp === marker.timestamp ? 'ring-2 ring-primary ring-offset-2' : ''}"
				style="left: {position}%"
				title="{marker.label} - {formatDate(marker.timestamp)} {formatTime(marker.timestamp)}"
				onclick={() => handleMarkerClick(marker)}
			>
				<span class="sr-only">{marker.label}</span>
			</button>
		{/each}
	</div>

	<!-- Invisible slider for scrubbing -->
	<input
		type="range"
		min="0"
		max="100"
		step="0.1"
		value={sliderValue}
		oninput={handleSliderChange}
		class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
	/>

	<!-- Date labels -->
	<div class="flex justify-between mt-2 text-xs text-muted-foreground">
		<span>{formatDate(effectiveMinDate.toISOString())}</span>
		{#if selectedTimestamp}
			<span class="font-medium text-foreground">
				{formatDate(selectedTimestamp)} {formatTime(selectedTimestamp)}
			</span>
		{/if}
		<span>Today</span>
	</div>
</div>

<style>
	/* Hide default slider appearance */
	input[type='range'] {
		-webkit-appearance: none;
		appearance: none;
		background: transparent;
	}

	input[type='range']::-webkit-slider-thumb {
		-webkit-appearance: none;
		appearance: none;
		width: 16px;
		height: 16px;
		border-radius: 50%;
		background: transparent;
		cursor: pointer;
	}

	input[type='range']::-moz-range-thumb {
		width: 16px;
		height: 16px;
		border-radius: 50%;
		background: transparent;
		cursor: pointer;
		border: none;
	}
</style>
