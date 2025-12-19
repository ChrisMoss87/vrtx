<script lang="ts">
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import {
		ArrowLeft,
		Save,
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
		Upload
	} from 'lucide-svelte';
	import {
		cmsFormApi,
		type FormField,
		type FormFieldType,
		type FormSubmitAction
	} from '$lib/api/cms';
	import { toast } from 'svelte-sonner';

	let saving = $state(false);

	// Form state
	let name = $state('');
	let slug = $state('');
	let description = $state('');
	let fields = $state<FormField[]>([]);
	let submitAction = $state<FormSubmitAction>('create_lead');
	let submitButtonText = $state('Submit');

	let showFieldDialog = $state(false);
	let editingFieldIndex = $state<number | null>(null);

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
			const form = await cmsFormApi.create({
				name: name.trim(),
				slug: slug.trim() || undefined,
				description: description.trim() || undefined,
				fields,
				submit_action: submitAction,
				submit_button_text: submitButtonText.trim() || 'Submit'
			});

			toast.success('Form created');
			goto(`/cms/forms/${form.id}/edit`);
		} catch (error) {
			toast.error('Failed to create form');
		} finally {
			saving = false;
		}
	}

	function getFieldIcon(type: FormFieldType) {
		return fieldTypes.find((f) => f.value === type)?.icon || Type;
	}
</script>

<div class="container py-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="sm" href="/cms/forms">
				<ArrowLeft class="mr-1 h-4 w-4" />
				Back
			</Button>
			<div>
				<h1 class="text-2xl font-bold">New Form</h1>
				<p class="text-muted-foreground">Create a new form for lead capture or feedback</p>
			</div>
		</div>
		<Button onclick={handleSave} disabled={saving}>
			<Save class="mr-1 h-4 w-4" />
			{saving ? 'Creating...' : 'Create Form'}
		</Button>
	</div>

	<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
		<!-- Main Content -->
		<div class="space-y-6 lg:col-span-2">
			<!-- Basic Info -->
			<Card.Root>
				<Card.Header>
					<Card.Title>Form Details</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
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
							rows={2}
						/>
					</div>

					<div class="grid grid-cols-2 gap-4">
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
							<Label for="submitButtonText">Button Text</Label>
							<Input id="submitButtonText" placeholder="Submit" bind:value={submitButtonText} />
						</div>
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Fields -->
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
						{:else}
							<p class="text-muted-foreground text-center text-sm">Add fields to see preview</p>
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
				<p class="text-muted-foreground text-xs">Auto-generated from label if not provided</p>
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
