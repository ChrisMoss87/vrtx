<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { MoreHorizontal, Eye, Pencil, Copy, Trash2 } from 'lucide-svelte';
	import { router } from '@sveltejs/kit';
	import { toast } from 'svelte-sonner';

	interface Props {
		row: any;
		moduleApiName: string;
		onEdit?: (row: any) => void;
		onDelete?: (row: any) => void;
		onDuplicate?: (row: any) => void;
		showView?: boolean;
		showEdit?: boolean;
		showDuplicate?: boolean;
		showDelete?: boolean;
	}

	let {
		row,
		moduleApiName,
		onEdit,
		onDelete,
		onDuplicate,
		showView = true,
		showEdit = true,
		showDuplicate = true,
		showDelete = true,
	}: Props = $props();

	function handleView() {
		router.visit(`/modules/${moduleApiName}/${row.id}`);
	}

	function handleEdit() {
		if (onEdit) {
			onEdit(row);
		} else {
			router.visit(`/modules/${moduleApiName}/${row.id}/edit`);
		}
	}

	function handleDuplicate() {
		if (onDuplicate) {
			onDuplicate(row);
		} else {
			// Default duplicate behavior - navigate to create with prefilled data
			router.visit(`/modules/${moduleApiName}/create`, {
				data: { duplicate: row.id },
			});
		}
	}

	function handleDelete() {
		if (onDelete) {
			onDelete(row);
		} else {
			// Default delete behavior with confirmation
			if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
				router.delete(`/api/modules/${moduleApiName}/records/${row.id}`, {
					onSuccess: () => {
						toast.success('Record deleted successfully');
						router.reload({ only: ['data'] });
					},
					onError: () => {
						toast.error('Failed to delete record');
					},
				});
			}
		}
	}
</script>

<DropdownMenu.Root>
	<DropdownMenu.Trigger >
		<Button
			variant="ghost"
			size="icon"
			class="h-8 w-8 p-0"
			onclick={(e) => e.stopPropagation()}
		>
			<span class="sr-only">Open menu</span>
			<MoreHorizontal class="h-4 w-4" />
		</Button>
	</DropdownMenu.Trigger>
	<DropdownMenu.Content align="end" class="w-40">
		<DropdownMenu.Group>
			{#if showView}
				<DropdownMenu.Item onclick={handleView}>
					<Eye class="mr-2 h-4 w-4" />
					<span>View</span>
				</DropdownMenu.Item>
			{/if}
			{#if showEdit}
				<DropdownMenu.Item onclick={handleEdit}>
					<Pencil class="mr-2 h-4 w-4" />
					<span>Edit</span>
				</DropdownMenu.Item>
			{/if}
			{#if showDuplicate}
				<DropdownMenu.Item onclick={handleDuplicate}>
					<Copy class="mr-2 h-4 w-4" />
					<span>Duplicate</span>
				</DropdownMenu.Item>
			{/if}
		</DropdownMenu.Group>
		{#if showDelete}
			<DropdownMenu.Separator />
			<DropdownMenu.Item onclick={handleDelete} class="text-destructive focus:text-destructive">
				<Trash2 class="mr-2 h-4 w-4" />
				<span>Delete</span>
			</DropdownMenu.Item>
		{/if}
	</DropdownMenu.Content>
</DropdownMenu.Root>
