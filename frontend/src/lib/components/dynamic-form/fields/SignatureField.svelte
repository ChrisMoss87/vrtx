<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Eraser, Check } from 'lucide-svelte';
	import type { FieldSettings } from '$lib/api/modules';
	import { cn } from '$lib/utils';
	import { onMount } from 'svelte';

	interface Props {
		value: string | null;
		error?: string;
		disabled?: boolean;
		required?: boolean;
		settings?: FieldSettings;
		onchange: (value: string | null) => void;
	}

	let {
		value = $bindable(null),
		error,
		disabled = false,
		required,
		settings,
		onchange
	}: Props = $props();

	const penColor = $derived((settings?.additional_settings?.penColor as string) ?? '#000000');
	const backgroundColor = $derived((settings?.additional_settings?.backgroundColor as string) ?? '#ffffff');

	let canvas: HTMLCanvasElement;
	let ctx: CanvasRenderingContext2D | null = null;
	let isDrawing = $state(false);
	let hasSignature = $state(!!value);

	onMount(() => {
		if (canvas) {
			ctx = canvas.getContext('2d');
			if (ctx) {
				ctx.strokeStyle = penColor;
				ctx.lineWidth = 2;
				ctx.lineCap = 'round';
				ctx.lineJoin = 'round';

				// Fill background
				ctx.fillStyle = backgroundColor;
				ctx.fillRect(0, 0, canvas.width, canvas.height);

				// Load existing signature if present
				if (value) {
					const img = new Image();
					img.onload = () => {
						ctx?.drawImage(img, 0, 0);
					};
					img.src = value;
				}
			}
		}
	});

	function getCoordinates(event: MouseEvent | TouchEvent): { x: number; y: number } | null {
		if (!canvas) return null;

		const rect = canvas.getBoundingClientRect();
		const scaleX = canvas.width / rect.width;
		const scaleY = canvas.height / rect.height;

		if ('touches' in event) {
			const touch = event.touches[0];
			if (!touch) return null;
			return {
				x: (touch.clientX - rect.left) * scaleX,
				y: (touch.clientY - rect.top) * scaleY
			};
		}

		return {
			x: (event.clientX - rect.left) * scaleX,
			y: (event.clientY - rect.top) * scaleY
		};
	}

	function startDrawing(event: MouseEvent | TouchEvent) {
		if (disabled || !ctx) return;
		event.preventDefault();

		isDrawing = true;
		const coords = getCoordinates(event);
		if (coords) {
			ctx.beginPath();
			ctx.moveTo(coords.x, coords.y);
		}
	}

	function draw(event: MouseEvent | TouchEvent) {
		if (!isDrawing || disabled || !ctx) return;
		event.preventDefault();

		const coords = getCoordinates(event);
		if (coords) {
			ctx.lineTo(coords.x, coords.y);
			ctx.stroke();
			hasSignature = true;
		}
	}

	function stopDrawing() {
		if (!isDrawing || !ctx) return;
		isDrawing = false;
		ctx.closePath();
	}

	function clearSignature() {
		if (!ctx || !canvas) return;
		ctx.fillStyle = backgroundColor;
		ctx.fillRect(0, 0, canvas.width, canvas.height);
		hasSignature = false;
		value = null;
		onchange(null);
	}

	function saveSignature() {
		if (!canvas || !hasSignature) return;
		const dataUrl = canvas.toDataURL('image/png');
		value = dataUrl;
		onchange(dataUrl);
	}
</script>

<div class={cn('space-y-2', disabled && 'opacity-50')}>
	<div
		class={cn(
			'relative overflow-hidden rounded-md border',
			error ? 'border-destructive' : 'border-input',
			disabled && 'pointer-events-none'
		)}
	>
		<canvas
			bind:this={canvas}
			width={400}
			height={150}
			class="h-[150px] w-full cursor-crosshair touch-none"
			style="background-color: {backgroundColor};"
			onmousedown={startDrawing}
			onmousemove={draw}
			onmouseup={stopDrawing}
			onmouseleave={stopDrawing}
			ontouchstart={startDrawing}
			ontouchmove={draw}
			ontouchend={stopDrawing}
		></canvas>

		{#if !hasSignature && !disabled}
			<div
				class="pointer-events-none absolute inset-0 flex items-center justify-center text-muted-foreground"
			>
				<span class="text-sm">Sign here</span>
			</div>
		{/if}
	</div>

	{#if !disabled}
		<div class="flex items-center gap-2">
			<Button
				type="button"
				variant="outline"
				size="sm"
				onclick={clearSignature}
				disabled={!hasSignature}
			>
				<Eraser class="mr-1 h-4 w-4" />
				Clear
			</Button>
			<Button
				type="button"
				variant="default"
				size="sm"
				onclick={saveSignature}
				disabled={!hasSignature}
			>
				<Check class="mr-1 h-4 w-4" />
				Save Signature
			</Button>
		</div>
	{/if}

	{#if value && disabled}
		<div class="text-xs text-muted-foreground">Signature captured</div>
	{/if}
</div>
