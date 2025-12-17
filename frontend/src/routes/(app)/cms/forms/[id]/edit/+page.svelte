<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import {
		ArrowLeft,
		Save,
		Eye,
		Settings,
		Plus,
		Trash2,
		GripVertical,
		Copy,
		Type,
		Mail,
		Phone,
		FileText,
		List,
		CheckSquare,
		Circle,
		Calendar,
		Hash,
		Upload,
		Code
	} from 'lucide-svelte';
	import {
		cmsFormApi,
		type CmsForm,
		type FormField,
		type FormFieldType,
		type FormSubmitAction
	} from '$lib/api/cms';
	import { toast } from 'svelte-sonner';
	import { onMount } from 'svelte';

	const formId = parseInt($page.params.id);

	let loading = $state(true);
	let saving = $state(false);
	let formData = $state<CmsForm | null>(null);

	// Form state
	let name = $state('');
	let slug = $state('');
	let description = $state('');
	let fields = $state<FormField[]>([]);
	let submitAction = $state<FormSubmitAction>('create_lead');
	let submitButtonText = $state('Submit');
	let successMessage = $state('');
	let redirectUrl = $state('');
	let notificationEmails = $state<string[]>([]);
	let emailInput = $state('');
	let isActive = $state(true);

	let activeTab = $state('fields');
	let showFieldDialog = $state(false);
	let showEmbedDialog = $state(false);
	let editingFieldIndex = $state<number | null>(null);
	let embedCode = $state('');

	// New field state
	let newField = $state<FormField>({
		name: '',
		type: 'text',
		label: '',
		required: false,
		placeholder: ''
	});

	const fieldTypes: { value: FormFieldType; label: string; icon: typeof Type }[] = [
		{ value: 'text', label: 'Text', icon: Type },
		{ value: 'email', label: 'Email', icon: Mail },
		{ value: 'phone', label: 'Phone', icon: Phone },
		{ value: 'textarea', label: 'Text Area', icon: FileText },
		{ value: 'select', label: 'Dropdown', icon: List },
		{ value: 'checkbox', label: 'Checkbox', icon: CheckSquare },
		{ value: 'radio', label: 'Radio', icon: Circle },
		{ value: 'date', label: 'Date', icon: Calendar },
		{ value: 'number', label: 'Number', icon: Hash },
		{ value: 'file', label: 'File Upload', icon: Upload }
	];

	const submitActions: { value: FormSubmitAction; label: string }[] = [
		{ value: 'create_lead', label: 'Create Lead' },
		{ value: 'create_contact', label: 'Create Contact' },
		{ value: 'update_contact', label: 'Update Contact' },
		{ value: 'webhook', label: 'Send to Webhook' },
		{ value: 'email', label: 'Send Email Only' },
		{ value: 'custom', label: 'Custom Action' }
	];

	onMount(async () => {
		await loadForm();
	});

	async function loadForm() {
		loading = true;
		try {
			const form = await cmsFormApi.get(formId);
			formData = form;

			// Populate form state
			name = form.name;
			slug = form.slug;
			description = form.description || '';
			fields = form.fields;
			submitAction = form.submit_action;
			submitButtonText = form.submit_button_text;
			successMessage = form.success_message || '';
			redirectUrl = form.redirect_url || '';
			notificationEmails = form.notification_emails || [];
			isActive = form.is_active;
		} catch (error) {
			toast.error('Failed to load form');
			goto('/cms/forms');
		} finally {
			loading = false;
		}
	}

	function generateSlug(text: string): string {
		return text
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/(^-|-$)/g, '');
	}

	function handleNameChange() {
		if (!slug || slug === generateSlug(name.slice(0, -1))) {
			slug = generateSlug(name);
		}
	}

	function openAddFieldDialog() {
		editingFieldIndex = null;
		newField = {
			name: '',
			type: 'text',
			label: '',
			required: false,
			placeholder: ''
		};
		showFieldDialog = true;
	}

	function openEditFieldDialog(index: number) {
		editingFieldIndex = index;
		newField = { ...fields[index] };
		showFieldDialog = true;
	}

	function saveField() {
		if (!newField.label.trim()) {
			toast.error('Field label is required');
			return;
		}

		// Generate name from label if not provided
		if (!newField.name.trim()) {
			newField.name = newField.label
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '_')
				.replace(/(^_|_$)/g, '');
		}

		if (editingFieldIndex !== null) {
			fields = fields.map((f, i) => (i === editingFieldIndex ? { ...newField } : f));
		} else {
			fields = [...fields, { ...newField }];
		}

		showFieldDialog = false;
	}

	function removeField(index: number) {
		fields = fields.filter((_, i) => i !== index);
	}

	function duplicateField(index: number) {
		const field = fields[index];
		const newFieldCopy = {
			...field,
			name: `${field.name}_copy`,
			label: `${field.label} (Copy)`
		};
		fields = [...fields.slice(0, index + 1), newFieldCopy, ...fields.slice(index + 1)];
	}

	function moveField(from: number, to: number) {
		const newFields = [...fields];
		const [moved] = newFields.splice(from, 1);
		newFields.splice(to, 0, moved);
		fields = newFields;
	}

	function addEmail() {
		const email = emailInput.trim();
		if (email && !notificationEmails.includes(email)) {
			notificationEmails = [...notificationEmails, email];
		}
		emailInput = '';
	}

	function removeEmail(email: string) {
		notificationEmails = notificationEmails.filter((e) => e !== email);
	}

	function addOption() {
		if (!newField.options) {
			newField.options = [];
		}
		newField.options = [...newField.options, { label: '', value: '' }];
	}

	function removeOption(index: number) {
		if (newField.options) {
			newField.options = newField.options.filter((_, i) => i !== index);
		}
	}

	async function handleSave() {
		if (!name.trim()) {
			toast.error('Form name is required');
			return;
		}

		if (fields.length === 0) {
			toast.error('At least one field is required');
			return;
		}

		saving = true;
		try {
			await cmsFormApi.update(formId, {
				name: name.trim(),
				slug: slug.trim() || undefined,
				description: description.trim() || undefined,
				fields,
				submit_action: submitAction,
				submit_button_text: submitButtonText.trim() || 'Submit',
				success_message: successMessage.trim() || undefined,
				redirect_url: redirectUrl.trim() || undefined,
				notification_emails: notificationEmails.length > 0 ? notificationEmails : undefined,
				is_active: isActive
			});

			toast.success('Form saved');
			await loadForm();
		} catch (error) {
			toast.error('Failed to save form');
		} finally {
			saving = false;
		}
	}

	async function showEmbed() {
		try {
			const result = await cmsFormApi.getEmbedCode(formId);
			embedCode = result.embed_code;
			showEmbedDialog = true;
		} catch (error) {
			toast.error('Failed to get embed code');
		}
	}

	function getFieldIcon(type: FormFieldType) {
		return fieldTypes.find((f) => f.value === type)?.icon || Type;
	}
</script>

{#if loading}
	<div class="flex h-[50vh] items-center justify-center">
		<div class="text-muted-foreground">Loading...</div>
	</div>
{:else if formData}
	<div class="container py-6">
		<!-- Header -->
		<div class="mb-6 flex items-center justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="sm" href="/cms/forms">
					<ArrowLeft class="mr-1 h-4 w-4" />
					Back
				</Button>
				<div>
					<div class="flex items-center gap-2">
						<h1 class="text-2xl font-bold">{name || 'Untitled Form'}</h1>
						{#if isActive}
							<Badge class="bg-green-100 text-green-800">Active</Badge>
						{:else}
							<Badge variant="secondary">Inactive</Badge>
						{/if}
					</div>
					<p class="text-muted-foreground text-sm">
						{formData.submission_count.toLocaleString()} submissions
					</p>
				</div>
			</div>
			<div class="flex items-center gap-2">
				<Button variant="outline" onclick={showEmbed}>
					<Code class="mr-1 h-4 w-4" />
					Embed Code
				</Button>
				<Button variant="outline" disabled={saving}>
					<Eye class="mr-1 h-4 w-4" />
					Preview
				</Button>
				<Button onclick={handleSave} disabled={saving}>
					<Save class="mr-1 h-4 w-4" />
					{saving ? 'Saving...' : 'Save'}
				</Button>
			</div>
		</div>

		<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
			<!-- Main Content -->
			<div class="lg:col-span-2">
				<Tabs.Root bind:value={activeTab}>
					<Tabs.List class="mb-4">
						<Tabs.Trigger value="fields">Fields</Tabs.Trigger>
						<Tabs.Trigger value="settings">Settings</Tabs.Trigger>
					</Tabs.List>

					<Tabs.Content value="fields">
						<Card.Root>
							<Card.Header>
								<div class="flex items-center justify-between">
									<Card.Title>Form Fields</Card.Title>
									<Button size="sm" onclick={openAddFieldDialog}>
										<Plus class="mr-1 h-4 w-4" />
										Add Field
									</Button>
								</div>
							</Card.Header>
							<Card.Content>
								{#if fields.length === 0}
									<div class="rounded-lg border border-dashed bg-muted/30 p-8 text-center">
										<p class="text-muted-foreground mb-3">No fields yet</p>
										<Button variant="outline" onclick={openAddFieldDialog}>
											<Plus class="mr-1 h-4 w-4" />
											Add Field
										</Button>
									</div>
								{:else}
									<div class="space-y-2">
										{#each fields as field, index}
											<div class="group flex items-center gap-3 rounded-lg border bg-card p-3">
												<button
													type="button"
													class="cursor-grab text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
												>
													<GripVertical class="h-4 w-4" />
												</button>
												<div class="flex-1">
													<div class="flex items-center gap-2">
														<svelte:component
															this={getFieldIcon(field.type)}
															class="h-4 w-4 text-muted-foreground"
														/>
														<span class="font-medium">{field.label}</span>
														{#if field.required}
															<span class="text-red-500">*</span>
														{/if}
													</div>
													<div class="text-muted-foreground text-sm">
														<code class="text-xs">{field.name}</code> - {field.type}
													</div>
												</div>
												<div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
													<Button variant="ghost" size="sm" onclick={() => openEditFieldDialog(index)}>
														Edit
													</Button>
													<Button variant="ghost" size="sm" onclick={() => duplicateField(index)}>
														<Copy class="h-4 w-4" />
													</Button>
													<Button variant="ghost" size="sm" onclick={() => removeField(index)}>
														<Trash2 class="h-4 w-4" />
													</Button>
												</div>
											</div>
										{/each}
									</div>
								{/if}
							</Card.Content>
						</Card.Root>
					</Tabs.Content>

					<Tabs.Content value="settings">
						<Card.Root>
							<Card.Content class="space-y-6 pt-6">
								<div class="space-y-2">
									<Label for="name">Form Name</Label>
									<Input
										id="name"
										placeholder="Contact Form"
										bind:value={name}
										oninput={handleNameChange}
									/>
								</div>

								<div class="space-y-2">
									<Label for="slug">Slug</Label>
									<Input id="slug" placeholder="contact-form" bind:value={slug} />
								</div>

								<div class="space-y-2">
									<Label for="description">Description</Label>
									<Textarea
										id="description"
										placeholder="Brief description of the form..."
										bind:value={description}
										rows={3}
									/>
								</div>

								<div class="space-y-2">
									<Label>Submit Action</Label>
									<Select.Root
										type="single"
										value={submitAction}
										onValueChange={(val) => {
											if (val) submitAction = val as FormSubmitAction;
										}}
									>
										<Select.Trigger>
											<span>{submitActions.find((a) => a.value === submitAction)?.label}</span>
										</Select.Trigger>
										<Select.Content>
											{#each submitActions as action}
												<Select.Item value={action.value}>{action.label}</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>
								</div>

								<div class="space-y-2">
									<Label for="submitButtonText">Submit Button Text</Label>
									<Input id="submitButtonText" placeholder="Submit" bind:value={submitButtonText} />
								</div>

								<div class="space-y-2">
									<Label for="successMessage">Success Message</Label>
									<Textarea
										id="successMessage"
										placeholder="Thank you for your submission!"
										bind:value={successMessage}
										rows={2}
									/>
								</div>

								<div class="space-y-2">
									<Label for="redirectUrl">Redirect URL (optional)</Label>
									<Input
										id="redirectUrl"
										type="url"
										placeholder="https://example.com/thank-you"
										bind:value={redirectUrl}
									/>
								</div>

								<div class="space-y-3">
									<Label>Notification Emails</Label>
									<div class="flex gap-2">
										<Input
											placeholder="email@example.com"
											bind:value={emailInput}
											onkeydown={(e) => {
												if (e.key === 'Enter') {
													e.preventDefault();
													addEmail();
												}
											}}
										/>
										<Button variant="outline" onclick={addEmail}>Add</Button>
									</div>
									{#if notificationEmails.length > 0}
										<div class="flex flex-wrap gap-1">
											{#each notificationEmails as email}
												<span class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs">
													{email}
													<button
														type="button"
														onclick={() => removeEmail(email)}
														class="hover:text-destructive"
													>
														&times;
													</button>
												</span>
											{/each}
										</div>
									{/if}
								</div>

								<div class="flex items-center justify-between rounded-lg border p-4">
									<div>
										<Label>Active</Label>
										<p class="text-muted-foreground text-sm">Form can receive submissions</p>
									</div>
									<Switch bind:checked={isActive} />
								</div>
							</Card.Content>
						</Card.Root>
					</Tabs.Content>
				</Tabs.Root>
			</div>

			<!-- Sidebar - Live Preview -->
			<div>
				<Card.Root class="sticky top-6">
					<Card.Header>
						<Card.Title>Preview</Card.Title>
					</Card.Header>
					<Card.Content>
						<form class="space-y-4" onsubmit={(e) => e.preventDefault()}>
							{#each fields as field}
								<div class="space-y-2">
									<Label>
										{field.label}
										{#if field.required}
											<span class="text-red-500">*</span>
										{/if}
									</Label>
									{#if field.type === 'text' || field.type === 'email' || field.type === 'phone' || field.type === 'number'}
										<Input
											type={field.type === 'email' ? 'email' : field.type === 'number' ? 'number' : 'text'}
											placeholder={field.placeholder}
											disabled
										/>
									{:else if field.type === 'textarea'}
										<Textarea placeholder={field.placeholder} rows={3} disabled />
									{:else if field.type === 'date'}
										<Input type="date" disabled />
									{:else if field.type === 'select'}
										<Select.Root type="single" disabled>
											<Select.Trigger>
												<span class="text-muted-foreground">{field.placeholder || 'Select...'}</span>
											</Select.Trigger>
										</Select.Root>
									{:else if field.type === 'checkbox'}
										<div class="flex items-center gap-2">
											<Checkbox disabled />
											<span class="text-sm">{field.placeholder || field.label}</span>
										</div>
									{:else if field.type === 'radio' && field.options}
										<div class="space-y-2">
											{#each field.options as option}
												<label class="flex items-center gap-2">
													<input type="radio" disabled class="h-4 w-4" />
													<span class="text-sm">{option.label}</span>
												</label>
											{/each}
										</div>
									{:else if field.type === 'file'}
										<Input type="file" disabled />
									{/if}
								</div>
							{/each}
							{#if fields.length > 0}
								<Button class="w-full" disabled>{submitButtonText || 'Submit'}</Button>
							{/if}
						</form>
					</Card.Content>
				</Card.Root>
			</div>
		</div>
	</div>

	<!-- Field Dialog -->
	<Dialog.Root bind:open={showFieldDialog}>
		<Dialog.Content class="max-w-lg">
			<Dialog.Header>
				<Dialog.Title>{editingFieldIndex !== null ? 'Edit Field' : 'Add Field'}</Dialog.Title>
			</Dialog.Header>
			<div class="space-y-4 py-4">
				<div class="space-y-2">
					<Label>Field Type</Label>
					<Select.Root
						type="single"
						value={newField.type}
						onValueChange={(val) => {
							if (val) newField.type = val as FormFieldType;
						}}
					>
						<Select.Trigger>
							<span>{fieldTypes.find((f) => f.value === newField.type)?.label}</span>
						</Select.Trigger>
						<Select.Content>
							{#each fieldTypes as ft}
								<Select.Item value={ft.value}>
									<div class="flex items-center gap-2">
										<svelte:component this={ft.icon} class="h-4 w-4" />
										{ft.label}
									</div>
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-2">
					<Label for="fieldLabel">Label</Label>
					<Input id="fieldLabel" placeholder="Field label" bind:value={newField.label} />
				</div>

				<div class="space-y-2">
					<Label for="fieldName">Name (optional)</Label>
					<Input id="fieldName" placeholder="field_name" bind:value={newField.name} />
					<p class="text-muted-foreground text-xs">
						Auto-generated from label if not provided
					</p>
				</div>

				<div class="space-y-2">
					<Label for="fieldPlaceholder">Placeholder</Label>
					<Input id="fieldPlaceholder" placeholder="Placeholder text" bind:value={newField.placeholder} />
				</div>

				{#if newField.type === 'select' || newField.type === 'radio'}
					<div class="space-y-2">
						<Label>Options</Label>
						{#if newField.options && newField.options.length > 0}
							<div class="space-y-2">
								{#each newField.options as option, i}
									<div class="flex gap-2">
										<Input
											placeholder="Label"
											bind:value={option.label}
											oninput={() => {
												if (!option.value) {
													option.value = option.label.toLowerCase().replace(/\s+/g, '_');
												}
											}}
										/>
										<Input placeholder="Value" bind:value={option.value} />
										<Button variant="ghost" size="sm" onclick={() => removeOption(i)}>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								{/each}
							</div>
						{/if}
						<Button variant="outline" size="sm" onclick={addOption}>
							<Plus class="mr-1 h-4 w-4" />
							Add Option
						</Button>
					</div>
				{/if}

				<div class="flex items-center gap-2">
					<Checkbox
						id="fieldRequired"
						checked={newField.required}
						onCheckedChange={(checked) => {
							newField.required = checked === true;
						}}
					/>
					<Label for="fieldRequired">Required field</Label>
				</div>
			</div>
			<Dialog.Footer>
				<Button variant="outline" onclick={() => (showFieldDialog = false)}>Cancel</Button>
				<Button onclick={saveField}>
					{editingFieldIndex !== null ? 'Update Field' : 'Add Field'}
				</Button>
			</Dialog.Footer>
		</Dialog.Content>
	</Dialog.Root>

	<!-- Embed Code Dialog -->
	<Dialog.Root bind:open={showEmbedDialog}>
		<Dialog.Content>
			<Dialog.Header>
				<Dialog.Title>Embed Code</Dialog.Title>
				<Dialog.Description>Copy this code to embed the form on your website</Dialog.Description>
			</Dialog.Header>
			<div class="py-4">
				<Textarea value={embedCode} rows={10} readonly class="font-mono text-sm" />
			</div>
			<Dialog.Footer>
				<Button
					onclick={() => {
						navigator.clipboard.writeText(embedCode);
						toast.success('Copied to clipboard');
					}}
				>
					<Copy class="mr-1 h-4 w-4" />
					Copy Code
				</Button>
			</Dialog.Footer>
		</Dialog.Content>
	</Dialog.Root>
{/if}
