<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import {
		type WebForm,
		type WebFormField,
		type ModuleForForm,
		type WebFormStyling,
		type WebFormThankYouConfig,
		type WebFormSpamProtection,
		type WebFormSettings,
		type WebFormData,
		FIELD_TYPES,
		getDefaultStyling,
		getDefaultThankYouConfig,
		getDefaultSettings,
		createFormField
	} from '$lib/api/web-forms';
	import WebFormFieldEditor from './WebFormFieldEditor.svelte';
	import WebFormPreview from './WebFormPreview.svelte';
	import WebFormStyler from './WebFormStyler.svelte';

	interface Props {
		form?: WebForm | null;
		modules: ModuleForForm[];
		onSave: (data: WebFormData) => void | Promise<void>;
		onCancel: () => void;
		saving?: boolean;
	}

	let { form = null, modules, onSave, onCancel, saving = false }: Props = $props();

	// Form state
	let name = $state(form?.name ?? '');
	let slug = $state(form?.slug ?? '');
	let description = $state(form?.description ?? '');
	let moduleId = $state<number | undefined>(form?.module?.id);
	let isActive = $state(form?.is_active ?? true);
	let settings = $state<WebFormSettings>(form?.settings ?? getDefaultSettings());
	let styling = $state<WebFormStyling>(form?.styling ?? getDefaultStyling());
	let thankYouConfig = $state<WebFormThankYouConfig>(
		form?.thank_you_config ?? getDefaultThankYouConfig()
	);
	let spamProtection = $state<WebFormSpamProtection>(form?.spam_protection ?? {});
	let fields = $state<WebFormField[]>(form?.fields ?? []);

	let activeTab = $state('fields');
	let selectedFieldIndex = $state<number | null>(null);

	// Get selected module
	const selectedModule = $derived(modules.find((m) => m.id === moduleId));

	// Add new field
	function addField(fieldType: string) {
		const newField = createFormField(fieldType, fields.length);
		fields = [...fields, newField];
		selectedFieldIndex = fields.length - 1;
	}

	// Remove field
	function removeField(index: number) {
		fields = fields.filter((_, i) => i !== index);
		if (selectedFieldIndex === index) {
			selectedFieldIndex = null;
		} else if (selectedFieldIndex !== null && selectedFieldIndex > index) {
			selectedFieldIndex--;
		}
	}

	// Move field
	function moveField(index: number, direction: 'up' | 'down') {
		const newIndex = direction === 'up' ? index - 1 : index + 1;
		if (newIndex < 0 || newIndex >= fields.length) return;

		const newFields = [...fields];
		[newFields[index], newFields[newIndex]] = [newFields[newIndex], newFields[index]];

		// Update display_order
		newFields.forEach((f, i) => {
			f.display_order = i;
		});

		fields = newFields;

		if (selectedFieldIndex === index) {
			selectedFieldIndex = newIndex;
		} else if (selectedFieldIndex === newIndex) {
			selectedFieldIndex = index;
		}
	}

	// Update field
	function updateField(index: number, updates: Partial<WebFormField>) {
		fields = fields.map((f, i) => (i === index ? { ...f, ...updates } : f));
	}

	// Handle save
	function handleSave() {
		if (!name || !moduleId) return;

		const data: WebFormData = {
			name,
			slug: slug || undefined,
			description: description || undefined,
			module_id: moduleId,
			is_active: isActive,
			settings,
			styling,
			thank_you_config: thankYouConfig,
			spam_protection: spamProtection,
			fields: fields.map((f) => ({
				field_type: f.field_type,
				label: f.label,
				name: f.name,
				placeholder: f.placeholder,
				is_required: f.is_required,
				module_field_id: f.module_field_id,
				options: f.options,
				validation_rules: f.validation_rules,
				display_order: f.display_order,
				settings: f.settings
			}))
		};

		onSave(data);
	}
</script>

<div class="grid h-full grid-cols-1 gap-6 lg:grid-cols-2">
	<!-- Left side: Form Builder -->
	<div class="flex flex-col gap-4 overflow-y-auto">
		<!-- Basic Info -->
		<Card.Root>
			<Card.Header>
				<Card.Title>Form Details</Card.Title>
			</Card.Header>
			<Card.Content class="space-y-4">
				<div class="grid gap-4 sm:grid-cols-2">
					<div class="space-y-2">
						<Label for="name">Form Name *</Label>
						<Input id="name" bind:value={name} placeholder="Contact Us" />
					</div>
					<div class="space-y-2">
						<Label for="slug">URL Slug</Label>
						<Input id="slug" bind:value={slug} placeholder="contact-us" />
						<p class="text-xs text-muted-foreground">Leave blank to auto-generate</p>
					</div>
				</div>

				<div class="space-y-2">
					<Label for="description">Description</Label>
					<Textarea
						id="description"
						bind:value={description}
						placeholder="A form for visitors to contact us"
						rows={2}
					/>
				</div>

				<div class="grid gap-4 sm:grid-cols-2">
					<div class="space-y-2">
						<Label for="module">Target Module *</Label>
						<Select.Root
							type="single"
							value={moduleId ? String(moduleId) : undefined}
							onValueChange={(v) => (moduleId = v ? parseInt(v) : undefined)}
						>
							<Select.Trigger id="module" class="w-full">
								{selectedModule?.name ?? 'Select module...'}
							</Select.Trigger>
							<Select.Content>
								{#each modules as module}
									<Select.Item value={String(module.id)}>{module.name}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
					<div class="flex items-center space-x-2 pt-6">
						<Switch id="is_active" bind:checked={isActive} />
						<Label for="is_active">Active</Label>
					</div>
				</div>
			</Card.Content>
		</Card.Root>

		<!-- Tabs for Fields, Settings, Styling -->
		<Tabs.Root bind:value={activeTab} class="flex-1">
			<Tabs.List class="grid w-full grid-cols-4">
				<Tabs.Trigger value="fields">Fields</Tabs.Trigger>
				<Tabs.Trigger value="settings">Settings</Tabs.Trigger>
				<Tabs.Trigger value="styling">Styling</Tabs.Trigger>
				<Tabs.Trigger value="spam">Spam</Tabs.Trigger>
			</Tabs.List>

			<!-- Fields Tab -->
			<Tabs.Content value="fields" class="mt-4 space-y-4">
				<!-- Add Field Buttons -->
				<Card.Root>
					<Card.Header class="py-3">
						<Card.Title class="text-sm">Add Field</Card.Title>
					</Card.Header>
					<Card.Content class="flex flex-wrap gap-2">
						{#each Object.entries(FIELD_TYPES) as [type, label]}
							<Button variant="outline" size="sm" onclick={() => addField(type)}>
								{label}
							</Button>
						{/each}
					</Card.Content>
				</Card.Root>

				<!-- Field List -->
				<div class="space-y-2">
					{#each fields as field, index}
						<Card.Root
							class="cursor-pointer transition-colors {selectedFieldIndex === index
								? 'ring-2 ring-primary'
								: 'hover:bg-muted/50'}"
							onclick={() => (selectedFieldIndex = index)}
						>
							<Card.Content class="flex items-center justify-between p-3">
								<div class="flex items-center gap-3">
									<span class="text-xs text-muted-foreground">{index + 1}</span>
									<div>
										<p class="font-medium">{field.label || '(No label)'}</p>
										<p class="text-xs text-muted-foreground">
											{FIELD_TYPES[field.field_type]}
											{field.is_required ? ' *' : ''}
										</p>
									</div>
								</div>
								<div class="flex items-center gap-1">
									<Button
										variant="ghost"
										size="sm"
										onclick={(e: MouseEvent) => {
											e.stopPropagation();
											moveField(index, 'up');
										}}
										disabled={index === 0}
									>
										<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
											<path d="M18 15l-6-6-6 6" stroke-width="2" />
										</svg>
									</Button>
									<Button
										variant="ghost"
										size="sm"
										onclick={(e: MouseEvent) => {
											e.stopPropagation();
											moveField(index, 'down');
										}}
										disabled={index === fields.length - 1}
									>
										<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
											<path d="M6 9l6 6 6-6" stroke-width="2" />
										</svg>
									</Button>
									<Button
										variant="ghost"
										size="sm"
										onclick={(e: MouseEvent) => {
											e.stopPropagation();
											removeField(index);
										}}
									>
										<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
											<path d="M6 18L18 6M6 6l12 12" stroke-width="2" />
										</svg>
									</Button>
								</div>
							</Card.Content>
						</Card.Root>
					{/each}

					{#if fields.length === 0}
						<div class="rounded-lg border border-dashed p-8 text-center">
							<p class="text-muted-foreground">No fields added yet</p>
							<p class="text-sm text-muted-foreground">Click a field type above to add one</p>
						</div>
					{/if}
				</div>

				<!-- Field Editor -->
				{#if selectedFieldIndex !== null && fields[selectedFieldIndex]}
					<WebFormFieldEditor
						field={fields[selectedFieldIndex]}
						moduleFields={selectedModule?.fields ?? []}
						onUpdate={(updates) => updateField(selectedFieldIndex!, updates)}
					/>
				{/if}
			</Tabs.Content>

			<!-- Settings Tab -->
			<Tabs.Content value="settings" class="mt-4">
				<Card.Root>
					<Card.Content class="space-y-4 pt-4">
						<div class="space-y-2">
							<Label for="submit_text">Submit Button Text</Label>
							<Input
								id="submit_text"
								value={settings.submit_button_text ?? 'Submit'}
								oninput={(e: Event) =>
									(settings = { ...settings, submit_button_text: (e.target as HTMLInputElement).value })}
							/>
						</div>

						<div class="space-y-2">
							<Label for="thank_you_type">After Submission</Label>
							<Select.Root
								type="single"
								value={thankYouConfig.type}
								onValueChange={(v) =>
									(thankYouConfig = { ...thankYouConfig, type: v as 'message' | 'redirect' })}
							>
								<Select.Trigger id="thank_you_type" class="w-full">
									{thankYouConfig.type === 'message' ? 'Show Message' : 'Redirect to URL'}
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="message">Show Message</Select.Item>
									<Select.Item value="redirect">Redirect to URL</Select.Item>
								</Select.Content>
							</Select.Root>
						</div>

						{#if thankYouConfig.type === 'message'}
							<div class="space-y-2">
								<Label for="success_message">Success Message</Label>
								<Textarea
									id="success_message"
									value={thankYouConfig.message}
									oninput={(e: Event) =>
										(thankYouConfig = {
											...thankYouConfig,
											message: (e.target as HTMLTextAreaElement).value
										})}
									rows={3}
								/>
							</div>
						{:else}
							<div class="space-y-2">
								<Label for="redirect_url">Redirect URL</Label>
								<Input
									id="redirect_url"
									value={thankYouConfig.redirect_url ?? ''}
									oninput={(e: Event) =>
										(thankYouConfig = {
											...thankYouConfig,
											redirect_url: (e.target as HTMLInputElement).value || null
										})}
									placeholder="https://example.com/thank-you"
								/>
							</div>
						{/if}

						<div class="space-y-2">
							<Label for="notification_email">Notification Email</Label>
							<Input
								id="notification_email"
								type="email"
								value={settings.notification_email ?? ''}
								oninput={(e: Event) =>
									(settings = {
										...settings,
										notification_email: (e.target as HTMLInputElement).value || undefined
									})}
								placeholder="admin@example.com"
							/>
							<p class="text-xs text-muted-foreground">
								Receive an email when someone submits the form
							</p>
						</div>

						<div class="space-y-2">
							<Label for="webhook_url">Webhook URL</Label>
							<Input
								id="webhook_url"
								value={settings.webhook_url ?? ''}
								oninput={(e: Event) =>
									(settings = {
										...settings,
										webhook_url: (e.target as HTMLInputElement).value || undefined
									})}
								placeholder="https://example.com/webhook"
							/>
							<p class="text-xs text-muted-foreground">Send submission data to an external URL</p>
						</div>
					</Card.Content>
				</Card.Root>
			</Tabs.Content>

			<!-- Styling Tab -->
			<Tabs.Content value="styling" class="mt-4">
				<WebFormStyler bind:styling />
			</Tabs.Content>

			<!-- Spam Protection Tab -->
			<Tabs.Content value="spam" class="mt-4">
				<Card.Root>
					<Card.Content class="space-y-4 pt-4">
						<div class="flex items-center justify-between">
							<div>
								<Label>Honeypot Field</Label>
								<p class="text-xs text-muted-foreground">
									Invisible field that catches bots
								</p>
							</div>
							<Switch
								checked={spamProtection.honeypot_enabled ?? false}
								onCheckedChange={(checked) =>
									(spamProtection = { ...spamProtection, honeypot_enabled: checked })}
							/>
						</div>

						<div class="flex items-center justify-between">
							<div>
								<Label>reCAPTCHA v3</Label>
								<p class="text-xs text-muted-foreground">
									Google's invisible spam protection
								</p>
							</div>
							<Switch
								checked={spamProtection.recaptcha_enabled ?? false}
								onCheckedChange={(checked) =>
									(spamProtection = { ...spamProtection, recaptcha_enabled: checked })}
							/>
						</div>

						{#if spamProtection.recaptcha_enabled}
							<div class="space-y-4 pl-4 border-l-2 border-muted">
								<div class="space-y-2">
									<Label for="recaptcha_site_key">Site Key</Label>
									<Input
										id="recaptcha_site_key"
										value={spamProtection.recaptcha_site_key ?? ''}
										oninput={(e: Event) =>
											(spamProtection = {
												...spamProtection,
												recaptcha_site_key: (e.target as HTMLInputElement).value || undefined
											})}
										placeholder="6Le..."
									/>
								</div>
								<div class="space-y-2">
									<Label for="recaptcha_secret_key">Secret Key</Label>
									<Input
										id="recaptcha_secret_key"
										type="password"
										value={spamProtection.recaptcha_secret_key ?? ''}
										oninput={(e: Event) =>
											(spamProtection = {
												...spamProtection,
												recaptcha_secret_key: (e.target as HTMLInputElement).value || undefined
											})}
										placeholder="6Le..."
									/>
								</div>
								<div class="space-y-2">
									<Label for="min_score">Minimum Score (0.0 - 1.0)</Label>
									<Input
										id="min_score"
										type="number"
										min="0"
										max="1"
										step="0.1"
										value={spamProtection.min_score ?? 0.5}
										oninput={(e: Event) =>
											(spamProtection = {
												...spamProtection,
												min_score: parseFloat((e.target as HTMLInputElement).value) || 0.5
											})}
									/>
								</div>
							</div>
						{/if}
					</Card.Content>
				</Card.Root>
			</Tabs.Content>
		</Tabs.Root>
	</div>

	<!-- Right side: Preview -->
	<div class="flex flex-col gap-4">
		<Card.Root class="flex-1">
			<Card.Header class="py-3">
				<Card.Title class="text-sm">Preview</Card.Title>
			</Card.Header>
			<Card.Content class="overflow-auto">
				<WebFormPreview {name} {fields} {styling} submitText={settings.submit_button_text} />
			</Card.Content>
		</Card.Root>

		<!-- Actions -->
		<div class="flex justify-end gap-2">
			<Button variant="outline" onclick={onCancel}>Cancel</Button>
			<Button onclick={handleSave} disabled={saving || !name || !moduleId}>
				{saving ? 'Saving...' : form ? 'Update Form' : 'Create Form'}
			</Button>
		</div>
	</div>
</div>
