<script lang="ts">
	import type { FormattedRequirement } from '$lib/api/blueprints';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Button } from '$lib/components/ui/button';
	import PaperclipIcon from '@lucide/svelte/icons/paperclip';
	import FileTextIcon from '@lucide/svelte/icons/file-text';
	import ListChecksIcon from '@lucide/svelte/icons/list-checks';
	import FormFieldIcon from '@lucide/svelte/icons/form-input';

	interface Props {
		requirements: FormattedRequirement[];
		data?: {
			fields: Record<string, unknown>;
			attachments: Array<{ name: string; size?: number; path?: string }>;
			note: string;
			checklist: Record<string | number, boolean>;
		};
	}

	let {
		requirements,
		data = $bindable({
			fields: {},
			attachments: [],
			note: '',
			checklist: {}
		})
	}: Props = $props();

	// Group requirements by type
	const fieldRequirements = $derived(requirements.filter((r) => r.type === 'mandatory_field'));
	const attachmentRequirements = $derived(requirements.filter((r) => r.type === 'attachment'));
	const noteRequirements = $derived(requirements.filter((r) => r.type === 'note'));
	const checklistRequirements = $derived(requirements.filter((r) => r.type === 'checklist'));

	// Get icon for requirement type
	function getTypeIcon(type: string) {
		switch (type) {
			case 'mandatory_field':
				return FormFieldIcon;
			case 'attachment':
				return PaperclipIcon;
			case 'note':
				return FileTextIcon;
			case 'checklist':
				return ListChecksIcon;
			default:
				return FormFieldIcon;
		}
	}

	// Handle field value change
	function handleFieldChange(fieldApiName: string, value: unknown) {
		data.fields = { ...data.fields, [fieldApiName]: value };
	}

	// Handle attachment upload
	function handleAttachmentUpload(files: File[]) {
		const newAttachments = files.map((f) => ({
			name: f.name,
			size: f.size
		}));
		data.attachments = [...data.attachments, ...newAttachments];
	}

	// Handle checklist item toggle
	function handleChecklistToggle(itemId: string | number, checked: boolean) {
		data.checklist = { ...data.checklist, [itemId]: checked };
	}
</script>

<div class="space-y-6">
	<!-- Mandatory Fields -->
	{#if fieldRequirements.length > 0}
		<div class="space-y-4">
			<div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
				<FormFieldIcon class="h-4 w-4" />
				Required Fields
			</div>
			{#each fieldRequirements as req}
				<div class="space-y-2">
					<Label for="field-{req.id}">
						{req.field?.label || req.label || 'Field'}
						{#if req.is_required}
							<span class="text-red-500">*</span>
						{/if}
					</Label>
					{#if req.description}
						<p class="text-xs text-muted-foreground">{req.description}</p>
					{/if}
					<!-- Render appropriate input based on field type -->
					{#if req.field?.type === 'textarea' || req.field?.type === 'text_area'}
						<Textarea
							id="field-{req.id}"
							value={(data.fields[req.field?.api_name || ''] as string) || ''}
							onchange={(e) =>
								handleFieldChange(req.field?.api_name || '', e.currentTarget.value)}
							placeholder="Enter {req.field?.label || 'value'}..."
						/>
					{:else}
						<Input
							id="field-{req.id}"
							type={req.field?.type === 'number' ? 'number' : 'text'}
							value={(data.fields[req.field?.api_name || ''] as string) || ''}
							onchange={(e) =>
								handleFieldChange(req.field?.api_name || '', e.currentTarget.value)}
							placeholder="Enter {req.field?.label || 'value'}..."
						/>
					{/if}
				</div>
			{/each}
		</div>
	{/if}

	<!-- Attachments -->
	{#if attachmentRequirements.length > 0}
		<div class="space-y-4">
			<div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
				<PaperclipIcon class="h-4 w-4" />
				Attachments
			</div>
			{#each attachmentRequirements as req}
				<div class="space-y-2">
					<Label>
						{req.label || 'Attachment'}
						{#if req.is_required}
							<span class="text-red-500">*</span>
						{/if}
					</Label>
					{#if req.description}
						<p class="text-xs text-muted-foreground">{req.description}</p>
					{/if}
					<div class="rounded-lg border border-dashed p-4">
						<input
							type="file"
							multiple
							accept={req.allowed_types?.join(',') || '*'}
							onchange={(e) => {
								const files = Array.from(e.currentTarget.files || []);
								handleAttachmentUpload(files);
							}}
							class="hidden"
							id="attachment-{req.id}"
						/>
						<label
							for="attachment-{req.id}"
							class="flex cursor-pointer flex-col items-center text-center"
						>
							<PaperclipIcon class="mb-2 h-8 w-8 text-muted-foreground" />
							<span class="text-sm text-muted-foreground">
								Click to upload or drag and drop
							</span>
							{#if req.allowed_types}
								<span class="mt-1 text-xs text-muted-foreground">
									Allowed: {req.allowed_types.join(', ')}
								</span>
							{/if}
						</label>
					</div>
					<!-- Show uploaded files -->
					{#if data.attachments.length > 0}
						<div class="space-y-1">
							{#each data.attachments as attachment, i}
								<div class="flex items-center justify-between rounded bg-muted p-2 text-sm">
									<span>{attachment.name}</span>
									<Button
										variant="ghost"
										size="sm"
										onclick={() => {
											data.attachments = data.attachments.filter((_, idx) => idx !== i);
										}}
									>
										Remove
									</Button>
								</div>
							{/each}
						</div>
					{/if}
				</div>
			{/each}
		</div>
	{/if}

	<!-- Notes -->
	{#if noteRequirements.length > 0}
		<div class="space-y-4">
			<div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
				<FileTextIcon class="h-4 w-4" />
				Notes
			</div>
			{#each noteRequirements as req}
				<div class="space-y-2">
					<Label for="note-{req.id}">
						{req.label || 'Note'}
						{#if req.is_required}
							<span class="text-red-500">*</span>
						{/if}
					</Label>
					{#if req.description}
						<p class="text-xs text-muted-foreground">{req.description}</p>
					{/if}
					<Textarea
						id="note-{req.id}"
						bind:value={data.note}
						placeholder="Enter your note..."
						rows={4}
					/>
					{#if req.min_length}
						<p class="text-xs text-muted-foreground">
							Minimum {req.min_length} characters
							{#if data.note}
								({data.note.length} entered)
							{/if}
						</p>
					{/if}
				</div>
			{/each}
		</div>
	{/if}

	<!-- Checklists -->
	{#if checklistRequirements.length > 0}
		<div class="space-y-4">
			<div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
				<ListChecksIcon class="h-4 w-4" />
				Checklists
			</div>
			{#each checklistRequirements as req}
				<div class="space-y-2">
					<Label>
						{req.label || 'Checklist'}
						{#if req.is_required}
							<span class="text-red-500">*</span>
						{/if}
					</Label>
					{#if req.description}
						<p class="text-xs text-muted-foreground">{req.description}</p>
					{/if}
					<div class="space-y-2 rounded-lg border p-3">
						{#each req.items || [] as item, i}
							<div class="flex items-center gap-3">
								<Checkbox
									id="checklist-{req.id}-{i}"
									checked={data.checklist[item.id || i] || false}
									onCheckedChange={(checked) =>
										handleChecklistToggle(item.id || i, !!checked)}
								/>
								<label
									for="checklist-{req.id}-{i}"
									class="text-sm {data.checklist[item.id || i]
										? 'text-muted-foreground line-through'
										: ''}"
								>
									{item.label}
									{#if item.required}
										<span class="text-red-500">*</span>
									{/if}
								</label>
							</div>
						{/each}
					</div>
				</div>
			{/each}
		</div>
	{/if}

	<!-- Empty state -->
	{#if requirements.length === 0}
		<div class="py-4 text-center text-muted-foreground">
			No requirements to complete
		</div>
	{/if}
</div>
