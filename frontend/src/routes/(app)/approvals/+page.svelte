<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import * as blueprintApi from '$lib/api/blueprints';
	import type { PendingApproval } from '$lib/api/blueprints';
	import { Button } from '$lib/components/ui/button';
	import * as Table from '$lib/components/ui/table';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Textarea } from '$lib/components/ui/textarea';
	import { toast } from 'svelte-sonner';
	import * as Dialog from '$lib/components/ui/dialog';
	import CheckCircleIcon from '@lucide/svelte/icons/check-circle';
	import XCircleIcon from '@lucide/svelte/icons/x-circle';
	import ClipboardListIcon from '@lucide/svelte/icons/clipboard-list';
	import Loader2Icon from '@lucide/svelte/icons/loader-2';
	import ExternalLinkIcon from '@lucide/svelte/icons/external-link';

	let approvals = $state<PendingApproval[]>([]);
	let loading = $state(true);
	let selectedApproval = $state<PendingApproval | null>(null);
	let dialogOpen = $state(false);
	let comments = $state('');
	let processing = $state(false);
	let action = $state<'approve' | 'reject' | null>(null);

	async function loadApprovals() {
		loading = true;
		try {
			approvals = await blueprintApi.getPendingApprovals();
		} catch (error) {
			console.error('Failed to load approvals:', error);
			toast.error('Failed to load pending approvals');
		} finally {
			loading = false;
		}
	}

	function openDialog(approval: PendingApproval, actionType: 'approve' | 'reject') {
		selectedApproval = approval;
		action = actionType;
		comments = '';
		dialogOpen = true;
	}

	async function handleConfirm() {
		if (!selectedApproval || !action) return;

		processing = true;
		try {
			if (action === 'approve') {
				await blueprintApi.approveRequest(selectedApproval.id, comments || undefined);
				toast.success('Request approved');
			} else {
				await blueprintApi.rejectRequest(selectedApproval.id, comments || undefined);
				toast.success('Request rejected');
			}
			dialogOpen = false;
			await loadApprovals();
		} catch (error) {
			console.error(`Failed to ${action}:`, error);
			toast.error(`Failed to ${action} request`);
		} finally {
			processing = false;
		}
	}

	function formatDate(dateStr: string): string {
		return new Date(dateStr).toLocaleString();
	}

	function getRelativeTime(dateStr: string): string {
		const date = new Date(dateStr);
		const now = new Date();
		const diffMs = now.getTime() - date.getTime();
		const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
		const diffDays = Math.floor(diffHours / 24);

		if (diffDays > 0) {
			return `${diffDays}d ago`;
		}
		if (diffHours > 0) {
			return `${diffHours}h ago`;
		}
		const diffMinutes = Math.floor(diffMs / (1000 * 60));
		return `${diffMinutes}m ago`;
	}

	onMount(() => {
		loadApprovals();
	});
</script>

<svelte:head>
	<title>Pending Approvals</title>
</svelte:head>

<div class="container mx-auto space-y-6 py-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Pending Approvals</h1>
			<p class="text-muted-foreground">
				Review and respond to pending transition approval requests.
			</p>
		</div>
		<Button variant="outline" onclick={loadApprovals} disabled={loading}>
			{#if loading}
				<Loader2Icon class="mr-2 h-4 w-4 animate-spin" />
			{/if}
			Refresh
		</Button>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Loader2Icon class="h-8 w-8 animate-spin text-muted-foreground" />
		</div>
	{:else if approvals.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<ClipboardListIcon class="mb-4 h-12 w-12 text-muted-foreground" />
				<h3 class="text-lg font-semibold">No Pending Approvals</h3>
				<p class="text-muted-foreground">You don't have any pending approval requests.</p>
			</Card.Content>
		</Card.Root>
	{:else}
		<Card.Root>
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Request</Table.Head>
						<Table.Head>Module</Table.Head>
						<Table.Head>Blueprint</Table.Head>
						<Table.Head>Requested By</Table.Head>
						<Table.Head>Requested</Table.Head>
						<Table.Head class="text-right">Actions</Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each approvals as approval}
						<Table.Row>
							<Table.Cell>
								<div class="flex flex-col">
									<span class="font-medium">{approval.execution.transition.name}</span>
									<span class="text-xs text-muted-foreground">
										Record #{approval.record_id}
									</span>
								</div>
							</Table.Cell>
							<Table.Cell>
								<Badge variant="outline">{approval.execution.module.name}</Badge>
							</Table.Cell>
							<Table.Cell>{approval.execution.blueprint.name}</Table.Cell>
							<Table.Cell>{approval.requested_by?.name || 'Unknown'}</Table.Cell>
							<Table.Cell>
								<span title={formatDate(approval.created_at)}>
									{getRelativeTime(approval.created_at)}
								</span>
							</Table.Cell>
							<Table.Cell class="text-right">
								<div class="flex justify-end gap-2">
									<Button
										variant="ghost"
										size="icon"
										title="View record"
										onclick={() => goto(`/records/${approval.record_id}`)}
									>
										<ExternalLinkIcon class="h-4 w-4" />
									</Button>
									<Button
										variant="outline"
										size="sm"
										class="text-green-600 hover:text-green-700"
										onclick={() => openDialog(approval, 'approve')}
									>
										<CheckCircleIcon class="mr-1 h-4 w-4" />
										Approve
									</Button>
									<Button
										variant="outline"
										size="sm"
										class="text-destructive hover:text-destructive"
										onclick={() => openDialog(approval, 'reject')}
									>
										<XCircleIcon class="mr-1 h-4 w-4" />
										Reject
									</Button>
								</div>
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		</Card.Root>
	{/if}
</div>

<!-- Approval Dialog -->
<Dialog.Root bind:open={dialogOpen}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>
				{action === 'approve' ? 'Approve' : 'Reject'} Request
			</Dialog.Title>
			<Dialog.Description>
				{#if selectedApproval}
					You are about to {action} the transition "{selectedApproval.execution.transition.name}"
					for record #{selectedApproval.record_id}.
				{/if}
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<label for="comments" class="text-sm font-medium">Comments (optional)</label>
				<Textarea
					id="comments"
					bind:value={comments}
					placeholder="Add any comments for your decision..."
					rows={3}
				/>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (dialogOpen = false)} disabled={processing}>
				Cancel
			</Button>
			<Button
				variant={action === 'approve' ? 'default' : 'destructive'}
				onclick={handleConfirm}
				disabled={processing}
				class={action === 'approve' ? 'bg-green-600 hover:bg-green-700' : ''}
			>
				{#if processing}
					<Loader2Icon class="mr-2 h-4 w-4 animate-spin" />
				{:else if action === 'approve'}
					<CheckCircleIcon class="mr-2 h-4 w-4" />
				{:else}
					<XCircleIcon class="mr-2 h-4 w-4" />
				{/if}
				{action === 'approve' ? 'Approve' : 'Reject'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
