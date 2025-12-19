<script lang="ts">
	import { createEventDispatcher } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Dialog from '$lib/components/ui/dialog';
	import { AlertTriangle, Loader2, Trash2 } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';

	interface Props {
		open: boolean;
		moduleName: string;
		selectedRecordIds: number[];
		onBulkDelete: () => Promise<void>;
		onClose: () => void;
	}

	let {
		open = $bindable(),
		moduleName,
		selectedRecordIds,
		onBulkDelete,
		onClose
	}: Props = $props();

	const dispatch = createEventDispatcher();

	let confirmText = $state('');
	let isDeleting = $state(false);

	const expectedConfirmText = $derived(`delete ${selectedRecordIds.length}`);
	const isConfirmed = $derived(confirmText.toLowerCase() === expectedConfirmText);

	async function handleDelete() {
		if (!isConfirmed) return;

		isDeleting = true;
		try {
			await onBulkDelete();
			toast.success(`Deleted ${selectedRecordIds.length} records`);
			handleClose();
		} catch (error) {
			console.error('Bulk delete failed:', error);
			toast.error('Failed to delete records');
		} finally {
			isDeleting = false;
		}
	}

	function handleClose() {
		confirmText = '';
		open = false;
		onClose();
		dispatch('close');
	}

	function handleOpenChange(isOpen: boolean) {
		if (!isOpen) {
			handleClose();
		}
	}
</script>

<Dialog.Root bind:open onOpenChange={handleOpenChange}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title class="text-destructive flex items-center gap-2">
				<AlertTriangle class="h-5 w-5" />
				Delete {selectedRecordIds.length} Records
			</Dialog.Title>
			<Dialog.Description>
				This action cannot be undone.
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<!-- Warning -->
			<div class="flex items-start gap-3 rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-900 p-4">
				<AlertTriangle class="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" />
				<div class="text-sm">
					<p class="font-medium text-red-800 dark:text-red-200">
						Warning: Permanent deletion
					</p>
					<p class="text-red-700 dark:text-red-300 mt-1">
						You are about to permanently delete <strong>{selectedRecordIds.length}</strong> {moduleName} records.
						This action cannot be undone and all associated data will be lost.
					</p>
				</div>
			</div>

			<!-- Confirmation Input -->
			<div class="space-y-2">
				<Label class="text-sm">
					Type <strong class="font-mono">{expectedConfirmText}</strong> to confirm
				</Label>
				<Input
					bind:value={confirmText}
					placeholder={expectedConfirmText}
					class="font-mono"
					autocomplete="off"
				/>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={handleClose} disabled={isDeleting}>
				Cancel
			</Button>
			<Button
				variant="destructive"
				onclick={handleDelete}
				disabled={!isConfirmed || isDeleting}
			>
				{#if isDeleting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					Deleting...
				{:else}
					<Trash2 class="mr-2 h-4 w-4" />
					Delete Records
				{/if}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
