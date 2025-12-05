<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import {
		Plus,
		Search,
		MoreVertical,
		Play,
		Pause,
		Copy,
		Trash2,
		Settings,
		History,
		Zap,
		Clock,
		CheckCircle,
		XCircle,
		AlertCircle
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getWorkflows,
		deleteWorkflow,
		toggleWorkflowActive,
		cloneWorkflow,
		type Workflow,
		type TriggerType
	} from '$lib/api/workflows';
	import { getModules, type Module } from '$lib/api/modules';

	let workflows = $state<Workflow[]>([]);
	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let selectedModule = $state<string>('all');
	let selectedStatus = $state<string>('all');
	let deleteDialogOpen = $state(false);
	let workflowToDelete = $state<Workflow | null>(null);

	const triggerTypeLabels: Record<TriggerType, string> = {
		record_created: 'Record Created',
		record_updated: 'Record Updated',
		record_deleted: 'Record Deleted',
		field_changed: 'Field Changed',
		time_based: 'Scheduled',
		webhook: 'Webhook',
		manual: 'Manual',
		record_saved: 'Record Saved',
		related_created: 'Related Created',
		related_updated: 'Related Updated',
		record_converted: 'Record Converted'
	};

	const triggerTypeIcons: Record<TriggerType, typeof Zap> = {
		record_created: Plus,
		record_updated: Settings,
		record_deleted: Trash2,
		field_changed: Zap,
		time_based: Clock,
		webhook: Zap,
		manual: Play,
		record_saved: CheckCircle,
		related_created: Plus,
		related_updated: Settings,
		record_converted: Zap
	};

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [workflowData, moduleData] = await Promise.all([getWorkflows(), getModules()]);
			workflows = workflowData;
			modules = moduleData;
		} catch (error) {
			console.error('Failed to load workflows:', error);
			toast.error('Failed to load workflows');
		} finally {
			loading = false;
		}
	}

	const filteredWorkflows = $derived(() => {
		let result = workflows;

		// Filter by search query
		if (searchQuery) {
			const query = searchQuery.toLowerCase();
			result = result.filter(
				(w) =>
					w.name.toLowerCase().includes(query) ||
					w.description?.toLowerCase().includes(query) ||
					w.module?.name.toLowerCase().includes(query)
			);
		}

		// Filter by module
		if (selectedModule !== 'all') {
			result = result.filter((w) => String(w.module_id) === selectedModule);
		}

		// Filter by status
		if (selectedStatus !== 'all') {
			if (selectedStatus === 'active') {
				result = result.filter((w) => w.is_active);
			} else if (selectedStatus === 'inactive') {
				result = result.filter((w) => !w.is_active);
			}
		}

		return result;
	});

	async function handleToggleActive(workflow: Workflow) {
		try {
			const updated = await toggleWorkflowActive(workflow.id);
			workflows = workflows.map((w) => (w.id === updated.id ? updated : w));
			toast.success(updated.is_active ? 'Workflow activated' : 'Workflow deactivated');
		} catch (error) {
			console.error('Failed to toggle workflow:', error);
			toast.error('Failed to toggle workflow status');
		}
	}

	async function handleClone(workflow: Workflow) {
		try {
			const cloned = await cloneWorkflow(workflow.id);
			workflows = [...workflows, cloned];
			toast.success('Workflow cloned successfully');
		} catch (error) {
			console.error('Failed to clone workflow:', error);
			toast.error('Failed to clone workflow');
		}
	}

	async function handleDelete() {
		if (!workflowToDelete) return;

		try {
			await deleteWorkflow(workflowToDelete.id);
			workflows = workflows.filter((w) => w.id !== workflowToDelete!.id);
			toast.success('Workflow deleted successfully');
			deleteDialogOpen = false;
			workflowToDelete = null;
		} catch (error) {
			console.error('Failed to delete workflow:', error);
			toast.error('Failed to delete workflow');
		}
	}

	function getSuccessRate(workflow: Workflow): number {
		if (workflow.execution_count === 0) return 0;
		return Math.round((workflow.success_count / workflow.execution_count) * 100);
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return 'Never';
		return new Date(dateString).toLocaleString();
	}
</script>

<svelte:head>
	<title>Workflows | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Workflows</h1>
			<p class="text-muted-foreground">Automate actions based on record events and schedules</p>
		</div>
		<Button onclick={() => goto('/workflows/new')}>
			<Plus class="mr-2 h-4 w-4" />
			Create Workflow
		</Button>
	</div>

	<!-- Filters -->
	<div class="mb-6 flex flex-wrap items-center gap-4">
		<div class="relative flex-1">
			<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
			<Input
				type="search"
				placeholder="Search workflows..."
				class="pl-9"
				bind:value={searchQuery}
			/>
		</div>

		<Select.Root type="single" bind:value={selectedModule}>
			<Select.Trigger class="w-[180px]">
				{selectedModule === 'all' ? 'All Modules' : modules.find((m) => String(m.id) === selectedModule)?.name || 'Select Module'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="all">All Modules</Select.Item>
				{#each modules as module}
					<Select.Item value={String(module.id)}>{module.name}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>

		<Select.Root type="single" bind:value={selectedStatus}>
			<Select.Trigger class="w-[150px]">
				{selectedStatus === 'all' ? 'All Status' : selectedStatus === 'active' ? 'Active' : 'Inactive'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="all">All Status</Select.Item>
				<Select.Item value="active">Active</Select.Item>
				<Select.Item value="inactive">Inactive</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Workflow List -->
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading workflows...</div>
		</div>
	{:else if filteredWorkflows().length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<Zap class="mb-4 h-12 w-12 text-muted-foreground" />
				<h3 class="mb-2 text-lg font-medium">No workflows found</h3>
				<p class="mb-4 text-muted-foreground">
					{searchQuery || selectedModule !== 'all' || selectedStatus !== 'all'
						? 'Try adjusting your filters'
						: 'Create your first workflow to automate tasks'}
				</p>
				{#if !searchQuery && selectedModule === 'all' && selectedStatus === 'all'}
					<Button onclick={() => goto('/workflows/new')}>
						<Plus class="mr-2 h-4 w-4" />
						Create Workflow
					</Button>
				{/if}
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="grid gap-4">
			{#each filteredWorkflows() as workflow (workflow.id)}
				{@const TriggerIcon = triggerTypeIcons[workflow.trigger_type] || Zap}
				<Card.Root class="transition-shadow hover:shadow-md">
					<Card.Content class="p-6">
						<div class="flex items-start justify-between">
							<div class="flex items-start gap-4">
								<div
									class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10"
								>
									<TriggerIcon class="h-5 w-5 text-primary" />
								</div>
								<div>
									<div class="flex items-center gap-2">
										<h3 class="font-medium">
											<a
												href="/workflows/{workflow.id}"
												class="hover:underline"
											>
												{workflow.name}
											</a>
										</h3>
										<Badge variant={workflow.is_active ? 'default' : 'secondary'}>
											{workflow.is_active ? 'Active' : 'Inactive'}
										</Badge>
									</div>
									{#if workflow.description}
										<p class="mt-1 text-sm text-muted-foreground">
											{workflow.description}
										</p>
									{/if}
									<div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
										<span class="flex items-center gap-1">
											<TriggerIcon class="h-3.5 w-3.5" />
											{triggerTypeLabels[workflow.trigger_type]}
										</span>
										{#if workflow.module}
											<span>Module: {workflow.module.name}</span>
										{/if}
										<span>Priority: {workflow.priority}</span>
									</div>
								</div>
							</div>

							<div class="flex items-center gap-4">
								<!-- Stats -->
								<div class="flex items-center gap-6 text-sm">
									<div class="text-center">
										<div class="font-medium">{workflow.execution_count}</div>
										<div class="text-xs text-muted-foreground">Runs</div>
									</div>
									<div class="text-center">
										<div class="flex items-center gap-1 font-medium">
											{#if workflow.execution_count > 0}
												{#if getSuccessRate(workflow) >= 90}
													<CheckCircle class="h-3.5 w-3.5 text-green-500" />
												{:else if getSuccessRate(workflow) >= 50}
													<AlertCircle class="h-3.5 w-3.5 text-yellow-500" />
												{:else}
													<XCircle class="h-3.5 w-3.5 text-red-500" />
												{/if}
												{getSuccessRate(workflow)}%
											{:else}
												-
											{/if}
										</div>
										<div class="text-xs text-muted-foreground">Success</div>
									</div>
									<div class="text-center">
										<div class="font-medium">
											{workflow.last_run_at ? formatDate(workflow.last_run_at).split(',')[0] : '-'}
										</div>
										<div class="text-xs text-muted-foreground">Last Run</div>
									</div>
								</div>

								<!-- Actions -->
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button variant="ghost" size="icon" {...props}>
												<MoreVertical class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => goto(`/workflows/${workflow.id}`)}>
											<Settings class="mr-2 h-4 w-4" />
											Edit
										</DropdownMenu.Item>
										<DropdownMenu.Item
											onclick={() => goto(`/workflows/${workflow.id}/executions`)}
										>
											<History class="mr-2 h-4 w-4" />
											View History
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										<DropdownMenu.Item onclick={() => handleToggleActive(workflow)}>
											{#if workflow.is_active}
												<Pause class="mr-2 h-4 w-4" />
												Deactivate
											{:else}
												<Play class="mr-2 h-4 w-4" />
												Activate
											{/if}
										</DropdownMenu.Item>
										<DropdownMenu.Item onclick={() => handleClone(workflow)}>
											<Copy class="mr-2 h-4 w-4" />
											Clone
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										<DropdownMenu.Item
											class="text-destructive focus:text-destructive"
											onclick={() => {
												workflowToDelete = workflow;
												deleteDialogOpen = true;
											}}
										>
											<Trash2 class="mr-2 h-4 w-4" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</div>
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{/if}
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Workflow</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{workflowToDelete?.name}"? This action cannot be undone.
				All execution history will also be deleted.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleDelete}>Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
