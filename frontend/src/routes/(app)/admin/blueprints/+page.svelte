<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import type { Blueprint } from '$lib/api/blueprints';
	import * as blueprintApi from '$lib/api/blueprints';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Table from '$lib/components/ui/table';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Badge } from '$lib/components/ui/badge';
	import { toast } from 'svelte-sonner';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import SearchIcon from '@lucide/svelte/icons/search';
	import MoreHorizontalIcon from '@lucide/svelte/icons/more-horizontal';
	import PencilIcon from '@lucide/svelte/icons/pencil';
	import Trash2Icon from '@lucide/svelte/icons/trash-2';
	import CopyIcon from '@lucide/svelte/icons/copy';
	import WorkflowIcon from '@lucide/svelte/icons/workflow';

	let blueprints = $state<Blueprint[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');

	const filteredBlueprints = $derived(
		blueprints.filter(
			(b) =>
				b.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
				b.module?.name.toLowerCase().includes(searchQuery.toLowerCase())
		)
	);

	async function loadBlueprints() {
		loading = true;
		try {
			blueprints = await blueprintApi.getBlueprints();
		} catch (error) {
			console.error('Failed to load blueprints:', error);
			toast.error('Failed to load blueprints');
		} finally {
			loading = false;
		}
	}

	async function deleteBlueprint(id: number) {
		if (!confirm('Are you sure you want to delete this blueprint?')) return;

		try {
			await blueprintApi.deleteBlueprint(id);
			blueprints = blueprints.filter((b) => b.id !== id);
			toast.success('Blueprint deleted');
		} catch (error) {
			console.error('Failed to delete blueprint:', error);
			toast.error('Failed to delete blueprint');
		}
	}

	async function duplicateBlueprint(blueprint: Blueprint) {
		try {
			const newBlueprint = await blueprintApi.createBlueprint({
				name: `${blueprint.name} (Copy)`,
				module_id: blueprint.module_id,
				field_id: blueprint.field_id,
				description: blueprint.description || undefined
			});
			blueprints = [...blueprints, newBlueprint];
			toast.success('Blueprint duplicated');
			goto(`/admin/blueprints/${newBlueprint.id}`);
		} catch (error) {
			console.error('Failed to duplicate blueprint:', error);
			toast.error('Failed to duplicate blueprint');
		}
	}

	onMount(() => {
		loadBlueprints();
	});
</script>

<svelte:head>
	<title>Blueprints | Admin</title>
</svelte:head>

<div class="container mx-auto space-y-6 py-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Blueprints</h1>
			<p class="text-muted-foreground">
				Configure advanced stage transition automation with conditions, requirements, and actions.
			</p>
		</div>
		<Button href="/admin/blueprints/create">
			<PlusIcon class="mr-2 h-4 w-4" />
			Create Blueprint
		</Button>
	</div>

	<!-- Search -->
	<div class="relative max-w-md">
		<SearchIcon class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
		<Input
			bind:value={searchQuery}
			placeholder="Search blueprints..."
			class="pl-10"
		/>
	</div>

	<!-- Table -->
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading blueprints...</div>
		</div>
	{:else if filteredBlueprints.length === 0}
		<div class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12">
			<WorkflowIcon class="mb-4 h-12 w-12 text-muted-foreground" />
			{#if searchQuery}
				<p class="text-muted-foreground">No blueprints match your search</p>
			{:else}
				<p class="text-muted-foreground">No blueprints created yet</p>
				<Button href="/admin/blueprints/create" class="mt-4">
					<PlusIcon class="mr-2 h-4 w-4" />
					Create your first blueprint
				</Button>
			{/if}
		</div>
	{:else}
		<div class="rounded-lg border">
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Name</Table.Head>
						<Table.Head>Module</Table.Head>
						<Table.Head>Field</Table.Head>
						<Table.Head>States</Table.Head>
						<Table.Head>Transitions</Table.Head>
						<Table.Head>Status</Table.Head>
						<Table.Head class="w-[50px]"></Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each filteredBlueprints as blueprint}
						<Table.Row
							class="cursor-pointer"
							onclick={() => goto(`/admin/blueprints/${blueprint.id}`)}
						>
							<Table.Cell class="font-medium">{blueprint.name}</Table.Cell>
							<Table.Cell>{blueprint.module?.name || '-'}</Table.Cell>
							<Table.Cell>{blueprint.field?.label || '-'}</Table.Cell>
							<Table.Cell>{blueprint.states?.length || 0}</Table.Cell>
							<Table.Cell>{blueprint.transitions?.length || 0}</Table.Cell>
							<Table.Cell>
								{#if blueprint.is_active}
									<Badge variant="default" class="bg-green-500">Active</Badge>
								{:else}
									<Badge variant="secondary">Inactive</Badge>
								{/if}
							</Table.Cell>
							<Table.Cell>
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button
												{...props}
												variant="ghost"
												size="icon"
												onclick={(e: MouseEvent) => e.stopPropagation()}
											>
												<MoreHorizontalIcon class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => goto(`/admin/blueprints/${blueprint.id}`)}>
											<PencilIcon class="mr-2 h-4 w-4" />
											Edit
										</DropdownMenu.Item>
										<DropdownMenu.Item onclick={() => duplicateBlueprint(blueprint)}>
											<CopyIcon class="mr-2 h-4 w-4" />
											Duplicate
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										<DropdownMenu.Item
											class="text-destructive"
											onclick={() => deleteBlueprint(blueprint.id)}
										>
											<Trash2Icon class="mr-2 h-4 w-4" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		</div>
	{/if}
</div>