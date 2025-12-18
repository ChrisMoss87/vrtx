<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Popover from '$lib/components/ui/popover';
	import { cn } from '$lib/utils';

	interface Props {
		label: string;
		value?: string;
		onchange?: (value: string) => void;
		class?: string;
	}

	let { label, value = '#3b82f6', onchange, class: className }: Props = $props();

	// Common color palette
	const colorPalette = [
		'#6b7280', // gray
		'#ef4444', // red
		'#f97316', // orange
		'#f59e0b', // amber
		'#eab308', // yellow
		'#84cc16', // lime
		'#22c55e', // green
		'#10b981', // emerald
		'#14b8a6', // teal
		'#06b6d4', // cyan
		'#0ea5e9', // sky
		'#3b82f6', // blue
		'#6366f1', // indigo
		'#8b5cf6', // violet
		'#a855f7', // purple
		'#d946ef', // fuchsia
		'#ec4899', // pink
		'#f43f5e', // rose
		'#ffffff', // white
		'#000000'  // black
	];

	let open = $state(false);

	function handleColorSelect(color: string) {
		onchange?.(color);
		open = false;
	}

	function handleInputChange(e: Event) {
		const target = e.target as HTMLInputElement;
		onchange?.(target.value);
	}
</script>

<div class={cn('space-y-2', className)}>
	<Label>{label}</Label>
	<Popover.Root bind:open>
		<Popover.Trigger>
			{#snippet child({ props })}
				<button
					{...props}
					class="flex h-10 w-full items-center gap-3 rounded-md border border-input bg-background px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground"
					type="button"
				>
					<div
						class="h-6 w-6 rounded border border-border"
						style="background-color: {value}"
					></div>
					<span class="flex-1 text-left">{value}</span>
				</button>
			{/snippet}
		</Popover.Trigger>
		<Popover.Content class="w-auto p-3" align="start">
			<div class="space-y-3">
				<div class="grid grid-cols-5 gap-2">
					{#each colorPalette as color}
						<button
							type="button"
							class="h-8 w-8 rounded border hover:scale-110 transition-transform {value ===
							color
								? 'ring-2 ring-primary ring-offset-2'
								: ''}"
							style="background-color: {color}"
							onclick={() => handleColorSelect(color)}
						></button>
					{/each}
				</div>
				<div class="flex items-center gap-2">
					<Input
						type="color"
						value={value}
						oninput={handleInputChange}
						class="h-10 w-20 cursor-pointer"
					/>
					<Input
						type="text"
						value={value}
						oninput={handleInputChange}
						placeholder="#000000"
						class="flex-1 font-mono text-xs"
					/>
				</div>
			</div>
		</Popover.Content>
	</Popover.Root>
</div>
