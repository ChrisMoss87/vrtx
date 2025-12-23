<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Save, Loader2 } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { reportTemplatesApi } from '$lib/api/report-templates';

	interface Props {
		reportId: number;
		defaultName?: string;
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
		onSaved?: () => void;
	}

	let { reportId, defaultName = '', open = $bindable(false), onOpenChange, onSaved }: Props = $props();

	let name = $state(defaultName || 'My Template');
	let description = $state('');
	let isPublic = $state(false);
	let saving = $state(false);

	async function handleSave() {
		if (!name.trim()) {
			toast.error('Please enter a template name');
			return;
		}

		saving = true;
		try {
			await reportTemplatesApi.createFromReport(reportId, {
				name: name.trim(),
				description: description.trim() || undefined,
				is_public: isPublic
			});

			toast.success('Template saved successfully');
			open = false;
			onSaved?.();
		} catch (error) {
			console.error('Failed to save template:', error);
			toast.error('Failed to save template');
		} finally {
			saving = false;
		}
	}

	function handleOpenChange(value: boolean) {
		open = value;
		onOpenChange?.(value);
	}
</script>

<Dialog.Root bind:open onOpenChange={handleOpenChange}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>Save as Template</Dialog.Title>
			<Dialog.Description>
				Save this report configuration as a reusable template
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="template-name">Template Name</Label>
				<Input
					id="template-name"
					placeholder="Enter template name"
					bind:value={name}
				/>
			</div>

			<div class="space-y-2">
				<Label for="template-description">Description (Optional)</Label>
				<Textarea
					id="template-description"
					placeholder="Describe what this template is for"
					bind:value={description}
					rows={2}
				/>
			</div>

			<div class="flex items-center justify-between">
				<div class="space-y-0.5">
					<Label for="template-public">Make Public</Label>
					<p class="text-xs text-muted-foreground">Allow other users to use this template</p>
				</div>
				<Switch id="template-public" bind:checked={isPublic} />
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (open = false)} disabled={saving}>
				Cancel
			</Button>
			<Button onclick={handleSave} disabled={saving}>
				{#if saving}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					Saving...
				{:else}
					<Save class="mr-2 h-4 w-4" />
					Save Template
				{/if}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
