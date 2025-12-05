<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { getWorkflows, deleteWorkflow, toggleWorkflowActive, cloneWorkflow, type Workflow } from '$lib/api/workflows';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import Plus from 'lucide-svelte/icons/plus';
	import MoreVertical from 'lucide-svelte/icons/more-vertical';
	import Play from 'lucide-svelte/icons/play';
	import Pause from 'lucide-svelte/icons/pause';
	import Pencil from 'lucide-svelte/icons/pencil';
	import Copy from 'lucide-svelte/icons/copy';
	import Trash2 from 'lucide-svelte/icons/trash-2';
	import Zap from 'lucide-svelte/icons/zap';
	import Clock from 'lucide-svelte/icons/clock';
	import CheckCircle from 'lucide-svelte/icons/check-circle';
	import XCircle from 'lucide-svelte/icons/x-circle';

	let workflows = $state<Workflow[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		await loadWorkflows();
	});

	async function loadWorkflows() {
		try {
			loading = true;
			error = null;
			workflows = await getWorkflows();
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to load workflows';
		} finally {
			loading = false;
		}
	}

	async function handleToggleActive(workflow: Workflow) {
		try {
			const updated = await toggleWorkflowActive(workflow.id);
			workflows = workflows.map((w) => (w.id === workflow.id ? updated : w));
		} catch (e) {
			console.error('Failed to toggle workflow:', e);
		}
	}

	async function handleClone(workflow: Workflow) {
		try {
			const cloned = await cloneWorkflow(workflow.id);
			workflows = [...workflows, cloned];
		} catch (e) {
			console.error('Failed to clone workflow:', e);
		}
	}

	async function handleDelete(workflow: Workflow) {
		if (!confirm(`Are you sure you want to delete "${workflow.name}"?`)) {
			return;
		}
		try {
			await deleteWorkflow(workflow.id);
			workflows = workflows.filter((w) => w.id !== workflow.id);
		} catch (e) {
			console.error('Failed to delete workflow:', e);
		}
	}

	function getTriggerLabel(type: string): string {
		const labels: Record<string, string> = {
			record_created: 'Record Created',
			record_updated: 'Record Updated',
			record_deleted: 'Record Deleted',
			field_changed: 'Field Changed',
			time_based: 'Scheduled',
			webhook: 'Webhook',
			manual: 'Manual'
		};
		return labels[type] || type;
	}

	function getTriggerColor(type: string): string {
		const colors: Record<string, string> = {
			record_created: 'bg-green-100 text-green-800',
			record_updated: 'bg-blue-100 text-blue-800',
			record_deleted: 'bg-red-100 text-red-800',
			field_changed: 'bg-purple-100 text-purple-800',
			time_based: 'bg-orange-100 text-orange-800',
			webhook: 'bg-cyan-100 text-cyan-800',
			manual: 'bg-gray-100 text-gray-800'
		};
		return colors[type] || 'bg-gray-100 text-gray-800';
	}
</script>

<div class="container mx-auto py-6">
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Workflow Automation</h1>
			<p class="text-muted-foreground">
				Create automated workflows that trigger on events, schedules, or manual actions.
			</p>
		</div>
		<Button href="/admin/workflows/create">
			<Plus class="mr-2 h-4 w-4" />
			Create Workflow
		</Button>
	</div>

	{#if loading}
		<div class="flex h-64 items-center justify-center">
			<div class="text-muted-foreground">Loading workflows...</div>
		</div>
	{:else if error}
		<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
			{error}
		</div>
	{:else if workflows.length === 0}
		<Card>
			<CardContent class="flex flex-col items-center justify-center py-12">
				<Zap class="mb-4 h-12 w-12 text-muted-foreground" />
				<h3 class="mb-2 text-lg font-semibold">No workflows yet</h3>
				<p class="mb-4 text-center text-muted-foreground">
					Create your first workflow to automate tasks like sending emails, updating records, or
					calling webhooks.
				</p>
				<Button href="/admin/workflows/create">
					<Plus class="mr-2 h-4 w-4" />
					Create Your First Workflow
				</Button>
			</CardContent>
		</Card>
	{:else}
		<div class="grid gap-4">
			{#each workflows as workflow}
				<Card>
					<CardHeader class="pb-3">
						<div class="flex items-start justify-between">
							<div class="flex items-center gap-3">
								<div
									class="flex h-10 w-10 items-center justify-center rounded-lg {workflow.is_active
										? 'bg-green-100'
										: 'bg-gray-100'}"
								>
									<Zap
										class="h-5 w-5 {workflow.is_active ? 'text-green-600' : 'text-gray-400'}"
									/>
								</div>
								<div>
									<CardTitle class="flex items-center gap-2">
										{workflow.name}
										{#if workflow.is_active}
											<Badge variant="outline" class="border-green-500 text-green-600">
												Active
											</Badge>
										{:else}
											<Badge variant="outline" class="text-muted-foreground">Inactive</Badge>
										{/if}
									</CardTitle>
									<CardDescription>
										{workflow.description || 'No description'}
									</CardDescription>
								</div>
							</div>

							<DropdownMenu.Root>
								<DropdownMenu.Trigger>
									<Button variant="ghost" size="icon">
										<MoreVertical class="h-4 w-4" />
									</Button>
								</DropdownMenu.Trigger>
								<DropdownMenu.Content align="end">
									<DropdownMenu.Item onclick={() => goto(`/admin/workflows/${workflow.id}`)}>
										<Pencil class="mr-2 h-4 w-4" />
										Edit
									</DropdownMenu.Item>
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
										class="text-red-600"
										onclick={() => handleDelete(workflow)}
									>
										<Trash2 class="mr-2 h-4 w-4" />
										Delete
									</DropdownMenu.Item>
								</DropdownMenu.Content>
							</DropdownMenu.Root>
						</div>
					</CardHeader>
					<CardContent>
						<div class="flex flex-wrap items-center gap-4 text-sm">
							<!-- Trigger Type -->
							<div class="flex items-center gap-2">
								<span class="text-muted-foreground">Trigger:</span>
								<Badge variant="secondary" class={getTriggerColor(workflow.trigger_type)}>
									{getTriggerLabel(workflow.trigger_type)}
								</Badge>
							</div>

							<!-- Module -->
							{#if workflow.module}
								<div class="flex items-center gap-2">
									<span class="text-muted-foreground">Module:</span>
									<span>{workflow.module.name}</span>
								</div>
							{/if}

							<!-- Steps -->
							<div class="flex items-center gap-2">
								<span class="text-muted-foreground">Steps:</span>
								<span>{workflow.steps?.length || 0}</span>
							</div>

							<!-- Stats -->
							<div class="ml-auto flex items-center gap-4 text-muted-foreground">
								<div class="flex items-center gap-1" title="Total executions">
									<Clock class="h-4 w-4" />
									{workflow.execution_count}
								</div>
								<div class="flex items-center gap-1 text-green-600" title="Successful">
									<CheckCircle class="h-4 w-4" />
									{workflow.success_count}
								</div>
								<div class="flex items-center gap-1 text-red-600" title="Failed">
									<XCircle class="h-4 w-4" />
									{workflow.failure_count}
								</div>
							</div>
						</div>
					</CardContent>
				</Card>
			{/each}
		</div>
	{/if}
</div>
