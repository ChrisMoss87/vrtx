<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { modulesApi, type Module, type Field, type Block } from '$lib/api/modules';
	import { recordsApi, type ModuleRecord } from '$lib/api/records';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import * as Card from '$lib/components/ui/card';
	import { ArrowLeft, Save, Loader2, Trash2 } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';

	const moduleApiName = $derived($page.params.moduleApiName as string);
	const recordId = $derived(parseInt($page.params.recordId as string));

	let module = $state<Module | null>(null);
	let record = $state<ModuleRecord | null>(null);
	let loading = $state(true);
	let saving = $state(false);
	let deleting = $state(false);
	let error = $state<string | null>(null);
	let formData = $state<Record<string, any>>({});
	let fieldErrors = $state<Record<string, string>>({});
	let deleteDialogOpen = $state(false);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		error = null;

		try {
			// Load module and record in parallel
			const [moduleData, recordData] = await Promise.all([
				modulesApi.getByApiName(moduleApiName),
				recordsApi.getById(moduleApiName, recordId)
			]);

			module = moduleData;
			record = recordData;

			// Initialize form data from record
			formData = { ...recordData.data };
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load record';
		} finally {
			loading = false;
		}
	}

	function validateField(field: Field): string | null {
		const value = formData[field.api_name];

		if (field.is_required) {
			if (value === null || value === undefined || value === '') {
				return `${field.label} is required`;
			}
			if (Array.isArray(value) && value.length === 0) {
				return `${field.label} is required`;
			}
		}

		// Type-specific validation
		if (value !== null && value !== undefined && value !== '') {
			switch (field.type) {
				case 'email':
					if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
						return 'Please enter a valid email address';
					}
					break;
				case 'url':
					try {
						new URL(value);
					} catch {
						return 'Please enter a valid URL';
					}
					break;
				case 'phone':
					if (!/^[\d\s\-+()]+$/.test(value)) {
						return 'Please enter a valid phone number';
					}
					break;
			}
		}

		return null;
	}

	function validateForm(): boolean {
		fieldErrors = {};
		let isValid = true;

		if (module?.blocks) {
			for (const block of module.blocks) {
				for (const field of block.fields) {
					const error = validateField(field);
					if (error) {
						fieldErrors[field.api_name] = error;
						isValid = false;
					}
				}
			}
		}

		return isValid;
	}

	async function handleSubmit() {
		if (!validateForm()) {
			toast.error('Please fix the errors before submitting');
			return;
		}

		saving = true;

		try {
			await recordsApi.update(moduleApiName, recordId, formData);
			toast.success(`${module?.singular_name || 'Record'} updated successfully`);
			goto(`/records/${moduleApiName}`);
		} catch (err: any) {
			const message = err.response?.data?.error || err.message || 'Failed to update record';
			toast.error(message);

			// Handle validation errors from server
			if (err.response?.data?.errors) {
				fieldErrors = err.response.data.errors;
			}
		} finally {
			saving = false;
		}
	}

	async function handleDelete() {
		deleting = true;

		try {
			await recordsApi.delete(moduleApiName, recordId);
			toast.success(`${module?.singular_name || 'Record'} deleted successfully`);
			goto(`/records/${moduleApiName}`);
		} catch (err: any) {
			const message = err.response?.data?.error || err.message || 'Failed to delete record';
			toast.error(message);
		} finally {
			deleting = false;
			deleteDialogOpen = false;
		}
	}

	function handleCancel() {
		goto(`/records/${moduleApiName}`);
	}

	function handleFieldChange(fieldApiName: string, value: any) {
		formData[fieldApiName] = value;
		// Clear error when user starts typing
		if (fieldErrors[fieldApiName]) {
			delete fieldErrors[fieldApiName];
			fieldErrors = { ...fieldErrors };
		}
	}
</script>

<div class="container mx-auto max-w-4xl py-8">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-center">
				<Loader2 class="mx-auto h-12 w-12 animate-spin text-primary" />
				<p class="mt-4 text-muted-foreground">Loading...</p>
			</div>
		</div>
	{:else if error}
		<div class="rounded-lg border border-destructive p-6">
			<p class="text-destructive">{error}</p>
			<Button variant="outline" class="mt-4" onclick={() => goto(`/records/${moduleApiName}`)}>
				<ArrowLeft class="mr-2 h-4 w-4" />
				Go Back
			</Button>
		</div>
	{:else if module && record}
		<div class="space-y-6">
			<!-- Header -->
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-4">
					<Button variant="ghost" size="icon" onclick={handleCancel}>
						<ArrowLeft class="h-4 w-4" />
					</Button>
					<div>
						<h1 class="text-2xl font-bold">Edit {module.singular_name}</h1>
						<p class="mt-1 text-muted-foreground">Update the details below</p>
					</div>
				</div>
				<div class="flex items-center gap-2">
					<Button
						variant="destructive"
						size="sm"
						onclick={() => (deleteDialogOpen = true)}
						disabled={saving || deleting}
					>
						<Trash2 class="mr-2 h-4 w-4" />
						Delete
					</Button>
					<Button variant="outline" onclick={handleCancel} disabled={saving}>Cancel</Button>
					<Button onclick={handleSubmit} disabled={saving}>
						{#if saving}
							<Loader2 class="mr-2 h-4 w-4 animate-spin" />
							Saving...
						{:else}
							<Save class="mr-2 h-4 w-4" />
							Save Changes
						{/if}
					</Button>
				</div>
			</div>

			<!-- Record metadata -->
			<div class="text-sm text-muted-foreground">
				<span>Created: {new Date(record.created_at).toLocaleString()}</span>
				{#if record.updated_at}
					<span class="ml-4">Last updated: {new Date(record.updated_at).toLocaleString()}</span>
				{/if}
			</div>

			<!-- Form -->
			<form
				onsubmit={(e) => {
					e.preventDefault();
					handleSubmit();
				}}
			>
				{#each module.blocks || [] as block}
					<Card.Root class="mb-6">
						<Card.Header>
							<Card.Title>{block.name}</Card.Title>
						</Card.Header>
						<Card.Content>
							<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
								{#each block.fields as field}
									{@const fieldError = fieldErrors[field.api_name]}
									<div
										class={field.type === 'textarea' || field.type === 'rich_text'
											? 'md:col-span-2'
											: ''}
									>
										<Label
											for={field.api_name}
											class={field.is_required
												? 'after:ml-0.5 after:text-destructive after:content-["*"]'
												: ''}
										>
											{field.label}
										</Label>

										{#if field.help_text}
											<p class="mt-1 text-xs text-muted-foreground">{field.help_text}</p>
										{/if}

										<div class="mt-2">
											{#if field.type === 'text' || field.type === 'email' || field.type === 'phone' || field.type === 'url'}
												<Input
													id={field.api_name}
													type={field.type === 'email'
														? 'email'
														: field.type === 'url'
															? 'url'
															: field.type === 'phone'
																? 'tel'
																: 'text'}
													placeholder={field.placeholder || `Enter ${field.label.toLowerCase()}`}
													value={formData[field.api_name] || ''}
													oninput={(e) => handleFieldChange(field.api_name, e.currentTarget.value)}
													class={fieldError ? 'border-destructive' : ''}
												/>
											{:else if field.type === 'textarea'}
												<Textarea
													id={field.api_name}
													placeholder={field.placeholder || `Enter ${field.label.toLowerCase()}`}
													value={formData[field.api_name] || ''}
													oninput={(e) => handleFieldChange(field.api_name, e.currentTarget.value)}
													rows={4}
													class={fieldError ? 'border-destructive' : ''}
												/>
											{:else if field.type === 'number' || field.type === 'decimal' || field.type === 'currency' || field.type === 'percent'}
												<Input
													id={field.api_name}
													type="number"
													step={field.type === 'decimal' || field.type === 'currency'
														? '0.01'
														: field.type === 'percent'
															? '0.1'
															: '1'}
													placeholder={field.placeholder || `Enter ${field.label.toLowerCase()}`}
													value={formData[field.api_name] ?? ''}
													oninput={(e) =>
														handleFieldChange(
															field.api_name,
															e.currentTarget.value ? parseFloat(e.currentTarget.value) : null
														)}
													class={fieldError ? 'border-destructive' : ''}
												/>
											{:else if field.type === 'date'}
												<Input
													id={field.api_name}
													type="date"
													value={formData[field.api_name] || ''}
													oninput={(e) => handleFieldChange(field.api_name, e.currentTarget.value)}
													class={fieldError ? 'border-destructive' : ''}
												/>
											{:else if field.type === 'datetime'}
												<Input
													id={field.api_name}
													type="datetime-local"
													value={formData[field.api_name] || ''}
													oninput={(e) => handleFieldChange(field.api_name, e.currentTarget.value)}
													class={fieldError ? 'border-destructive' : ''}
												/>
											{:else if field.type === 'select' || field.type === 'radio'}
												<Select.Root
													type="single"
													value={formData[field.api_name] || undefined}
													onValueChange={(value) => handleFieldChange(field.api_name, value || '')}
												>
													<Select.Trigger class={fieldError ? 'border-destructive' : ''}>
														<span>
															{formData[field.api_name]
																? field.options?.find((o) => o.value === formData[field.api_name])
																		?.label || formData[field.api_name]
																: field.placeholder || `Select ${field.label.toLowerCase()}`}
														</span>
													</Select.Trigger>
													<Select.Content>
														{#each field.options || [] as option}
															<Select.Item value={option.value}>{option.label}</Select.Item>
														{/each}
													</Select.Content>
												</Select.Root>
											{:else if field.type === 'multiselect'}
												<Select.Root
													type="multiple"
													value={Array.isArray(formData[field.api_name])
														? formData[field.api_name]
														: []}
													onValueChange={(values) =>
														handleFieldChange(field.api_name, values || [])}
												>
													<Select.Trigger class={fieldError ? 'border-destructive' : ''}>
														<span>
															{#if Array.isArray(formData[field.api_name]) && formData[field.api_name].length > 0}
																{formData[field.api_name]
																	.map(
																		(v: string) =>
																			field.options?.find((o) => o.value === v)?.label || v
																	)
																	.join(', ')}
															{:else}
																{field.placeholder || `Select ${field.label.toLowerCase()}`}
															{/if}
														</span>
													</Select.Trigger>
													<Select.Content>
														{#each field.options || [] as option}
															<Select.Item value={option.value}>{option.label}</Select.Item>
														{/each}
													</Select.Content>
												</Select.Root>
											{:else if field.type === 'checkbox' || field.type === 'toggle'}
												<div class="flex items-center gap-2">
													<Checkbox
														id={field.api_name}
														checked={formData[field.api_name] || false}
														onCheckedChange={(checked) =>
															handleFieldChange(field.api_name, checked)}
													/>
													<Label for={field.api_name} class="cursor-pointer font-normal">
														{field.placeholder || field.label}
													</Label>
												</div>
											{:else}
												<!-- Fallback to text input -->
												<Input
													id={field.api_name}
													type="text"
													placeholder={field.placeholder || `Enter ${field.label.toLowerCase()}`}
													value={formData[field.api_name] || ''}
													oninput={(e) => handleFieldChange(field.api_name, e.currentTarget.value)}
													class={fieldError ? 'border-destructive' : ''}
												/>
											{/if}
										</div>

										{#if fieldError}
											<p class="mt-1 text-sm text-destructive">{fieldError}</p>
										{/if}
									</div>
								{/each}
							</div>
						</Card.Content>
					</Card.Root>
				{/each}

				<!-- Submit buttons at bottom -->
				<div class="flex justify-between border-t pt-4">
					<Button
						type="button"
						variant="destructive"
						onclick={() => (deleteDialogOpen = true)}
						disabled={saving || deleting}
					>
						<Trash2 class="mr-2 h-4 w-4" />
						Delete {module.singular_name}
					</Button>
					<div class="flex gap-2">
						<Button type="button" variant="outline" onclick={handleCancel} disabled={saving}>
							Cancel
						</Button>
						<Button type="submit" disabled={saving}>
							{#if saving}
								<Loader2 class="mr-2 h-4 w-4 animate-spin" />
								Saving...
							{:else}
								<Save class="mr-2 h-4 w-4" />
								Save Changes
							{/if}
						</Button>
					</div>
				</div>
			</form>
		</div>
	{/if}
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete {module?.singular_name || 'Record'}?</AlertDialog.Title>
			<AlertDialog.Description>
				This action cannot be undone. This will permanently delete this {module?.singular_name?.toLowerCase() ||
					'record'} from the database.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel disabled={deleting}>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				onclick={handleDelete}
				disabled={deleting}
				class="text-destructive-foreground bg-destructive hover:bg-destructive/90"
			>
				{#if deleting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					Deleting...
				{:else}
					Delete
				{/if}
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
