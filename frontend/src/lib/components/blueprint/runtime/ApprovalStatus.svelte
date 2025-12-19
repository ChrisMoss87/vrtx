<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Label } from '$lib/components/ui/label';
	import { toast } from 'svelte-sonner';
	import CheckCircleIcon from '@lucide/svelte/icons/check-circle';
	import XCircleIcon from '@lucide/svelte/icons/x-circle';
	import ClockIcon from '@lucide/svelte/icons/clock';
	import Loader2Icon from '@lucide/svelte/icons/loader-2';
	import * as blueprintApi from '$lib/api/blueprints';

	interface Props {
		executionId: number;
		status: 'pending_approval' | 'approved' | 'rejected';
		canApprove?: boolean;
		onStatusChange?: () => void;
	}

	let { executionId, status, canApprove = false, onStatusChange }: Props = $props();

	let comments = $state('');
	let approving = $state(false);
	let rejecting = $state(false);

	async function handleApprove() {
		approving = true;
		try {
			await blueprintApi.approveRequest(executionId, comments || undefined);
			toast.success('Request approved');
			onStatusChange?.();
		} catch (error) {
			console.error('Failed to approve:', error);
			toast.error('Failed to approve request');
		} finally {
			approving = false;
		}
	}

	async function handleReject() {
		rejecting = true;
		try {
			await blueprintApi.rejectRequest(executionId, comments || undefined);
			toast.success('Request rejected');
			onStatusChange?.();
		} catch (error) {
			console.error('Failed to reject:', error);
			toast.error('Failed to reject request');
		} finally {
			rejecting = false;
		}
	}

	const statusConfig = $derived(
		(() => {
			switch (status) {
				case 'pending_approval':
					return {
						label: 'Pending Approval',
						variant: 'secondary' as const,
						icon: ClockIcon
					};
				case 'approved':
					return {
						label: 'Approved',
						variant: 'default' as const,
						icon: CheckCircleIcon
					};
				case 'rejected':
					return {
						label: 'Rejected',
						variant: 'destructive' as const,
						icon: XCircleIcon
					};
				default:
					return {
						label: 'Unknown',
						variant: 'secondary' as const,
						icon: ClockIcon
					};
			}
		})()
	);
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<div class="flex items-center justify-between">
			<Card.Title class="text-base">Approval Status</Card.Title>
			<Badge variant={statusConfig.variant}>
				<svelte:component this={statusConfig.icon} class="mr-1 h-3 w-3" />
				{statusConfig.label}
			</Badge>
		</div>
	</Card.Header>
	<Card.Content>
		{#if status === 'pending_approval' && canApprove}
			<div class="space-y-4">
				<div class="space-y-2">
					<Label for="comments">Comments (optional)</Label>
					<Textarea
						id="comments"
						bind:value={comments}
						placeholder="Add a comment for your decision..."
						rows={3}
					/>
				</div>
				<div class="flex gap-2">
					<Button
						variant="default"
						class="flex-1 bg-green-600 hover:bg-green-700"
						onclick={handleApprove}
						disabled={approving || rejecting}
					>
						{#if approving}
							<Loader2Icon class="mr-2 h-4 w-4 animate-spin" />
							Approving...
						{:else}
							<CheckCircleIcon class="mr-2 h-4 w-4" />
							Approve
						{/if}
					</Button>
					<Button
						variant="destructive"
						class="flex-1"
						onclick={handleReject}
						disabled={approving || rejecting}
					>
						{#if rejecting}
							<Loader2Icon class="mr-2 h-4 w-4 animate-spin" />
							Rejecting...
						{:else}
							<XCircleIcon class="mr-2 h-4 w-4" />
							Reject
						{/if}
					</Button>
				</div>
			</div>
		{:else if status === 'pending_approval'}
			<p class="text-sm text-muted-foreground">Waiting for approval from authorized users.</p>
		{:else if status === 'approved'}
			<p class="text-sm text-muted-foreground">This transition has been approved.</p>
		{:else if status === 'rejected'}
			<p class="text-sm text-muted-foreground">This transition was rejected.</p>
		{/if}
	</Card.Content>
</Card.Root>
