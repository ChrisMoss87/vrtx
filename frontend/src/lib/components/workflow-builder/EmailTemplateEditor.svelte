<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Button } from '$lib/components/ui/button';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import { Save, X, ChevronDown, Code, Eye, Loader2 } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		createWorkflowEmailTemplate,
		updateWorkflowEmailTemplate,
		getWorkflowEmailTemplateVariables,
		type WorkflowEmailTemplate,
		type WorkflowEmailTemplateInput,
		type VariableDefinition
	} from '$lib/api/workflow-email-templates';
	import * as Popover from '$lib/components/ui/popover';
	import { Code2 } from 'lucide-svelte';
	import DOMPurify from 'isomorphic-dompurify';

	/**
	 * Sanitize HTML content for preview to prevent XSS attacks.
	 * Allows more tags for email template editing.
	 */
	function sanitizeHtml(html: string): string {
		return DOMPurify.sanitize(html, {
			ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'u', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'div', 'span', 'table', 'tr', 'td', 'th', 'thead', 'tbody', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code', 'img', 'hr', 'center', 'font'],
			ALLOWED_ATTR: ['href', 'src', 'alt', 'title', 'class', 'style', 'target', 'width', 'height', 'align', 'valign', 'bgcolor', 'color', 'size', 'face', 'border', 'cellpadding', 'cellspacing'],
			ALLOW_DATA_ATTR: false,
		});
	}

	interface Props {
		template?: WorkflowEmailTemplate | null;
		onSave?: () => void;
		onCancel?: () => void;
	}

	let { template = null, onSave, onCancel }: Props = $props();

	const isNew = !template?.id;

	// Form state
	let name = $state(template?.name || '');
	let description = $state(template?.description || '');
	let subject = $state(template?.subject || '');
	let bodyHtml = $state(template?.body_html || getDefaultBody());
	let bodyText = $state(template?.body_text || '');
	let fromName = $state(template?.from_name || '');
	let fromEmail = $state(template?.from_email || '');
	let replyTo = $state(template?.reply_to || '');
	let category = $state(template?.category || '');

	let saving = $state(false);
	let activeTab = $state('html');
	let variablesOpen = $state(true);
	let availableVariables = $state<Record<string, VariableDefinition>>({});

	// Load available variables
	$effect(() => {
		loadVariables();
	});

	async function loadVariables() {
		try {
			availableVariables = await getWorkflowEmailTemplateVariables();
		} catch (error) {
			console.error('Failed to load variables:', error);
		}
	}

	function getDefaultBody(): string {
		return `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<h2>Hello {{record.name}},</h2>
	<p>Your email content goes here.</p>
	<p>Best regards,<br>{{user.name}}</p>
</div>`;
	}

	async function handleSave() {
		if (!name.trim()) {
			toast.error('Template name is required');
			return;
		}
		if (!subject.trim()) {
			toast.error('Subject is required');
			return;
		}
		if (!bodyHtml.trim()) {
			toast.error('Email body is required');
			return;
		}

		saving = true;
		try {
			const data: WorkflowEmailTemplateInput = {
				name: name.trim(),
				description: description.trim() || undefined,
				subject: subject.trim(),
				body_html: bodyHtml,
				body_text: bodyText.trim() || undefined,
				from_name: fromName.trim() || undefined,
				from_email: fromEmail.trim() || undefined,
				reply_to: replyTo.trim() || undefined,
				category: category.trim() || undefined
			};

			if (isNew) {
				await createWorkflowEmailTemplate(data);
			} else {
				await updateWorkflowEmailTemplate(template!.id, data);
			}

			onSave?.();
		} catch (error) {
			console.error('Failed to save template:', error);
			toast.error('Failed to save template');
		} finally {
			saving = false;
		}
	}

	function insertVariable(variable: string, targetField: 'subject' | 'body') {
		const variableText = `{{${variable}}}`;
		if (targetField === 'subject') {
			subject = subject + variableText;
		} else {
			bodyHtml = bodyHtml + variableText;
		}
	}

	// Generate plain text from HTML
	function generatePlainText() {
		const temp = document.createElement('div');
		temp.innerHTML = bodyHtml;
		bodyText = temp.textContent || temp.innerText || '';
	}
</script>

<div class="space-y-6">
	<!-- Basic Info -->
	<div class="grid gap-4 sm:grid-cols-2">
		<div class="space-y-2">
			<Label>Template Name *</Label>
			<Input bind:value={name} placeholder="e.g., Welcome Email" />
		</div>
		<div class="space-y-2">
			<Label>Category</Label>
			<Input bind:value={category} placeholder="e.g., Onboarding" />
		</div>
	</div>

	<div class="space-y-2">
		<Label>Description</Label>
		<Textarea
			bind:value={description}
			placeholder="Briefly describe when this template is used"
			rows={2}
		/>
	</div>

	<!-- Sender Settings -->
	<Collapsible.Root>
		<Collapsible.Trigger class="flex w-full items-center justify-between rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted/50">
			Sender Settings (Optional)
			<ChevronDown class="h-4 w-4" />
		</Collapsible.Trigger>
		<Collapsible.Content>
			<div class="mt-2 grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
				<div class="space-y-2">
					<Label>From Name</Label>
					<Input bind:value={fromName} placeholder="e.g., VRTX CRM" />
				</div>
				<div class="space-y-2">
					<Label>From Email</Label>
					<Input type="email" bind:value={fromEmail} placeholder="e.g., noreply@example.com" />
				</div>
				<div class="space-y-2">
					<Label>Reply-To</Label>
					<Input type="email" bind:value={replyTo} placeholder="e.g., support@example.com" />
				</div>
			</div>
		</Collapsible.Content>
	</Collapsible.Root>

	<!-- Subject -->
	<div class="space-y-2">
		<div class="flex items-center justify-between">
			<Label>Subject Line *</Label>
			<Popover.Root>
				<Popover.Trigger>
					{#snippet child({ props })}
						<Button variant="ghost" size="sm" class="h-7 gap-1 px-2" {...props}>
							<Code2 class="h-3.5 w-3.5" />
							Insert Variable
						</Button>
					{/snippet}
				</Popover.Trigger>
				<Popover.Content class="w-64 p-2" align="end">
					<div class="space-y-2">
						{#each Object.entries(availableVariables) as [key, variable]}
							<button
								type="button"
								class="flex w-full flex-col items-start rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
								onclick={() => insertVariable(key, 'subject')}
							>
								<code class="text-xs font-semibold text-primary">{`{{${key}}}`}</code>
								<span class="text-xs text-muted-foreground">{variable.description}</span>
							</button>
						{/each}
					</div>
				</Popover.Content>
			</Popover.Root>
		</div>
		<Input bind:value={subject} placeholder={'e.g., Welcome to {{company.name}}, {{record.name}}!'} />
	</div>

	<!-- Available Variables Reference -->
	<Collapsible.Root bind:open={variablesOpen}>
		<Collapsible.Trigger class="flex w-full items-center justify-between rounded-lg border bg-muted/30 px-4 py-2 text-sm font-medium hover:bg-muted/50">
			Available Variables
			<ChevronDown class="h-4 w-4 transition-transform {variablesOpen ? 'rotate-180' : ''}" />
		</Collapsible.Trigger>
		<Collapsible.Content>
			<div class="mt-2 rounded-lg border bg-muted/30 p-4">
				<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
					{#each Object.entries(availableVariables) as [key, variable]}
						<div class="space-y-1">
							<code class="text-sm font-semibold text-primary">{`{{${key}}}`}</code>
							<p class="text-xs text-muted-foreground">{variable.description}</p>
							{#if variable.fields && variable.fields.length > 0}
								<p class="text-xs text-muted-foreground">
									Fields: {variable.fields.join(', ')}
								</p>
							{/if}
						</div>
					{/each}
				</div>
			</div>
		</Collapsible.Content>
	</Collapsible.Root>

	<!-- Email Body -->
	<div class="space-y-2">
		<div class="flex items-center justify-between">
			<Label>Email Body *</Label>
			<div class="flex items-center gap-2">
				<Popover.Root>
					<Popover.Trigger>
						{#snippet child({ props })}
							<Button variant="ghost" size="sm" class="h-7 gap-1 px-2" {...props}>
								<Code2 class="h-3.5 w-3.5" />
								Insert Variable
							</Button>
						{/snippet}
					</Popover.Trigger>
					<Popover.Content class="w-64 p-2" align="end">
						<div class="space-y-2 max-h-64 overflow-y-auto">
							{#each Object.entries(availableVariables) as [key, variable]}
								<button
									type="button"
									class="flex w-full flex-col items-start rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
									onclick={() => insertVariable(key, 'body')}
								>
									<code class="text-xs font-semibold text-primary">{`{{${key}}}`}</code>
									<span class="text-xs text-muted-foreground">{variable.description}</span>
								</button>
							{/each}
						</div>
					</Popover.Content>
				</Popover.Root>
			</div>
		</div>

		<Tabs.Root bind:value={activeTab}>
			<div class="flex items-center justify-between">
				<Tabs.List>
					<Tabs.Trigger value="html">
						<Code class="mr-1.5 h-3.5 w-3.5" />
						HTML
					</Tabs.Trigger>
					<Tabs.Trigger value="preview">
						<Eye class="mr-1.5 h-3.5 w-3.5" />
						Preview
					</Tabs.Trigger>
					<Tabs.Trigger value="text">
						Plain Text
					</Tabs.Trigger>
				</Tabs.List>
				{#if activeTab === 'text'}
					<Button variant="outline" size="sm" onclick={generatePlainText}>
						Generate from HTML
					</Button>
				{/if}
			</div>

			<Tabs.Content value="html" class="mt-2">
				<Textarea
					bind:value={bodyHtml}
					placeholder="<div>Your HTML email content...</div>"
					rows={15}
					class="font-mono text-sm"
				/>
			</Tabs.Content>

			<Tabs.Content value="preview" class="mt-2">
				<div
					class="min-h-[300px] rounded-md border bg-white p-4 prose prose-sm max-w-none"
				>
					{@html sanitizeHtml(bodyHtml)}
				</div>
			</Tabs.Content>

			<Tabs.Content value="text" class="mt-2">
				<Textarea
					bind:value={bodyText}
					placeholder="Plain text version of your email..."
					rows={15}
					class="font-mono text-sm"
				/>
				<p class="mt-1 text-xs text-muted-foreground">
					Optional: Provide a plain text version for email clients that don't support HTML.
				</p>
			</Tabs.Content>
		</Tabs.Root>
	</div>

	<!-- Actions -->
	<div class="flex justify-end gap-2 border-t pt-4">
		<Button variant="outline" onclick={onCancel} disabled={saving}>
			<X class="mr-2 h-4 w-4" />
			Cancel
		</Button>
		<Button onclick={handleSave} disabled={saving}>
			{#if saving}
				<Loader2 class="mr-2 h-4 w-4 animate-spin" />
			{:else}
				<Save class="mr-2 h-4 w-4" />
			{/if}
			{isNew ? 'Create Template' : 'Save Changes'}
		</Button>
	</div>
</div>
