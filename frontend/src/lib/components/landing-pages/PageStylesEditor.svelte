<script lang="ts">
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import type { PageStyles } from '$lib/api/landing-pages';

	interface Props {
		styles: PageStyles;
		onChange: (styles: PageStyles) => void;
	}

	let { styles, onChange }: Props = $props();

	function updateStyle<K extends keyof PageStyles>(key: K, value: PageStyles[K]) {
		onChange({ ...styles, [key]: value });
	}
</script>

<div class="flex h-full flex-col">
	<div class="border-b p-3">
		<h3 class="text-sm font-medium">Page Styles</h3>
		<p class="text-muted-foreground text-xs">Global styling for the page</p>
	</div>

	<ScrollArea class="flex-1">
		<div class="space-y-6 p-3">
			<!-- Colors -->
			<div>
				<h4 class="text-muted-foreground mb-3 text-xs font-medium uppercase tracking-wide">
					Colors
				</h4>
				<div class="space-y-3">
					<div>
						<Label for="primary-color">Primary Color</Label>
						<div class="flex gap-2">
							<Input
								id="primary-color"
								type="color"
								class="h-10 w-14 p-1"
								value={styles.primary_color || '#3b82f6'}
								onchange={(e) => updateStyle('primary_color', e.currentTarget.value)}
							/>
							<Input
								value={styles.primary_color || '#3b82f6'}
								oninput={(e) => updateStyle('primary_color', e.currentTarget.value)}
							/>
						</div>
					</div>

					<div>
						<Label for="secondary-color">Secondary Color</Label>
						<div class="flex gap-2">
							<Input
								id="secondary-color"
								type="color"
								class="h-10 w-14 p-1"
								value={styles.secondary_color || '#6b7280'}
								onchange={(e) => updateStyle('secondary_color', e.currentTarget.value)}
							/>
							<Input
								value={styles.secondary_color || '#6b7280'}
								oninput={(e) => updateStyle('secondary_color', e.currentTarget.value)}
							/>
						</div>
					</div>

					<div>
						<Label for="accent-color">Accent Color</Label>
						<div class="flex gap-2">
							<Input
								id="accent-color"
								type="color"
								class="h-10 w-14 p-1"
								value={styles.accent_color || '#10b981'}
								onchange={(e) => updateStyle('accent_color', e.currentTarget.value)}
							/>
							<Input
								value={styles.accent_color || '#10b981'}
								oninput={(e) => updateStyle('accent_color', e.currentTarget.value)}
							/>
						</div>
					</div>

					<div>
						<Label for="text-color">Text Color</Label>
						<div class="flex gap-2">
							<Input
								id="text-color"
								type="color"
								class="h-10 w-14 p-1"
								value={styles.text_color || '#1f2937'}
								onchange={(e) => updateStyle('text_color', e.currentTarget.value)}
							/>
							<Input
								value={styles.text_color || '#1f2937'}
								oninput={(e) => updateStyle('text_color', e.currentTarget.value)}
							/>
						</div>
					</div>
				</div>
			</div>

			<!-- Typography -->
			<div>
				<h4 class="text-muted-foreground mb-3 text-xs font-medium uppercase tracking-wide">
					Typography
				</h4>
				<div class="space-y-3">
					<div>
						<Label for="heading-font">Heading Font</Label>
						<Input
							id="heading-font"
							value={styles.heading_font || 'Inter, sans-serif'}
							oninput={(e) => updateStyle('heading_font', e.currentTarget.value)}
							placeholder="Inter, sans-serif"
						/>
					</div>
					<div>
						<Label for="body-font">Body Font</Label>
						<Input
							id="body-font"
							value={styles.body_font || 'Inter, sans-serif'}
							oninput={(e) => updateStyle('body_font', e.currentTarget.value)}
							placeholder="Inter, sans-serif"
						/>
					</div>
				</div>
			</div>

			<!-- Custom CSS -->
			<div>
				<h4 class="text-muted-foreground mb-3 text-xs font-medium uppercase tracking-wide">
					Custom CSS
				</h4>
				<Textarea
					value={styles.custom_css || ''}
					oninput={(e) => updateStyle('custom_css', e.currentTarget.value)}
					rows={8}
					placeholder="/* Add custom CSS here */"
					class="font-mono text-sm"
				/>
			</div>
		</div>
	</ScrollArea>
</div>
