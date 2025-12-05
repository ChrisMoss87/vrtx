<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trash2, Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import VariableInserter from '../VariableInserter.svelte';

	interface Header {
		name: string;
		value: string;
	}

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], onConfigChange }: Props = $props();

	// Local state
	let url = $state<string>((config.url as string) || '');
	let method = $state<string>((config.method as string) || 'POST');
	let headers = $state<Header[]>((config.headers as Header[]) || []);
	let bodyType = $state<string>((config.body_type as string) || 'json');
	let body = $state<string>((config.body as string) || '');
	let timeout = $state<number>((config.timeout as number) || 30);

	function emitChange() {
		onConfigChange?.({
			url,
			method,
			headers,
			body_type: bodyType,
			body,
			timeout
		});
	}

	function addHeader() {
		headers = [...headers, { name: '', value: '' }];
		emitChange();
	}

	function removeHeader(index: number) {
		headers = headers.filter((_, i) => i !== index);
		emitChange();
	}

	function updateHeader(index: number, updates: Partial<Header>) {
		headers = headers.map((h, i) => (i === index ? { ...h, ...updates } : h));
		emitChange();
	}

	function insertVariable(variable: string) {
		body = `${body}{{${variable}}}`;
		emitChange();
	}
</script>

<div class="space-y-4">
	<h4 class="font-medium">Webhook Configuration</h4>

	<!-- URL -->
	<div class="space-y-2">
		<Label>URL</Label>
		<Input
			value={url}
			oninput={(e) => {
				url = e.currentTarget.value;
				emitChange();
			}}
			placeholder="https://api.example.com/webhook"
		/>
		<p class="text-xs text-muted-foreground">
			The URL to send the webhook request to
		</p>
	</div>

	<!-- Method -->
	<div class="space-y-2">
		<Label>Method</Label>
		<Select.Root
			type="single"
			value={method}
			onValueChange={(v) => {
				if (v) {
					method = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{method}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="GET">GET</Select.Item>
				<Select.Item value="POST">POST</Select.Item>
				<Select.Item value="PUT">PUT</Select.Item>
				<Select.Item value="PATCH">PATCH</Select.Item>
				<Select.Item value="DELETE">DELETE</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Headers -->
	<div class="space-y-2">
		<Label>Headers (optional)</Label>
		{#if headers.length > 0}
			<div class="space-y-2">
				{#each headers as header, index}
					<div class="flex items-center gap-2">
						<Input
							value={header.name}
							oninput={(e) => updateHeader(index, { name: e.currentTarget.value })}
							placeholder="Header name"
							class="flex-1"
						/>
						<Input
							value={header.value}
							oninput={(e) => updateHeader(index, { value: e.currentTarget.value })}
							placeholder="Header value"
							class="flex-1"
						/>
						<Button
							type="button"
							variant="ghost"
							size="icon"
							class="h-9 w-9"
							onclick={() => removeHeader(index)}
						>
							<Trash2 class="h-4 w-4" />
						</Button>
					</div>
				{/each}
			</div>
		{/if}
		<Button type="button" variant="outline" size="sm" onclick={addHeader}>
			<Plus class="mr-2 h-4 w-4" />
			Add Header
		</Button>
	</div>

	<!-- Body Type -->
	{#if method !== 'GET'}
		<div class="space-y-2">
			<Label>Body Type</Label>
			<Select.Root
				type="single"
				value={bodyType}
				onValueChange={(v) => {
					if (v) {
						bodyType = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{bodyType === 'json' ? 'JSON' : bodyType === 'form' ? 'Form Data' : 'Raw'}
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="json">JSON</Select.Item>
					<Select.Item value="form">Form Data</Select.Item>
					<Select.Item value="raw">Raw</Select.Item>
				</Select.Content>
			</Select.Root>
		</div>

		<!-- Body -->
		<div class="space-y-2">
			<div class="flex items-center justify-between">
				<Label>Request Body</Label>
				<VariableInserter fields={moduleFields} onInsert={insertVariable} />
			</div>
			<Textarea
				value={body}
				oninput={(e) => {
					body = e.currentTarget.value;
					emitChange();
				}}
				placeholder={bodyType === 'json'
					? '{\n  "record_id": "{{record.id}}",\n  "name": "{{record.name}}"\n}'
					: 'key=value&another={{record.field}}'}
				rows={6}
				class="font-mono text-sm"
			/>
		</div>
	{/if}

	<!-- Timeout -->
	<div class="space-y-2">
		<Label>Timeout (seconds)</Label>
		<Input
			type="number"
			min="1"
			max="120"
			value={String(timeout)}
			oninput={(e) => {
				timeout = parseInt(e.currentTarget.value) || 30;
				emitChange();
			}}
		/>
		<p class="text-xs text-muted-foreground">
			Maximum time to wait for a response (1-120 seconds)
		</p>
	</div>

	<!-- Info -->
	<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
		<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
		<p class="text-xs text-muted-foreground">
			Use <code class="rounded bg-muted px-1">{'{{field_name}}'}</code> to include record data in the URL or body.
			The webhook will include the full record data as JSON if no custom body is specified.
		</p>
	</div>
</div>
