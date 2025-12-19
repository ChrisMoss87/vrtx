<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Trash2, ChevronUp, ChevronDown, Copy } from 'lucide-svelte';
	import type { PageElement } from '$lib/api/landing-pages';

	interface Props {
		element: PageElement | null;
		onUpdate: (id: string, props: Record<string, unknown>) => void;
		onDelete: (id: string) => void;
		onMoveUp: (id: string) => void;
		onMoveDown: (id: string) => void;
		onDuplicate: (id: string) => void;
	}

	let { element, onUpdate, onDelete, onMoveUp, onMoveDown, onDuplicate }: Props = $props();

	function updateProp(key: string, value: unknown) {
		if (!element) return;
		onUpdate(element.id, { ...element.props, [key]: value });
	}

	function getStringProp(key: string, fallback: string = ''): string {
		if (!element) return fallback;
		return (element.props[key] as string) || fallback;
	}

	function getBooleanProp(key: string, fallback: boolean = false): boolean {
		if (!element) return fallback;
		return (element.props[key] as boolean) || fallback;
	}
</script>

<div class="flex h-full flex-col">
	<div class="border-b p-3">
		<h3 class="text-sm font-medium">Element Settings</h3>
	</div>

	{#if element}
		<ScrollArea class="flex-1">
			<div class="space-y-4 p-3">
				<!-- Element type badge -->
				<div class="bg-muted rounded-md px-3 py-2">
					<span class="text-muted-foreground text-xs uppercase tracking-wide">{element.type}</span>
				</div>

				<!-- Actions -->
				<div class="flex gap-1">
					<Button variant="outline" size="sm" onclick={() => onMoveUp(element.id)}>
						<ChevronUp class="h-4 w-4" />
					</Button>
					<Button variant="outline" size="sm" onclick={() => onMoveDown(element.id)}>
						<ChevronDown class="h-4 w-4" />
					</Button>
					<Button variant="outline" size="sm" onclick={() => onDuplicate(element.id)}>
						<Copy class="h-4 w-4" />
					</Button>
					<Button variant="destructive" size="sm" onclick={() => onDelete(element.id)}>
						<Trash2 class="h-4 w-4" />
					</Button>
				</div>

				<!-- Type-specific fields -->
				{#if element.type === 'section'}
					<div class="space-y-3">
						<div>
							<Label for="bg-color">Background Color</Label>
							<Input
								id="bg-color"
								type="color"
								value={getStringProp('backgroundColor', '#ffffff')}
								onchange={(e) => updateProp('backgroundColor', e.currentTarget.value)}
							/>
						</div>
						<div>
							<Label for="padding">Padding</Label>
							<Input
								id="padding"
								value={getStringProp('padding', '60px 20px')}
								oninput={(e) => updateProp('padding', e.currentTarget.value)}
							/>
						</div>
					</div>
				{:else if element.type === 'container'}
					<div>
						<Label for="max-width">Max Width</Label>
						<Input
							id="max-width"
							value={getStringProp('maxWidth', '1200px')}
							oninput={(e) => updateProp('maxWidth', e.currentTarget.value)}
						/>
					</div>
				{:else if element.type === 'hero'}
					<div class="space-y-3">
						<div>
							<Label for="title">Title</Label>
							<Input
								id="title"
								value={getStringProp('title')}
								oninput={(e) => updateProp('title', e.currentTarget.value)}
							/>
						</div>
						<div>
							<Label for="subtitle">Subtitle</Label>
							<Textarea
								id="subtitle"
								value={getStringProp('subtitle')}
								oninput={(e) => updateProp('subtitle', e.currentTarget.value)}
							/>
						</div>
						<div>
							<Label for="cta-text">Button Text</Label>
							<Input
								id="cta-text"
								value={getStringProp('ctaText')}
								oninput={(e) => updateProp('ctaText', e.currentTarget.value)}
							/>
						</div>
					</div>
				{:else if element.type === 'heading'}
					<div class="space-y-3">
						<div>
							<Label for="text">Text</Label>
							<Input
								id="text"
								value={getStringProp('text')}
								oninput={(e) => updateProp('text', e.currentTarget.value)}
							/>
						</div>
						<div>
							<Label>Level</Label>
							<Select.Root
								type="single"
								value={getStringProp('level', 'h2')}
								onValueChange={(v) => v && updateProp('level', v)}
							>
								<Select.Trigger class="w-full">
									{getStringProp('level', 'h2').toUpperCase()}
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="h1">H1</Select.Item>
									<Select.Item value="h2">H2</Select.Item>
									<Select.Item value="h3">H3</Select.Item>
									<Select.Item value="h4">H4</Select.Item>
								</Select.Content>
							</Select.Root>
						</div>
					</div>
				{:else if element.type === 'text'}
					<div>
						<Label for="text">Text</Label>
						<Textarea
							id="text"
							value={getStringProp('text')}
							rows={6}
							oninput={(e) => updateProp('text', e.currentTarget.value)}
						/>
					</div>
				{:else if element.type === 'image'}
					<div class="space-y-3">
						<div>
							<Label for="src">Image URL</Label>
							<Input
								id="src"
								type="url"
								value={getStringProp('src')}
								oninput={(e) => updateProp('src', e.currentTarget.value)}
							/>
						</div>
						<div>
							<Label for="alt">Alt Text</Label>
							<Input
								id="alt"
								value={getStringProp('alt')}
								oninput={(e) => updateProp('alt', e.currentTarget.value)}
							/>
						</div>
					</div>
				{:else if element.type === 'video'}
					<div class="space-y-3">
						<div>
							<Label for="src">Video URL</Label>
							<Input
								id="src"
								type="url"
								value={getStringProp('src')}
								oninput={(e) => updateProp('src', e.currentTarget.value)}
							/>
						</div>
						<div class="flex items-center gap-2">
							<input
								type="checkbox"
								id="autoplay"
								checked={getBooleanProp('autoplay')}
								onchange={(e) => updateProp('autoplay', e.currentTarget.checked)}
							/>
							<Label for="autoplay">Autoplay</Label>
						</div>
					</div>
				{:else if element.type === 'button'}
					<div class="space-y-3">
						<div>
							<Label for="text">Button Text</Label>
							<Input
								id="text"
								value={getStringProp('text')}
								oninput={(e) => updateProp('text', e.currentTarget.value)}
							/>
						</div>
						<div>
							<Label>Variant</Label>
							<Select.Root
								type="single"
								value={getStringProp('variant', 'primary')}
								onValueChange={(v) => v && updateProp('variant', v)}
							>
								<Select.Trigger class="w-full">
									{getStringProp('variant', 'primary')}
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="primary">Primary</Select.Item>
									<Select.Item value="secondary">Secondary</Select.Item>
								</Select.Content>
							</Select.Root>
						</div>
					</div>
				{:else if element.type === 'cta'}
					<div class="space-y-3">
						<div>
							<Label for="title">Title</Label>
							<Input
								id="title"
								value={getStringProp('title')}
								oninput={(e) => updateProp('title', e.currentTarget.value)}
							/>
						</div>
						<div>
							<Label for="button-text">Button Text</Label>
							<Input
								id="button-text"
								value={getStringProp('buttonText')}
								oninput={(e) => updateProp('buttonText', e.currentTarget.value)}
							/>
						</div>
					</div>
				{:else if element.type === 'form'}
					<div>
						<Label for="form-id">Form ID</Label>
						<Input
							id="form-id"
							type="number"
							value={getStringProp('formId')}
							oninput={(e) => updateProp('formId', e.currentTarget.value ? parseInt(e.currentTarget.value) : null)}
						/>
						<p class="text-muted-foreground mt-1 text-xs">Enter the ID of an existing web form</p>
					</div>
				{:else if element.type === 'footer'}
					<div>
						<Label for="copyright">Copyright Text</Label>
						<Input
							id="copyright"
							value={getStringProp('copyright')}
							oninput={(e) => updateProp('copyright', e.currentTarget.value)}
						/>
					</div>
				{:else if element.type === 'divider'}
					<div>
						<Label>Style</Label>
						<Select.Root
							type="single"
							value={getStringProp('style', 'solid')}
							onValueChange={(v) => v && updateProp('style', v)}
						>
							<Select.Trigger class="w-full">
								{getStringProp('style', 'solid')}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="solid">Solid</Select.Item>
								<Select.Item value="dashed">Dashed</Select.Item>
								<Select.Item value="dotted">Dotted</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>
				{:else if element.type === 'spacer'}
					<div>
						<Label for="height">Height</Label>
						<Input
							id="height"
							value={getStringProp('height', '40px')}
							oninput={(e) => updateProp('height', e.currentTarget.value)}
						/>
					</div>
				{:else}
					<p class="text-muted-foreground text-sm">
						Configure this element's content in the items section below.
					</p>
				{/if}
			</div>
		</ScrollArea>
	{:else}
		<div class="flex flex-1 items-center justify-center p-4">
			<p class="text-muted-foreground text-center text-sm">
				Select an element to edit its properties
			</p>
		</div>
	{/if}
</div>
