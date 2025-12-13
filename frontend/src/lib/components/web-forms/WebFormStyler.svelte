<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { type WebFormStyling, getDefaultStyling } from '$lib/api/web-forms';

	interface Props {
		styling: WebFormStyling;
	}

	let { styling = $bindable() }: Props = $props();

	const fontFamilies = [
		{ value: 'Inter, system-ui, sans-serif', label: 'Inter (Default)' },
		{ value: 'system-ui, sans-serif', label: 'System UI' },
		{ value: 'Arial, sans-serif', label: 'Arial' },
		{ value: 'Georgia, serif', label: 'Georgia' }
	];

	const fontSizes = [
		{ value: '12px', label: 'Small (12px)' },
		{ value: '14px', label: 'Default (14px)' },
		{ value: '16px', label: 'Medium (16px)' },
		{ value: '18px', label: 'Large (18px)' }
	];

	const borderRadii = [
		{ value: '0', label: 'None' },
		{ value: '4px', label: 'Small (4px)' },
		{ value: '8px', label: 'Default (8px)' },
		{ value: '12px', label: 'Large (12px)' }
	];

	const maxWidths = [
		{ value: '400px', label: 'Narrow (400px)' },
		{ value: '500px', label: 'Medium (500px)' },
		{ value: '600px', label: 'Default (600px)' },
		{ value: '700px', label: 'Wide (700px)' },
		{ value: '100%', label: 'Full Width' }
	];

	function updateStyling<K extends keyof WebFormStyling>(key: K, value: WebFormStyling[K]) {
		styling = { ...styling, [key]: value };
	}

	function resetToDefaults() {
		styling = getDefaultStyling();
	}
</script>

<Card.Root>
	<Card.Header class="flex flex-row items-center justify-between py-3">
		<Card.Title class="text-sm">Form Styling</Card.Title>
		<button class="text-xs text-muted-foreground hover:text-foreground" onclick={resetToDefaults}>
			Reset to Defaults
		</button>
	</Card.Header>
	<Card.Content class="space-y-6">
		<!-- Colors Section -->
		<div class="space-y-4">
			<h4 class="text-sm font-medium">Colors</h4>
			<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
				<div class="space-y-2">
					<Label for="bg_color" class="text-xs">Background</Label>
					<div class="flex gap-2">
						<input
							type="color"
							id="bg_color"
							value={styling.background_color}
							oninput={(e) => updateStyling('background_color', (e.target as HTMLInputElement).value)}
							class="h-9 w-9 cursor-pointer rounded border"
						/>
						<Input
							value={styling.background_color}
							oninput={(e) => updateStyling('background_color', (e.target as HTMLInputElement).value)}
							class="flex-1"
						/>
					</div>
				</div>

				<div class="space-y-2">
					<Label for="text_color" class="text-xs">Text</Label>
					<div class="flex gap-2">
						<input
							type="color"
							id="text_color"
							value={styling.text_color}
							oninput={(e) => updateStyling('text_color', (e.target as HTMLInputElement).value)}
							class="h-9 w-9 cursor-pointer rounded border"
						/>
						<Input
							value={styling.text_color}
							oninput={(e) => updateStyling('text_color', (e.target as HTMLInputElement).value)}
							class="flex-1"
						/>
					</div>
				</div>

				<div class="space-y-2">
					<Label for="label_color" class="text-xs">Labels</Label>
					<div class="flex gap-2">
						<input
							type="color"
							id="label_color"
							value={styling.label_color}
							oninput={(e) => updateStyling('label_color', (e.target as HTMLInputElement).value)}
							class="h-9 w-9 cursor-pointer rounded border"
						/>
						<Input
							value={styling.label_color}
							oninput={(e) => updateStyling('label_color', (e.target as HTMLInputElement).value)}
							class="flex-1"
						/>
					</div>
				</div>

				<div class="space-y-2">
					<Label for="primary_color" class="text-xs">Primary (Button)</Label>
					<div class="flex gap-2">
						<input
							type="color"
							id="primary_color"
							value={styling.primary_color}
							oninput={(e) => updateStyling('primary_color', (e.target as HTMLInputElement).value)}
							class="h-9 w-9 cursor-pointer rounded border"
						/>
						<Input
							value={styling.primary_color}
							oninput={(e) => updateStyling('primary_color', (e.target as HTMLInputElement).value)}
							class="flex-1"
						/>
					</div>
				</div>

				<div class="space-y-2">
					<Label for="border_color" class="text-xs">Border</Label>
					<div class="flex gap-2">
						<input
							type="color"
							id="border_color"
							value={styling.border_color}
							oninput={(e) => updateStyling('border_color', (e.target as HTMLInputElement).value)}
							class="h-9 w-9 cursor-pointer rounded border"
						/>
						<Input
							value={styling.border_color}
							oninput={(e) => updateStyling('border_color', (e.target as HTMLInputElement).value)}
							class="flex-1"
						/>
					</div>
				</div>
			</div>
		</div>

		<!-- Typography Section -->
		<div class="space-y-4">
			<h4 class="text-sm font-medium">Typography</h4>
			<div class="grid gap-4 sm:grid-cols-2">
				<div class="space-y-2">
					<Label for="font_family" class="text-xs">Font Family</Label>
					<Select.Root
						type="single"
						value={styling.font_family}
						onValueChange={(v) => updateStyling('font_family', v)}
					>
						<Select.Trigger id="font_family" class="w-full">
							{fontFamilies.find((f) => f.value === styling.font_family)?.label ?? 'Select font...'}
						</Select.Trigger>
						<Select.Content>
							{#each fontFamilies as font}
								<Select.Item value={font.value}>{font.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-2">
					<Label for="font_size" class="text-xs">Font Size</Label>
					<Select.Root
						type="single"
						value={styling.font_size}
						onValueChange={(v) => updateStyling('font_size', v)}
					>
						<Select.Trigger id="font_size" class="w-full">
							{fontSizes.find((f) => f.value === styling.font_size)?.label ?? 'Select size...'}
						</Select.Trigger>
						<Select.Content>
							{#each fontSizes as size}
								<Select.Item value={size.value}>{size.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>
		</div>

		<!-- Layout Section -->
		<div class="space-y-4">
			<h4 class="text-sm font-medium">Layout</h4>
			<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
				<div class="space-y-2">
					<Label for="border_radius" class="text-xs">Border Radius</Label>
					<Select.Root
						type="single"
						value={styling.border_radius}
						onValueChange={(v) => updateStyling('border_radius', v)}
					>
						<Select.Trigger id="border_radius" class="w-full">
							{borderRadii.find((r) => r.value === styling.border_radius)?.label ??
								styling.border_radius}
						</Select.Trigger>
						<Select.Content>
							{#each borderRadii as radius}
								<Select.Item value={radius.value}>{radius.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-2">
					<Label for="max_width" class="text-xs">Max Width</Label>
					<Select.Root
						type="single"
						value={styling.max_width}
						onValueChange={(v) => updateStyling('max_width', v)}
					>
						<Select.Trigger id="max_width" class="w-full">
							{maxWidths.find((w) => w.value === styling.max_width)?.label ?? styling.max_width}
						</Select.Trigger>
						<Select.Content>
							{#each maxWidths as width}
								<Select.Item value={width.value}>{width.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-2">
					<Label for="padding" class="text-xs">Padding</Label>
					<Input
						id="padding"
						value={styling.padding}
						oninput={(e) => updateStyling('padding', (e.target as HTMLInputElement).value)}
						placeholder="24px"
					/>
				</div>
			</div>
		</div>

		<!-- Custom CSS Section -->
		<div class="space-y-2">
			<Label for="custom_css" class="text-xs">Custom CSS</Label>
			<Textarea
				id="custom_css"
				value={styling.custom_css}
				oninput={(e) => updateStyling('custom_css', (e.target as HTMLTextAreaElement).value)}
				placeholder=".form-field &#123; margin-bottom: 1.5rem; &#125;"
				rows={4}
				class="font-mono text-xs"
			/>
			<p class="text-xs text-muted-foreground">
				Add custom CSS to further customize the form appearance
			</p>
		</div>
	</Card.Content>
</Card.Root>
