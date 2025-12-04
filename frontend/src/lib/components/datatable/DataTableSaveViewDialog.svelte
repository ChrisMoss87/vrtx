<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Checkbox } from '$lib/components/ui/checkbox';

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
		onSaved?: () => void;
	}

	let { open = $bindable(false), module, currentState, onOpenChange, onSaved }: Props = $props();

	let name = $state('');
	let description = $state('');
	let isDefault = $state(false);
	let isPublic = $state(false);
	let saving = $state(false);

	async function saveView() {
		if (!name.trim()) {
			return;
		}

		saving = true;
		try {
			const response = await fetch('/api/table-views', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN':
						document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
				},
				body: JSON.stringify({
					name: name.trim(),
					module,
					description: description.trim() || null,
					filters: currentState.filters || null,
					sorting: currentState.sorting || null,
					column_visibility: currentState.columnVisibility || null,
					column_order: currentState.columnOrder || null,
					column_widths: currentState.columnWidths || null,
					page_size: currentState.pageSize || 50,
					is_default: isDefault,
					is_public: isPublic
				})
			});

			if (response.ok) {
				// Reset form
				name = '';
				description = '';
				isDefault = false;
				isPublic = false;
				open = false;
				onSaved?.();
			}
		} catch (error) {
			console.error('Failed to save view:', error);
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
			isPublic = false;
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
				<Checkbox id="is_public" bind:checked={isPublic} />
				<Label
					for="is_public"
					class="text-sm leading-none font-normal peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
				>
					Make this view public (visible to all users)
				</Label>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" on:click={() => handleOpenChange(false)}>Cancel</Button>
			<Button on:click={saveView} disabled={!name.trim() || saving}>
				{saving ? 'Saving...' : 'Save View'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
