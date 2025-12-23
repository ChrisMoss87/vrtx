<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import {
		Play,
		Pause,
		ChevronLeft,
		ChevronRight,
		X,
		Maximize,
		Minimize,
		Settings
	} from 'lucide-svelte';
	import * as Popover from '$lib/components/ui/popover';
	import { Slider } from '$lib/components/ui/slider';
	import type { DashboardWidget } from '$lib/api/dashboards';

	interface Props {
		widgets: DashboardWidget[];
		widgetData: Record<number, unknown>;
		onClose: () => void;
	}

	let { widgets, widgetData, onClose }: Props = $props();

	let currentIndex = $state(0);
	let autoAdvance = $state(true);
	let intervalMs = $state(10000);
	let isFullscreen = $state(false);
	let containerRef = $state<HTMLDivElement | null>(null);
	let intervalId = $state<ReturnType<typeof setInterval> | null>(null);

	// Filter to presentable widgets (excluding text, iframe, embed)
	const presentableWidgets = $derived(
		widgets.filter((w) => !['text', 'iframe', 'embed', 'quick_links'].includes(w.type))
	);

	const currentWidget = $derived(presentableWidgets[currentIndex]);
	const currentData = $derived(currentWidget ? widgetData[currentWidget.id] : null);

	onMount(() => {
		// Start auto-advance
		if (autoAdvance) {
			startAutoAdvance();
		}

		// Listen for keyboard events
		document.addEventListener('keydown', handleKeydown);

		// Check initial fullscreen state
		isFullscreen = !!document.fullscreenElement;
		document.addEventListener('fullscreenchange', handleFullscreenChange);
	});

	onDestroy(() => {
		stopAutoAdvance();
		document.removeEventListener('keydown', handleKeydown);
		document.removeEventListener('fullscreenchange', handleFullscreenChange);
	});

	function startAutoAdvance() {
		stopAutoAdvance();
		intervalId = setInterval(() => {
			nextSlide();
		}, intervalMs);
	}

	function stopAutoAdvance() {
		if (intervalId) {
			clearInterval(intervalId);
			intervalId = null;
		}
	}

	function toggleAutoAdvance() {
		autoAdvance = !autoAdvance;
		if (autoAdvance) {
			startAutoAdvance();
		} else {
			stopAutoAdvance();
		}
	}

	function nextSlide() {
		currentIndex = (currentIndex + 1) % presentableWidgets.length;
	}

	function prevSlide() {
		currentIndex = (currentIndex - 1 + presentableWidgets.length) % presentableWidgets.length;
	}

	function goToSlide(index: number) {
		currentIndex = index;
		if (autoAdvance) {
			startAutoAdvance();
		}
	}

	function handleKeydown(event: KeyboardEvent) {
		switch (event.key) {
			case 'ArrowRight':
			case ' ':
				nextSlide();
				break;
			case 'ArrowLeft':
				prevSlide();
				break;
			case 'Escape':
				if (isFullscreen) {
					exitFullscreen();
				} else {
					onClose();
				}
				break;
			case 'f':
				toggleFullscreen();
				break;
			case 'p':
				toggleAutoAdvance();
				break;
		}
	}

	function handleFullscreenChange() {
		isFullscreen = !!document.fullscreenElement;
	}

	async function toggleFullscreen() {
		if (!containerRef) return;

		if (isFullscreen) {
			await exitFullscreen();
		} else {
			await enterFullscreen();
		}
	}

	async function enterFullscreen() {
		try {
			await containerRef?.requestFullscreen();
		} catch (error) {
			console.error('Failed to enter fullscreen:', error);
		}
	}

	async function exitFullscreen() {
		try {
			await document.exitFullscreen();
		} catch (error) {
			console.error('Failed to exit fullscreen:', error);
		}
	}

	function handleIntervalChange(value: number[]) {
		intervalMs = value[0] * 1000;
		if (autoAdvance) {
			startAutoAdvance();
		}
	}

	function formatValue(value: unknown): string {
		if (typeof value === 'number') {
			if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
			if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
			return value.toLocaleString();
		}
		return String(value ?? '-');
	}
</script>

<div
	bind:this={containerRef}
	class="fixed inset-0 z-50 flex flex-col bg-background"
>
	<!-- Header -->
	<div class="flex items-center justify-between px-6 py-4 border-b bg-background/95 backdrop-blur">
		<div class="flex items-center gap-4">
			<Badge variant="outline" class="text-sm">
				{currentIndex + 1} / {presentableWidgets.length}
			</Badge>
			{#if currentWidget}
				<h2 class="text-xl font-semibold">{currentWidget.title}</h2>
			{/if}
		</div>

		<div class="flex items-center gap-2">
			<!-- Auto-advance controls -->
			<Button
				variant={autoAdvance ? 'default' : 'outline'}
				size="sm"
				onclick={toggleAutoAdvance}
			>
				{#if autoAdvance}
					<Pause class="mr-2 h-4 w-4" />
					Pause
				{:else}
					<Play class="mr-2 h-4 w-4" />
					Play
				{/if}
			</Button>

			<!-- Settings -->
			<Popover.Root>
				<Popover.Trigger>
					{#snippet child({ props })}
						<Button variant="outline" size="icon" {...props}>
							<Settings class="h-4 w-4" />
						</Button>
					{/snippet}
				</Popover.Trigger>
				<Popover.Content class="w-64">
					<div class="space-y-4">
						<div class="space-y-2">
							<label class="text-sm font-medium">Auto-advance interval</label>
							<div class="flex items-center gap-4">
								<Slider
									type="multiple"
									value={[intervalMs / 1000]}
									onValueChange={handleIntervalChange}
									min={5}
									max={60}
									step={5}
									class="flex-1"
								/>
								<span class="text-sm text-muted-foreground w-10">
									{intervalMs / 1000}s
								</span>
							</div>
						</div>
					</div>
				</Popover.Content>
			</Popover.Root>

			<!-- Fullscreen -->
			<Button variant="outline" size="icon" onclick={toggleFullscreen}>
				{#if isFullscreen}
					<Minimize class="h-4 w-4" />
				{:else}
					<Maximize class="h-4 w-4" />
				{/if}
			</Button>

			<!-- Close -->
			<Button variant="outline" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>
	</div>

	<!-- Main content -->
	<div class="flex-1 flex items-center justify-center p-8 relative">
		<!-- Navigation arrows -->
		<Button
			variant="ghost"
			size="icon"
			class="absolute left-4 h-12 w-12 rounded-full"
			onclick={prevSlide}
		>
			<ChevronLeft class="h-8 w-8" />
		</Button>

		<!-- Widget display -->
		{#if currentWidget && currentData}
			<div class="w-full max-w-4xl">
				{#if currentWidget.type === 'kpi' || currentWidget.type === 'goal_kpi'}
					<div class="text-center space-y-4">
						<div class="text-8xl font-bold">
							{formatValue(typeof currentData === 'object' && currentData !== null && 'value' in currentData ? currentData.value : currentData)}
						</div>
						{#if typeof currentData === 'object' && currentData !== null && 'change_percent' in currentData}
							{@const data = currentData as { change_percent?: number; change_type?: string }}
							{#if data.change_percent !== null && data.change_percent !== undefined}
								<div
									class="text-2xl {data.change_type === 'increase'
										? 'text-green-500'
										: data.change_type === 'decrease'
											? 'text-red-500'
											: 'text-muted-foreground'}"
								>
									{data.change_percent >= 0 ? '+' : ''}{data.change_percent.toFixed(1)}%
								</div>
							{/if}
						{/if}
					</div>
				{:else if currentWidget.type === 'chart'}
					<div class="aspect-video bg-card rounded-lg p-4">
						<!-- Chart would render here - for now show placeholder -->
						<div class="h-full flex items-center justify-center text-muted-foreground">
							Chart: {currentWidget.title}
						</div>
					</div>
				{:else}
					<div class="aspect-video bg-card rounded-lg p-4 flex items-center justify-center">
						<span class="text-muted-foreground">
							{currentWidget.type}: {currentWidget.title}
						</span>
					</div>
				{/if}
			</div>
		{:else}
			<div class="text-center text-muted-foreground">
				No widget data available
			</div>
		{/if}

		<Button
			variant="ghost"
			size="icon"
			class="absolute right-4 h-12 w-12 rounded-full"
			onclick={nextSlide}
		>
			<ChevronRight class="h-8 w-8" />
		</Button>
	</div>

	<!-- Footer with slide indicators -->
	<div class="flex items-center justify-center gap-2 px-6 py-4 border-t">
		{#each presentableWidgets as widget, index}
			<button
				type="button"
				class="h-2 w-2 rounded-full transition-colors {index === currentIndex
					? 'bg-primary'
					: 'bg-muted hover:bg-muted-foreground/50'}"
				onclick={() => goToSlide(index)}
			/>
		{/each}
	</div>

	<!-- Keyboard shortcuts hint -->
	<div class="absolute bottom-4 left-4 text-xs text-muted-foreground">
		<kbd class="px-1 bg-muted rounded">Space</kbd> / <kbd class="px-1 bg-muted rounded">→</kbd> Next
		<span class="mx-2">|</span>
		<kbd class="px-1 bg-muted rounded">←</kbd> Previous
		<span class="mx-2">|</span>
		<kbd class="px-1 bg-muted rounded">F</kbd> Fullscreen
		<span class="mx-2">|</span>
		<kbd class="px-1 bg-muted rounded">P</kbd> Pause
		<span class="mx-2">|</span>
		<kbd class="px-1 bg-muted rounded">Esc</kbd> Exit
	</div>
</div>
