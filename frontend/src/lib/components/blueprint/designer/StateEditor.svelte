<script lang="ts">
	import type { BlueprintState } from '$lib/api/blueprints';
	import * as blueprintApi from '$lib/api/blueprints';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { toast } from 'svelte-sonner';
	import XIcon from '@lucide/svelte/icons/x';
	import Trash2Icon from '@lucide/svelte/icons/trash-2';

	interface Props {
		nodeState: BlueprintState;
		blueprintId: number;
		readonly?: boolean;
		onUpdate?: (state: BlueprintState) => void;
		onDelete?: () => void;
		onClose?: () => void;
	}

	let {
		nodeState,
		blueprintId,
		readonly = false,
		onUpdate,
		onDelete,
		onClose
	}: Props = $props();

	let saving = $state(false);

	// Form state
	let name = $state(nodeState.name);
	let color = $state(nodeState.color || '#6b7280');
	let isInitial = $state(nodeState.is_initial);
	let isTerminal = $state(nodeState.is_terminal);

	// Update form state when state prop changes
	$effect(() => {
		name = nodeState.name;
		color = nodeState.color || '#6b7280';
		isInitial = nodeState.is_initial;
		isTerminal = nodeState.is_terminal;
	});

	// Color presets
	const colorPresets = [
		{ color: '#6b7280', name: 'Gray' },
		{ color: '#22c55e', name: 'Green' },
		{ color: '#3b82f6', name: 'Blue' },
		{ color: '#f59e0b', name: 'Amber' },
		{ color: '#ef4444', name: 'Red' },
		{ color: '#8b5cf6', name: 'Purple' },
		{ color: '#06b6d4', name: 'Cyan' },
		{ color: '#ec4899', name: 'Pink' }
	];

	async function handleSave() {
		saving = true;
		try {
			const updated = await blueprintApi.updateState(blueprintId, nodeState.id, {
				name,
				color,
				is_initial: isInitial,
				is_terminal: isTerminal
			});
			onUpdate?.(updated);
			toast.success('Stage updated');
		} catch (error) {
			console.error('Failed to update stage:', error);
			toast.error('Failed to update stage');
		} finally {
			saving = false;
		}
	}

	async function handleDelete() {
		if (!confirm('Are you sure you want to delete this stage? This will also remove all connected transitions.')) {
			return;
		}
		onDelete?.();
	}
</script>

<div class="space-y-4">
	<!-- Header -->
	<div class="flex items-center justify-between border-b pb-3">
		<h3 class="font-semibold">Edit Stage</h3>
		<Button variant="ghost" size="icon" onclick={onClose}>
			<XIcon class="h-4 w-4" />
		</Button>
	</div>

	<!-- Form -->
	<div class="space-y-4">
		<!-- Name -->
		<div class="space-y-2">
			<Label for="stage-name">Name</Label>
			<Input
				id="stage-name"
				bind:value={name}
				disabled={readonly}
				placeholder="Stage name"
			/>
		</div>

		<!-- Color -->
		<div class="space-y-2">
			<Label>Color</Label>
			<div class="flex flex-wrap gap-2">
				{#each colorPresets as preset}
					<button
						type="button"
						class="h-8 w-8 rounded-full border-2 transition-transform hover:scale-110 {color === preset.color ? 'border-foreground ring-2 ring-offset-2' : 'border-transparent'}"
						style="background-color: {preset.color}"
						onclick={() => (color = preset.color)}
						title={preset.name}
						disabled={readonly}
					/>
				{/each}
			</div>
			<div class="flex items-center gap-2">
				<Input
					type="color"
					bind:value={color}
					class="h-8 w-16 cursor-pointer p-0"
					disabled={readonly}
				/>
				<Input
					bind:value={color}
					placeholder="#000000"
					class="flex-1 font-mono text-xs"
					disabled={readonly}
				/>
			</div>
		</div>

		<!-- Is Initial -->
		<div class="flex items-center justify-between">
			<div>
				<Label for="is-initial">Initial Stage</Label>
				<p class="text-xs text-muted-foreground">Records start in this stage</p>
			</div>
			<Switch
				id="is-initial"
				checked={isInitial}
				onCheckedChange={(checked) => (isInitial = checked)}
				disabled={readonly}
			/>
		</div>

		<!-- Is Terminal -->
		<div class="flex items-center justify-between">
			<div>
				<Label for="is-terminal">Terminal Stage</Label>
				<p class="text-xs text-muted-foreground">No transitions allowed from this stage</p>
			</div>
			<Switch
				id="is-terminal"
				checked={isTerminal}
				onCheckedChange={(checked) => (isTerminal = checked)}
				disabled={readonly}
			/>
		</div>

		<!-- Field Option Value -->
		{#if nodeState.field_option_value}
			<div class="rounded-lg bg-muted p-3">
				<p class="text-xs text-muted-foreground">Linked to field option:</p>
				<p class="font-mono text-sm">{nodeState.field_option_value}</p>
			</div>
		{/if}
	</div>

	<!-- Actions -->
	{#if !readonly}
		<div class="flex gap-2 border-t pt-4">
			<Button variant="destructive" size="sm" onclick={handleDelete}>
				<Trash2Icon class="mr-2 h-4 w-4" />
				Delete
			</Button>
			<Button class="flex-1" size="sm" onclick={handleSave} disabled={saving}>
				{saving ? 'Saving...' : 'Save Changes'}
			</Button>
		</div>
	{/if}
</div>
