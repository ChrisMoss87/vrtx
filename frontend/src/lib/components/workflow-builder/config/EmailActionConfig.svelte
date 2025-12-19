<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { X, Plus, Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import VariableInserter from '../VariableInserter.svelte';

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], onConfigChange }: Props = $props();

	// Local state
	let recipientType = $state<string>((config.recipient_type as string) || 'field');
	let recipientField = $state<string>((config.recipient_field as string) || '');
	let recipientEmail = $state<string>((config.recipient_email as string) || '');
	let ccEmails = $state<string[]>((config.cc_emails as string[]) || []);
	let subject = $state<string>((config.subject as string) || '');
	let bodyHtml = $state<string>((config.body_html as string) || '');
	let templateId = $state<number | null>((config.template_id as number) || null);
	let newCcEmail = $state('');

	// Email fields from module
	const emailFields = $derived(moduleFields.filter((f) => f.type === 'email'));

	function emitChange() {
		onConfigChange?.({
			recipient_type: recipientType,
			recipient_field: recipientField,
			recipient_email: recipientEmail,
			cc_emails: ccEmails,
			subject,
			body_html: bodyHtml,
			template_id: templateId
		});
	}

	function addCcEmail() {
		if (newCcEmail && !ccEmails.includes(newCcEmail)) {
			ccEmails = [...ccEmails, newCcEmail];
			newCcEmail = '';
			emitChange();
		}
	}

	function removeCcEmail(email: string) {
		ccEmails = ccEmails.filter((e) => e !== email);
		emitChange();
	}

	function insertVariable(variable: string, target: 'subject' | 'body') {
		if (target === 'subject') {
			subject = `${subject}{{${variable}}}`;
		} else {
			bodyHtml = `${bodyHtml}{{${variable}}}`;
		}
		emitChange();
	}
</script>

<div class="space-y-4">
	<h4 class="font-medium">Email Configuration</h4>

	<!-- Recipient Type -->
	<div class="space-y-2">
		<Label>Send To</Label>
		<Select.Root
			type="single"
			value={recipientType}
			onValueChange={(v) => {
				if (v) {
					recipientType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{recipientType === 'field'
					? 'Email from record field'
					: recipientType === 'owner'
						? 'Record owner'
						: recipientType === 'specific'
							? 'Specific email address'
							: 'Select recipient type'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="field">Email from record field</Select.Item>
				<Select.Item value="owner">Record owner</Select.Item>
				<Select.Item value="specific">Specific email address</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Field Selection (for field type) -->
	{#if recipientType === 'field'}
		<div class="space-y-2">
			<Label>Email Field</Label>
			<Select.Root
				type="single"
				value={recipientField}
				onValueChange={(v) => {
					if (v) {
						recipientField = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{emailFields.find((f) => f.api_name === recipientField)?.label || 'Select email field'}
				</Select.Trigger>
				<Select.Content>
					{#each emailFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			{#if emailFields.length === 0}
				<p class="text-xs text-muted-foreground">
					No email fields found in this module
				</p>
			{/if}
		</div>
	{/if}

	<!-- Specific Email (for specific type) -->
	{#if recipientType === 'specific'}
		<div class="space-y-2">
			<Label>Email Address</Label>
			<Input
				type="email"
				value={recipientEmail}
				oninput={(e) => {
					recipientEmail = e.currentTarget.value;
					emitChange();
				}}
				placeholder="recipient@example.com"
			/>
		</div>
	{/if}

	<!-- CC Emails -->
	<div class="space-y-2">
		<Label>CC (optional)</Label>
		{#if ccEmails.length > 0}
			<div class="flex flex-wrap gap-2">
				{#each ccEmails as email}
					<Badge variant="secondary" class="gap-1">
						{email}
						<button type="button" onclick={() => removeCcEmail(email)} class="hover:text-destructive">
							<X class="h-3 w-3" />
						</button>
					</Badge>
				{/each}
			</div>
		{/if}
		<div class="flex gap-2">
			<Input
				type="email"
				bind:value={newCcEmail}
				placeholder="cc@example.com"
				onkeydown={(e) => e.key === 'Enter' && addCcEmail()}
			/>
			<Button type="button" variant="outline" size="icon" onclick={addCcEmail}>
				<Plus class="h-4 w-4" />
			</Button>
		</div>
	</div>

	<!-- Subject -->
	<div class="space-y-2">
		<div class="flex items-center justify-between">
			<Label>Subject</Label>
			<VariableInserter fields={moduleFields} onInsert={(v) => insertVariable(v, 'subject')} />
		</div>
		<Input
			value={subject}
			oninput={(e) => {
				subject = e.currentTarget.value;
				emitChange();
			}}
			placeholder="Email subject - use variables like record.name"
		/>
	</div>

	<!-- Body -->
	<div class="space-y-2">
		<div class="flex items-center justify-between">
			<Label>Body</Label>
			<VariableInserter fields={moduleFields} onInsert={(v) => insertVariable(v, 'body')} />
		</div>
		<Textarea
			value={bodyHtml}
			oninput={(e) => {
				bodyHtml = e.currentTarget.value;
				emitChange();
			}}
			placeholder="Email body - use variables like record.name"
			rows={6}
		/>
	</div>

	<!-- Help Text -->
	<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
		<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
		<p class="text-xs text-muted-foreground">
			Use <code class="rounded bg-muted px-1">{'{{field_name}}'}</code> to insert record field values.
			For example: <code class="rounded bg-muted px-1">{'{{first_name}}'}</code>
		</p>
	</div>
</div>
