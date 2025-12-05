<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Table from '$lib/components/ui/table';
	import {
		ArrowLeft,
		RefreshCw,
		CheckCircle,
		XCircle,
		Clock,
		AlertCircle,
		Loader2,
		ChevronRight
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getWorkflow,
		getWorkflowExecutions,
		type Workflow,
		type WorkflowExecution
	} from '$lib/api/workflows';

	let workflow = $state<Workflow | null>(null);
	let executions = $state<WorkflowExecution[]>([]);
	let loading = $state(true);
	let currentPage = $state(1);
	let totalPages = $state(1);
	let statusFilter = $state<string>('all');

	const workflowId = $derived(parseInt($page.params.id || '0'));

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [wf, execs] = await Promise.all([
				getWorkflow(workflowId),
				getWorkflowExecutions(workflowId, {
					status: statusFilter !== 'all' ? (statusFilter as WorkflowExecution['status']) : undefined,
					page: currentPage,
					per_page: 20
				})
			]);
			workflow = wf;
			executions = execs.data;
			totalPages = execs.last_page;
		} catch (error) {
			console.error('Failed to load executions:', error);
			toast.error('Failed to load execution history');
		} finally {
			loading = false;
		}
	}

	function getStatusIcon(status: WorkflowExecution['status']) {
		switch (status) {
			case 'completed':
				return CheckCircle;
			case 'failed':
				return XCircle;
			case 'running':
				return Loader2;
			case 'pending':
			case 'queued':
				return Clock;
			case 'cancelled':
				return AlertCircle;
			default:
				return Clock;
		}
	}

	function getStatusColor(status: WorkflowExecution['status']) {
		switch (status) {
			case 'completed':
				return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
			case 'failed':
				return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
			case 'running':
				return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
			case 'cancelled':
				return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
			default:
				return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
		}
	}

	function formatDuration(ms: number | null): string {
		if (!ms) return '-';
		if (ms < 1000) return `${ms}ms`;
		if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
		return `${(ms / 60000).toFixed(1)}m`;
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return '-';
		return new Date(dateString).toLocaleString();
	}

	async function handleStatusChange(value: string) {
		statusFilter = value;
		currentPage = 1;
		await loadData();
	}

	async function handlePageChange(newPage: number) {
		currentPage = newPage;
		await loadData();
	}
</script>

<svelte:head>
	<title>Execution History | {workflow?.name || 'Workflow'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto(`/workflows/${workflowId}`)}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">Execution History</h1>
				<p class="text-muted-foreground">{workflow?.name || 'Loading...'}</p>
			</div>
		</div>
		<div class="flex items-center gap-2">
			<Select.Root type="single" value={statusFilter} onValueChange={handleStatusChange}>
				<Select.Trigger class="w-[150px]">
					{statusFilter === 'all' ? 'All Status' : statusFilter}
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="all">All Status</Select.Item>
					<Select.Item value="completed">Completed</Select.Item>
					<Select.Item value="failed">Failed</Select.Item>
					<Select.Item value="running">Running</Select.Item>
					<Select.Item value="pending">Pending</Select.Item>
					<Select.Item value="cancelled">Cancelled</Select.Item>
				</Select.Content>
			</Select.Root>
			<Button variant="outline" onclick={loadData} disabled={loading}>
				<RefreshCw class="mr-2 h-4 w-4 {loading ? 'animate-spin' : ''}" />
				Refresh
			</Button>
		</div>
	</div>

	<!-- Stats Cards -->
	{#if workflow}
		<div class="mb-6 grid gap-4 sm:grid-cols-4">
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="text-2xl font-bold">{workflow.execution_count}</div>
					<p class="text-xs text-muted-foreground">Total Executions</p>
				</Card.Content>
			</Card.Root>
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="text-2xl font-bold text-green-600">{workflow.success_count}</div>
					<p class="text-xs text-muted-foreground">Successful</p>
				</Card.Content>
			</Card.Root>
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="text-2xl font-bold text-red-600">{workflow.failure_count}</div>
					<p class="text-xs text-muted-foreground">Failed</p>
				</Card.Content>
			</Card.Root>
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="text-2xl font-bold">
						{workflow.execution_count > 0
							? Math.round((workflow.success_count / workflow.execution_count) * 100)
							: 0}%
					</div>
					<p class="text-xs text-muted-foreground">Success Rate</p>
				</Card.Content>
			</Card.Root>
		</div>
	{/if}

	<!-- Executions Table -->
	<Card.Root>
		<Card.Content class="p-0">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else if executions.length === 0}
				<div class="flex flex-col items-center justify-center py-12">
					<Clock class="mb-4 h-12 w-12 text-muted-foreground" />
					<h3 class="mb-2 text-lg font-medium">No executions yet</h3>
					<p class="text-muted-foreground">
						{statusFilter !== 'all'
							? `No ${statusFilter} executions found`
							: 'This workflow has not been triggered yet'}
					</p>
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Status</Table.Head>
							<Table.Head>Trigger</Table.Head>
							<Table.Head>Started</Table.Head>
							<Table.Head>Duration</Table.Head>
							<Table.Head>Steps</Table.Head>
							<Table.Head></Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each executions as execution}
							{@const StatusIcon = getStatusIcon(execution.status)}
							<Table.Row class="cursor-pointer hover:bg-muted/50">
								<Table.Cell>
									<Badge class={getStatusColor(execution.status)}>
										<StatusIcon
											class="mr-1 h-3 w-3 {execution.status === 'running' ? 'animate-spin' : ''}"
										/>
										{execution.status}
									</Badge>
								</Table.Cell>
								<Table.Cell>
									<span class="capitalize">{execution.trigger_type.replace('_', ' ')}</span>
									{#if execution.trigger_record_id}
										<span class="ml-1 text-xs text-muted-foreground">
											#{execution.trigger_record_id}
										</span>
									{/if}
								</Table.Cell>
								<Table.Cell>
									{formatDate(execution.started_at || execution.created_at)}
								</Table.Cell>
								<Table.Cell>{formatDuration(execution.duration_ms)}</Table.Cell>
								<Table.Cell>
									<div class="flex items-center gap-1 text-sm">
										<span class="text-green-600">{execution.steps_completed}</span>
										<span class="text-muted-foreground">/</span>
										<span class="text-red-600">{execution.steps_failed}</span>
										{#if execution.steps_skipped > 0}
											<span class="text-muted-foreground">({execution.steps_skipped} skipped)</span>
										{/if}
									</div>
								</Table.Cell>
								<Table.Cell>
									<Button variant="ghost" size="icon" class="h-8 w-8">
										<ChevronRight class="h-4 w-4" />
									</Button>
								</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>

				<!-- Pagination -->
				{#if totalPages > 1}
					<div class="flex items-center justify-between border-t px-4 py-3">
						<p class="text-sm text-muted-foreground">
							Page {currentPage} of {totalPages}
						</p>
						<div class="flex gap-2">
							<Button
								variant="outline"
								size="sm"
								disabled={currentPage === 1}
								onclick={() => handlePageChange(currentPage - 1)}
							>
								Previous
							</Button>
							<Button
								variant="outline"
								size="sm"
								disabled={currentPage === totalPages}
								onclick={() => handlePageChange(currentPage + 1)}
							>
								Next
							</Button>
						</div>
					</div>
				{/if}
			{/if}
		</Card.Content>
	</Card.Root>
</div>
