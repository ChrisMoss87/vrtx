<script lang="ts">
	import type { CardStyle } from '$lib/types/kanban-card-config';
	import { PRESET_THEMES } from '$lib/types/kanban-card-config';
	import ColorPicker from './ColorPicker.svelte';
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { cn } from '$lib/utils';
	import { Palette, RotateCcw } from 'lucide-svelte';

	interface Props {
		style: CardStyle;
		onchange: (style: CardStyle) => void;
		showPresets?: boolean;
		class?: string;
	}

	let { style, onchange, showPresets = true, class: className }: Props = $props();

	function updateStyle(updates: Partial<CardStyle>) {
		onchange({ ...style, ...updates });
	}

	function applyPreset(presetConfig: Partial<CardStyle>) {
		onchange({ ...style, ...presetConfig });
	}

	function resetToDefaults() {
		onchange({
			backgroundColor: '#ffffff',
			borderColor: '#e5e7eb',
			accentColor: '#3b82f6',
			accentWidth: 3,
			titleColor: '#111827',
			subtitleColor: '#6b7280',
			textColor: '#374151'
		});
	}
</script>

<div class={cn('space-y-6', className)}>
	{#if showPresets}
		<!-- Preset themes -->
		<div class="space-y-3">
			<div class="flex items-center justify-between">
				<div>
					<h4 class="text-sm font-semibold">Preset Themes</h4>
					<p class="text-xs text-muted-foreground">Quick start with a pre-designed theme</p>
				</div>
				<Button size="sm" variant="ghost" onclick={resetToDefaults} class="gap-2">
					<RotateCcw class="h-3 w-3" />
					Reset
				</Button>
			</div>
			<div class="grid grid-cols-2 gap-2">
				{#each PRESET_THEMES as theme}
					<button
						type="button"
						onclick={() => applyPreset(theme.config.default || {})}
						class="group relative rounded-lg border p-3 text-left transition-all hover:border-primary hover:bg-accent/5"
					>
						<div class="flex items-center gap-2 mb-2">
							<Palette class="h-4 w-4 text-muted-foreground" />
							<span class="font-medium text-sm">{theme.name}</span>
						</div>
						<p class="text-xs text-muted-foreground mb-2">{theme.description}</p>
						<div class="flex gap-1">
							{#if theme.config.default}
								<div
									class="h-4 w-4 rounded border"
									style="background-color: {theme.config.default.backgroundColor}"
								></div>
								<div
									class="h-4 w-4 rounded border"
									style="background-color: {theme.config.default.accentColor}"
								></div>
								<div
									class="h-4 w-4 rounded border"
									style="background-color: {theme.config.default.titleColor}"
								></div>
							{/if}
						</div>
					</button>
				{/each}
			</div>
		</div>

		<div class="relative">
			<div class="absolute inset-0 flex items-center">
				<span class="w-full border-t"></span>
			</div>
			<div class="relative flex justify-center text-xs uppercase">
				<span class="bg-background px-2 text-muted-foreground">Or customize</span>
			</div>
		</div>
	{/if}

	<!-- Card colors -->
	<div class="space-y-4">
		<div>
			<h4 class="text-sm font-semibold mb-3">Card Colors</h4>
			<div class="grid grid-cols-2 gap-4">
				<ColorPicker
					label="Background"
					value={style.backgroundColor}
					onchange={(val) => updateStyle({ backgroundColor: val })}
				/>
				<ColorPicker
					label="Border"
					value={style.borderColor}
					onchange={(val) => updateStyle({ borderColor: val })}
				/>
			</div>
		</div>

		<div>
			<h4 class="text-sm font-semibold mb-3">Accent Strip</h4>
			<div class="grid grid-cols-2 gap-4">
				<ColorPicker
					label="Accent Color"
					value={style.accentColor}
					onchange={(val) => updateStyle({ accentColor: val })}
				/>
				<div class="space-y-2">
					<Label>Width (px)</Label>
					<Input
						type="number"
						min="0"
						max="10"
						value={style.accentWidth || 3}
						oninput={(e) =>
							updateStyle({ accentWidth: parseInt((e.target as HTMLInputElement).value) })}
						class="h-10"
					/>
				</div>
			</div>
			<p class="text-xs text-muted-foreground mt-2">
				Set width to 0 to hide the accent strip
			</p>
		</div>
	</div>

	<!-- Text colors -->
	<div class="space-y-4">
		<h4 class="text-sm font-semibold">Text Colors</h4>
		<div class="grid grid-cols-2 gap-4">
			<ColorPicker
				label="Title"
				value={style.titleColor}
				onchange={(val) => updateStyle({ titleColor: val })}
			/>
			<ColorPicker
				label="Subtitle"
				value={style.subtitleColor}
				onchange={(val) => updateStyle({ subtitleColor: val })}
			/>
		</div>
		<div class="grid grid-cols-2 gap-4">
			<ColorPicker
				label="Body Text"
				value={style.textColor}
				onchange={(val) => updateStyle({ textColor: val })}
			/>
		</div>
	</div>

	<!-- Color guide -->
	<div class="rounded-lg bg-muted/50 border p-3">
		<h5 class="text-xs font-semibold mb-2">Color Tips</h5>
		<ul class="text-xs text-muted-foreground space-y-1">
			<li>Use contrasting colors for text to ensure readability</li>
			<li>Accent strip helps differentiate card types at a glance</li>
			<li>Keep background colors light for better text visibility</li>
			<li>Match your brand colors for a cohesive look</li>
		</ul>
	</div>
</div>
