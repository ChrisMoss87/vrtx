<script lang="ts">
	import * as Drawer from '$lib/components/ui/drawer';
	import { Button } from '$lib/components/ui/button';
	import { Separator } from '$lib/components/ui/separator';
	import {
		Eye,
		Pencil,
		Trash2,
		Copy,
		MoreHorizontal,
		ExternalLink
	} from 'lucide-svelte';
	import type { BaseRowData } from './types';

	interface Action {
		id: string;
		label: string;
		icon?: typeof Eye;
		variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost';
		disabled?: boolean;
	}

	interface Props {
		open: boolean;
		record: BaseRowData | null;
		recordTitle?: string;
		actions?: Action[];
		onOpenChange: (open: boolean) => void;
		onAction: (action: string, record: BaseRowData) => void;
	}

	let {
		open = $bindable(false),
		record,
		recordTitle = 'Record',
		actions = [],
		onOpenChange,
		onAction
	}: Props = $props();

	// Default actions if none provided
	const defaultActions: Action[] = [
		{ id: 'view', label: 'View Details', icon: Eye },
		{ id: 'edit', label: 'Edit', icon: Pencil },
		{ id: 'duplicate', label: 'Duplicate', icon: Copy },
		{ id: 'delete', label: 'Delete', icon: Trash2, variant: 'destructive' }
	];

	const effectiveActions = $derived(actions.length > 0 ? actions : defaultActions);

	function handleAction(actionId: string) {
		if (record) {
			onAction(actionId, record);
		}
		onOpenChange(false);
	}
</script>

<Drawer.Root bind:open onOpenChange={onOpenChange}>
	<Drawer.Portal>
		<Drawer.Overlay class="fixed inset-0 bg-black/40" />
		<Drawer.Content
			class="fixed inset-x-0 bottom-0 mt-24 flex h-auto max-h-[85vh] flex-col rounded-t-xl bg-background"
		>
			<!-- Handle -->
			<div class="mx-auto mt-4 h-1.5 w-12 flex-shrink-0 rounded-full bg-muted" />

			<!-- Header -->
			<Drawer.Header class="px-4 pb-2">
				<Drawer.Title class="text-lg font-semibold text-center">
					{recordTitle}
				</Drawer.Title>
				{#if record?.id}
					<Drawer.Description class="text-sm text-muted-foreground text-center">
						ID: {record.id}
					</Drawer.Description>
				{/if}
			</Drawer.Header>

			<Separator />

			<!-- Actions -->
			<div class="flex-1 overflow-y-auto px-4 py-4">
				<div class="space-y-2">
					{#each effectiveActions as action (action.id)}
						{@const Icon = action.icon}
						<Button
							variant={action.variant || 'ghost'}
							class="w-full justify-start h-12 text-base {action.variant === 'destructive' ? 'text-destructive hover:text-destructive hover:bg-destructive/10' : ''}"
							disabled={action.disabled}
							onclick={() => handleAction(action.id)}
						>
							{#if Icon}
								<Icon class="h-5 w-5 mr-3" />
							{/if}
							{action.label}
						</Button>
					{/each}
				</div>
			</div>

			<!-- Footer -->
			<Drawer.Footer class="px-4 pb-6">
				<Button variant="outline" class="w-full h-12" onclick={() => onOpenChange(false)}>
					Cancel
				</Button>
			</Drawer.Footer>
		</Drawer.Content>
	</Drawer.Portal>
</Drawer.Root>
