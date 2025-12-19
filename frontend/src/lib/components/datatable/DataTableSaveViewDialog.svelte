<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { createView, type CreateViewRequest, type ModuleView } from '$lib/api/views';
	import { toast } from 'svelte-sonner';

	interface Props {
		open?: boolean;
		module: string;
		currentState: {
			filters?: any;
			sorting?: any;
			columnVisibility?: Record<string, boolean>;
			columnOrder?: string[];
			columnWidths?: Record<string, number>;
			pageSize?: number;
		};
		onOpenChange?: (open: boolean) => void;
		onSaved?: (view: ModuleView) => void;
	}

	let { open = $bindable(false), module, currentState, onOpenChange, onSaved }: Props = $props();

	let name = $state('');
	let description = $state('');
	let isDefault = $state(false);
	let isShared = $state(false);
	let saving = $state(false);
	let error = $state('');

	async function handleSaveView() {
		if (!name.trim()) {
			error = 'Please enter a view name';
			return;
		}

		saving = true;
		error = '';

		try {
			const viewData: CreateViewRequest = {
				name: name.trim(),
				description: description.trim() || undefined,
				filters: currentState.filters || [],
				sorting: currentState.sorting || [],
				column_visibility: currentState.columnVisibility || {},
				column_order: currentState.columnOrder || [],
				column_widths: currentState.columnWidths || {},
				page_size: currentState.pageSize || 50,
				is_default: isDefault,
				is_shared: isShared
			};

			const view = await createView(module, viewData);

			// Reset form
			name = '';
			description = '';
			isDefault = false;
			isShared = false;
			open = false;

			toast.success('View saved', {
				description: `"${view.name}" has been saved successfully.`
			});

			onSaved?.(view);
		} catch (err: any) {
			console.error('Failed to save view:', err);
			error = err.response?.data?.message || err.message || 'Failed to save view';
			toast.error('Failed to save view', {
				description: error
			});
		} finally {
			saving = false;
		}
	}

	function handleOpenChange(newOpen: boolean) {
		open = newOpen;
		onOpenChange?.(newOpen);

		// Reset form when closing
		if (!newOpen) {
			name = '';
			description = '';
			isDefault = false;
			isShared = false;
			error = '';
		}
	}
</script>

<Dialog.Root {open} onOpenChange={handleOpenChange}>
	<Dialog.Content class="sm:max-w-[425px]">
		<Dialog.Header>
			<Dialog.Title>Save View</Dialog.Title>
			<Dialog.Description>
				Save your current table configuration as a reusable view.
			</Dialog.Description>
		</Dialog.Header>
		<div class="grid gap-4 py-4">
			{#if error}
				<div class="text-sm text-destructive">{error}</div>
			{/if}
			<div class="grid gap-2">
				<Label for="name">View Name</Label>
				<Input id="name" bind:value={name} placeholder="My Custom View" autocomplete="off" />
			</div>
			<div class="grid gap-2">
				<Label for="description">Description (optional)</Label>
				<Textarea
					id="description"
					bind:value={description}
					placeholder="Describe what this view is for..."
					rows={3}
				/>
			</div>
			<div class="flex items-center space-x-2">
				<Checkbox id="is_default" bind:checked={isDefault} />
				<Label
					for="is_default"
					class="text-sm leading-none font-normal peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
				>
					Set as my default view
				</Label>
			</div>
			<div class="flex items-center space-x-2">
				<Checkbox id="is_shared" bind:checked={isShared} />
				<Label
					for="is_shared"
					class="text-sm leading-none font-normal peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
				>
					Share this view with other users
				</Label>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => handleOpenChange(false)}>Cancel</Button>
			<Button onclick={handleSaveView} disabled={!name.trim() || saving}>
				{saving ? 'Saving...' : 'Save View'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
