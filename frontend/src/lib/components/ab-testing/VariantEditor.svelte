<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import { Copy, Eye, Save, Sparkles } from 'lucide-svelte';
	import type { AbTestVariant, AbTestType } from '$lib/api/ab-tests';

	interface Props {
		variant: AbTestVariant;
		testType: AbTestType;
		onSave: (content: Record<string, unknown>) => void;
		onPreview?: () => void;
	}

	let { variant, testType, onSave, onPreview }: Props = $props();

	let content = $state<Record<string, unknown>>({ ...variant.content });
	let hasChanges = $state(false);

	interface EditorField {
		key: string;
		label: string;
		type: 'input' | 'textarea' | 'color' | 'number' | 'select';
		placeholder?: string;
		options?: string[];
		min?: number;
		max?: number;
	}

	// Different editor views based on test type
	const editorConfig = $derived.by((): { fields: EditorField[] } => {
		switch (testType) {
			case 'email_subject':
				return {
					fields: [
						{ key: 'subject', label: 'Subject Line', type: 'input', placeholder: 'Enter email subject...' },
						{ key: 'preheader', label: 'Preheader Text', type: 'input', placeholder: 'Preview text...' }
					]
				};
			case 'email_content':
				return {
					fields: [
						{ key: 'body_html', label: 'Email Body (HTML)', type: 'textarea', placeholder: 'HTML content...' },
						{ key: 'body_text', label: 'Plain Text Version', type: 'textarea', placeholder: 'Plain text...' }
					]
				};
			case 'cta_button':
				return {
					fields: [
						{ key: 'text', label: 'Button Text', type: 'input', placeholder: 'Click here' },
						{ key: 'color', label: 'Button Color', type: 'color' },
						{ key: 'size', label: 'Button Size', type: 'select', options: ['small', 'medium', 'large'] },
						{ key: 'url', label: 'Link URL', type: 'input', placeholder: 'https://...' }
					]
				};
			case 'send_time':
				return {
					fields: [
						{ key: 'hour', label: 'Send Hour (0-23)', type: 'number', min: 0, max: 23 },
						{ key: 'day_of_week', label: 'Day of Week', type: 'select', options: ['any', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] },
						{ key: 'timezone', label: 'Timezone', type: 'input', placeholder: 'UTC' }
					]
				};
			case 'form_layout':
				return {
					fields: [
						{ key: 'layout', label: 'Layout Type', type: 'select', options: ['single-column', 'two-column', 'inline'] },
						{ key: 'field_order', label: 'Field Order (comma-separated)', type: 'input', placeholder: 'email, name, company' },
						{ key: 'submit_text', label: 'Submit Button Text', type: 'input', placeholder: 'Submit' }
					]
				};
			default:
				return { fields: [] };
		}
	});

	function updateContent(key: string, value: unknown) {
		content = { ...content, [key]: value };
		hasChanges = true;
	}

	function handleSave() {
		onSave(content);
		hasChanges = false;
	}

	function copyFromControl() {
		// This would copy content from control variant
		// Implementation depends on parent component
	}
</script>

<Card.Root>
	<Card.Header>
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<Card.Title>{variant.name}</Card.Title>
				{#if variant.is_control}
					<Badge variant="secondary">Control</Badge>
				{/if}
				{#if variant.is_winner}
					<Badge class="bg-green-100 text-green-800">Winner</Badge>
				{/if}
			</div>
			<div class="flex items-center gap-2">
				{#if !variant.is_control}
					<Button variant="ghost" size="sm" onclick={copyFromControl}>
						<Copy class="mr-2 h-4 w-4" />
						Copy from Control
					</Button>
				{/if}
				{#if onPreview}
					<Button variant="outline" size="sm" onclick={onPreview}>
						<Eye class="mr-2 h-4 w-4" />
						Preview
					</Button>
				{/if}
			</div>
		</div>
		<Card.Description>
			Variant Code: {variant.variant_code} | Traffic: {variant.traffic_percentage}%
		</Card.Description>
	</Card.Header>
	<Card.Content class="space-y-4">
		<Tabs.Root value="editor">
			<Tabs.List>
				<Tabs.Trigger value="editor">Editor</Tabs.Trigger>
				<Tabs.Trigger value="json">JSON</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="editor" class="space-y-4 pt-4">
				{#each editorConfig.fields as field}
					<div class="space-y-2">
						<Label for={field.key}>{field.label}</Label>

						{#if field.type === 'input'}
							<Input
								id={field.key}
								value={(content[field.key] as string) || ''}
								oninput={(e) => updateContent(field.key, e.currentTarget.value)}
								placeholder={field.placeholder}
							/>
						{:else if field.type === 'textarea'}
							<Textarea
								id={field.key}
								value={(content[field.key] as string) || ''}
								oninput={(e) => updateContent(field.key, e.currentTarget.value)}
								placeholder={field.placeholder}
								rows={6}
							/>
						{:else if field.type === 'color'}
							<div class="flex items-center gap-2">
								<input
									type="color"
									id={field.key}
									value={(content[field.key] as string) || '#000000'}
									oninput={(e) => updateContent(field.key, e.currentTarget.value)}
									class="h-10 w-20 cursor-pointer rounded border"
								/>
								<Input
									value={(content[field.key] as string) || '#000000'}
									oninput={(e) => updateContent(field.key, e.currentTarget.value)}
									class="w-32"
								/>
							</div>
						{:else if field.type === 'number'}
							<Input
								id={field.key}
								type="number"
								value={(content[field.key] as number) || 0}
								oninput={(e) => updateContent(field.key, parseInt(e.currentTarget.value))}
								min={field.min}
								max={field.max}
							/>
						{:else if field.type === 'select'}
							<select
								id={field.key}
								value={(content[field.key] as string) || ''}
								onchange={(e) => updateContent(field.key, e.currentTarget.value)}
								class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
							>
								<option value="">Select...</option>
								{#each field.options || [] as option}
									<option value={option}>{option}</option>
								{/each}
							</select>
						{/if}
					</div>
				{/each}

				{#if editorConfig.fields.length === 0}
					<p class="text-muted-foreground">
						No specific editor available for this test type. Use the JSON editor.
					</p>
				{/if}
			</Tabs.Content>

			<Tabs.Content value="json" class="pt-4">
				<Textarea
					value={JSON.stringify(content, null, 2)}
					oninput={(e) => {
						try {
							content = JSON.parse(e.currentTarget.value);
							hasChanges = true;
						} catch {
							// Invalid JSON, ignore
						}
					}}
					rows={12}
					class="font-mono text-sm"
				/>
			</Tabs.Content>
		</Tabs.Root>
	</Card.Content>
	<Card.Footer class="flex justify-between">
		<div class="flex items-center gap-2">
			<Switch id={`active-${variant.id}`} checked={variant.is_active} disabled />
			<Label for={`active-${variant.id}`} class="text-muted-foreground">Active</Label>
		</div>
		<Button onclick={handleSave} disabled={!hasChanges}>
			<Save class="mr-2 h-4 w-4" />
			Save Changes
		</Button>
	</Card.Footer>
</Card.Root>
