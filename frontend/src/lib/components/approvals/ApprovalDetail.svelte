<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import { Textarea } from '$lib/components/ui/textarea';
  import { Label } from '$lib/components/ui/label';
  import * as Card from '$lib/components/ui/card';
  import * as Dialog from '$lib/components/ui/dialog';
  import * as Select from '$lib/components/ui/select';
  import type { ApprovalRequest, ApprovalStep, ApprovalHistory } from '$lib/api/approvals';

  export let request: ApprovalRequest;
  export let history: ApprovalHistory[] = [];
  export let users: Array<{ id: number; name: string }> = [];
  export let loading = false;

  const dispatch = createEventDispatcher<{
    approve: { comment?: string };
    reject: { comment: string };
    delegate: { userId: number; comment?: string };
    cancel: void;
  }>();

  let showApproveDialog = $state(false);
  let showRejectDialog = $state(false);
  let showDelegateDialog = $state(false);
  let comment = $state('');
  let delegateUserId = $state<number | null>(null);

  const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    delegated: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-gray-100 text-gray-800',
  };

  function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function getStatusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
  }

  function handleApprove() {
    dispatch('approve', { comment: comment || undefined });
    showApproveDialog = false;
    comment = '';
  }

  function handleReject() {
    dispatch('reject', { comment });
    showRejectDialog = false;
    comment = '';
  }

  function handleDelegate() {
    if (delegateUserId) {
      dispatch('delegate', { userId: delegateUserId, comment: comment || undefined });
      showDelegateDialog = false;
      comment = '';
      delegateUserId = null;
    }
  }

  const currentStep = $derived(request.steps?.find(s => s.status === 'pending'));
</script>

<div class="space-y-6">
  <!-- Header -->
  <Card.Root>
    <Card.Header>
      <div class="flex justify-between items-start">
        <div>
          <Card.Title class="text-xl">{request.title || `${request.entity_type} Approval Request`}</Card.Title>
          <Card.Description>
            Submitted by {request.requester?.name || 'Unknown'} on {formatDate(request.created_at)}
          </Card.Description>
        </div>
        <Badge class={statusColors[request.status]}>
          {getStatusLabel(request.status)}
        </Badge>
      </div>
    </Card.Header>
    <Card.Content>
      {#if request.details}
        <div class="p-4 rounded-lg bg-muted mb-4">
          <h4 class="font-medium mb-2">Request Details</h4>
          <p class="text-sm">{request.details}</p>
        </div>
      {/if}

      {#if request.entity_data}
        <div class="p-4 rounded-lg border">
          <h4 class="font-medium mb-2">Related Data</h4>
          <pre class="text-xs bg-muted p-2 rounded overflow-x-auto">{JSON.stringify(request.entity_data, null, 2)}</pre>
        </div>
      {/if}
    </Card.Content>
    {#if request.status === 'pending' && currentStep}
      <Card.Footer class="flex gap-2 justify-end">
        {#if currentStep.can_delegate}
          <Button variant="outline" onclick={() => showDelegateDialog = true}>
            Delegate
          </Button>
        {/if}
        <Button variant="outline" class="text-destructive" onclick={() => showRejectDialog = true}>
          Reject
        </Button>
        <Button onclick={() => showApproveDialog = true}>
          Approve
        </Button>
      </Card.Footer>
    {/if}
  </Card.Root>

  <!-- Approval Flow -->
  <Card.Root>
    <Card.Header>
      <Card.Title>Approval Flow</Card.Title>
    </Card.Header>
    <Card.Content>
      <div class="relative">
        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-muted"></div>
        <div class="space-y-6">
          {#each request.steps || [] as step, index}
            <div class="relative pl-10">
              <div class="absolute left-0 w-8 h-8 rounded-full flex items-center justify-center {
                step.status === 'approved' ? 'bg-green-500 text-white' :
                step.status === 'rejected' ? 'bg-red-500 text-white' :
                step.status === 'pending' ? 'bg-primary text-primary-foreground' :
                'bg-muted text-muted-foreground'
              }">
                {#if step.status === 'approved'}
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                {:else if step.status === 'rejected'}
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                {:else}
                  {index + 1}
                {/if}
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-medium">{step.approver?.name || 'Unknown Approver'}</span>
                  <Badge variant="outline" class={statusColors[step.status]}>
                    {getStatusLabel(step.status)}
                  </Badge>
                </div>
                {#if step.acted_at}
                  <p class="text-sm text-muted-foreground">
                    {getStatusLabel(step.status)} on {formatDate(step.acted_at)}
                  </p>
                {:else if step.status === 'pending'}
                  <p class="text-sm text-muted-foreground">Waiting for approval</p>
                {/if}
                {#if step.comment}
                  <p class="text-sm mt-1 p-2 rounded bg-muted">{step.comment}</p>
                {/if}
              </div>
            </div>
          {/each}
        </div>
      </div>
    </Card.Content>
  </Card.Root>

  <!-- History -->
  {#if history.length > 0}
    <Card.Root>
      <Card.Header>
        <Card.Title>Activity History</Card.Title>
      </Card.Header>
      <Card.Content>
        <div class="space-y-4">
          {#each history as entry}
            <div class="flex items-start gap-3">
              <div class="w-2 h-2 mt-2 rounded-full bg-muted-foreground"></div>
              <div>
                <p class="text-sm">
                  <span class="font-medium">{entry.user?.name || 'System'}</span>
                  {entry.action}
                </p>
                <p class="text-xs text-muted-foreground">{formatDate(entry.created_at)}</p>
                {#if entry.comment}
                  <p class="text-sm mt-1 p-2 rounded bg-muted">{entry.comment}</p>
                {/if}
              </div>
            </div>
          {/each}
        </div>
      </Card.Content>
    </Card.Root>
  {/if}
</div>

<!-- Approve Dialog -->
<Dialog.Root bind:open={showApproveDialog}>
  <Dialog.Content>
    <Dialog.Header>
      <Dialog.Title>Approve Request</Dialog.Title>
      <Dialog.Description>
        Add an optional comment with your approval.
      </Dialog.Description>
    </Dialog.Header>
    <div class="py-4">
      <Label for="approveComment">Comment (Optional)</Label>
      <Textarea
        id="approveComment"
        bind:value={comment}
        placeholder="Add a comment..."
        class="mt-2"
      />
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => showApproveDialog = false}>Cancel</Button>
      <Button onclick={handleApprove} disabled={loading}>
        {loading ? 'Approving...' : 'Approve'}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<!-- Reject Dialog -->
<Dialog.Root bind:open={showRejectDialog}>
  <Dialog.Content>
    <Dialog.Header>
      <Dialog.Title>Reject Request</Dialog.Title>
      <Dialog.Description>
        Please provide a reason for rejecting this request.
      </Dialog.Description>
    </Dialog.Header>
    <div class="py-4">
      <Label for="rejectComment">Reason for Rejection</Label>
      <Textarea
        id="rejectComment"
        bind:value={comment}
        placeholder="Explain why this request is being rejected..."
        class="mt-2"
      />
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => showRejectDialog = false}>Cancel</Button>
      <Button variant="destructive" onclick={handleReject} disabled={loading || !comment}>
        {loading ? 'Rejecting...' : 'Reject'}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<!-- Delegate Dialog -->
<Dialog.Root bind:open={showDelegateDialog}>
  <Dialog.Content>
    <Dialog.Header>
      <Dialog.Title>Delegate Approval</Dialog.Title>
      <Dialog.Description>
        Select someone else to review this request.
      </Dialog.Description>
    </Dialog.Header>
    <div class="py-4 space-y-4">
      <div class="space-y-2">
        <Label>Delegate To</Label>
        <Select.Root type="single" value={delegateUserId?.toString() || ''} onValueChange={(v) => delegateUserId = v ? parseInt(v) : null}>
          <Select.Trigger>
            {delegateUserId ? users.find(u => u.id === delegateUserId)?.name : 'Select user'}
          </Select.Trigger>
          <Select.Content>
            {#each users as user}
              <Select.Item value={user.id.toString()} label={user.name}>{user.name}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>
      <div class="space-y-2">
        <Label for="delegateComment">Comment (Optional)</Label>
        <Textarea
          id="delegateComment"
          bind:value={comment}
          placeholder="Add a note for the delegate..."
        />
      </div>
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => showDelegateDialog = false}>Cancel</Button>
      <Button onclick={handleDelegate} disabled={loading || !delegateUserId}>
        {loading ? 'Delegating...' : 'Delegate'}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
