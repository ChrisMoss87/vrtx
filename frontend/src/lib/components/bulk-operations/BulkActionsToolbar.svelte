<script lang="ts">
	import { createEventDispatcher } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Badge } from '$lib/components/ui/badge';
	import {
		Edit,
		Trash2,
		Download,
		Upload,
		UserPlus,
		Tag,
		ChevronDown,
		X,
		MoreHorizontal,
		Copy
	} from 'lucide-svelte';

	interface Props {
		selectedCount: number;
		moduleName: string;
		showImport?: boolean;
		showExport?: boolean;
		showBulkEdit?: boolean;
		showBulkDelete?: boolean;
		showBulkAssign?: boolean;
		showBulkTag?: boolean;
		onClearSelection?: () => void;
		onBulkEdit?: () => void;
		onBulkDelete?: () => void;
		onBulkAssign?: () => void;
		onBulkTag?: () => void;
		onImport?: () => void;
		onExport?: () => void;
	}

	let {
		selectedCount,
		moduleName,
		showImport = true,
		showExport = true,
		showBulkEdit = true,
		showBulkDelete = true,
		showBulkAssign = false,
		showBulkTag = false,
		onClearSelection,
		onBulkEdit,
		onBulkDelete,
		onBulkAssign,
		onBulkTag,
		onImport,
		onExport
	}: Props = $props();

	const dispatch = createEventDispatcher();

	const hasSelection = $derived(selectedCount > 0);
</script>

{#if hasSelection}
	<!-- Selection Actions Bar -->
	<div class="flex items-center gap-3 p-3 bg-primary/5 border rounded-lg">
		<div class="flex items-center gap-2">
			<Badge variant="secondary" class="text-sm">
				{selectedCount} selected
			</Badge>
			<Button variant="ghost" size="sm" class="h-7 px-2" onclick={onClearSelection}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<div class="h-4 w-px bg-border"></div>

		<div class="flex items-center gap-2">
			{#if showBulkEdit}
				<Button
					variant="outline"
					size="sm"
					onclick={() => {
						onBulkEdit?.();
						dispatch('bulkEdit');
					}}
				>
					<Edit class="mr-2 h-4 w-4" />
					Edit
				</Button>
			{/if}

			{#if showBulkDelete}
				<Button
					variant="outline"
					size="sm"
					class="text-destructive hover:text-destructive"
					onclick={() => {
						onBulkDelete?.();
						dispatch('bulkDelete');
					}}
				>
					<Trash2 class="mr-2 h-4 w-4" />
					Delete
				</Button>
			{/if}

			{#if showBulkAssign || showBulkTag || showExport}
				<DropdownMenu.Root>
					<DropdownMenu.Trigger>
						{#snippet child({ props })}
							<Button variant="outline" size="sm" {...props}>
								<MoreHorizontal class="mr-2 h-4 w-4" />
								More
								<ChevronDown class="ml-2 h-4 w-4" />
							</Button>
						{/snippet}
					</DropdownMenu.Trigger>
					<DropdownMenu.Content align="end">
						{#if showBulkAssign}
							<DropdownMenu.Item
								onclick={() => {
									onBulkAssign?.();
									dispatch('bulkAssign');
								}}
							>
								<UserPlus class="mr-2 h-4 w-4" />
								Assign to User
							</DropdownMenu.Item>
						{/if}

						{#if showBulkTag}
							<DropdownMenu.Item
								onclick={() => {
									onBulkTag?.();
									dispatch('bulkTag');
								}}
							>
								<Tag class="mr-2 h-4 w-4" />
								Add Tags
							</DropdownMenu.Item>
						{/if}

						{#if showExport}
							<DropdownMenu.Separator />
							<DropdownMenu.Item
								onclick={() => {
									onExport?.();
									dispatch('export');
								}}
							>
								<Download class="mr-2 h-4 w-4" />
								Export Selected
							</DropdownMenu.Item>
						{/if}
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			{/if}
		</div>
	</div>
{:else}
	<!-- Regular Actions Bar -->
	<div class="flex items-center gap-2">
		{#if showImport}
			<Button
				variant="outline"
				size="sm"
				onclick={() => {
					onImport?.();
					dispatch('import');
				}}
			>
				<Upload class="mr-2 h-4 w-4" />
				Import
			</Button>
		{/if}

		{#if showExport}
			<Button
				variant="outline"
				size="sm"
				onclick={() => {
					onExport?.();
					dispatch('export');
				}}
			>
				<Download class="mr-2 h-4 w-4" />
				Export
			</Button>
		{/if}
	</div>
{/if}
