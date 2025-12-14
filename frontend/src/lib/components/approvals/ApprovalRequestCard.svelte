<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import * as Card from '$lib/components/ui/card';
  import type { ApprovalRequest, ApprovalStep } from '$lib/api/approvals';

  interface Props {
    request: ApprovalRequest;
    showActions?: boolean;
    onApprove?: () => void;
    onReject?: () => void;
    onDelegate?: () => void;
    onView?: () => void;
  }

  let {
    request,
    showActions = true,
    onApprove,
    onReject,
    onDelegate,
    onView,
  }: Props = $props();

  const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    delegated: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-gray-100 text-gray-800',
    expired: 'bg-orange-100 text-orange-800',
  };

  function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function getStatusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
  }

  const currentStep = $derived(request.steps?.find(s => s.status === 'pending'));
  const completedSteps = $derived(request.steps?.filter(s => s.status === 'approved').length || 0);
  const totalSteps = $derived(request.steps?.length || 0);
</script>

<Card.Root class="hover:shadow-md transition-shadow">
  <Card.Content class="pt-6">
    <div class="flex justify-between items-start mb-4">
      <div>
        <h3 class="font-medium text-lg">{request.title || `${request.entity_type} Approval`}</h3>
        <p class="text-sm text-muted-foreground">
          Requested by {request.requester?.name || 'Unknown'}
        </p>
      </div>
      <Badge class={statusColors[request.status]}>
        {getStatusLabel(request.status)}
      </Badge>
    </div>

    <!-- Progress -->
    <div class="mb-4">
      <div class="flex items-center gap-2 mb-2">
        {#each request.steps || [] as step, index}
          <div class="flex items-center">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs {
              step.status === 'approved' ? 'bg-green-500 text-white' :
              step.status === 'rejected' ? 'bg-red-500 text-white' :
              step.status === 'pending' ? 'bg-primary text-primary-foreground' :
              'bg-muted text-muted-foreground'
            }">
              {#if step.status === 'approved'}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              {:else if step.status === 'rejected'}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
              {:else}
                {index + 1}
              {/if}
            </div>
            {#if index < (request.steps?.length || 0) - 1}
              <div class="w-8 h-0.5 {step.status === 'approved' ? 'bg-green-500' : 'bg-muted'}"></div>
            {/if}
          </div>
        {/each}
      </div>
      <p class="text-xs text-muted-foreground">
        Step {completedSteps + 1} of {totalSteps}
        {#if currentStep?.approver?.name}
          • Waiting for {currentStep.approver.name}
        {/if}
      </p>
    </div>

    <!-- Details -->
    {#if request.details}
      <div class="p-3 rounded-lg bg-muted/50 mb-4">
        <p class="text-sm">{request.details}</p>
      </div>
    {/if}

    <!-- Meta -->
    <div class="flex items-center justify-between text-xs text-muted-foreground">
      <span>Submitted {formatDate(request.created_at)}</span>
      {#if request.due_date}
        <span class="flex items-center gap-1">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
          </svg>
          Due {formatDate(request.due_date)}
        </span>
      {/if}
    </div>

    <!-- Actions -->
    {#if showActions && request.status === 'pending' && currentStep}
      <div class="flex gap-2 mt-4 pt-4 border-t">
        <Button variant="outline" class="flex-1" onclick={() => onView?.()}>
          View Details
        </Button>
        {#if currentStep.can_delegate}
          <Button variant="outline" onclick={() => onDelegate?.()}>
            Delegate
          </Button>
        {/if}
        <Button variant="outline" class="text-destructive" onclick={() => onReject?.()}>
          Reject
        </Button>
        <Button onclick={() => onApprove?.()}>
          Approve
        </Button>
      </div>
    {:else}
      <div class="mt-4 pt-4 border-t">
        <Button variant="outline" class="w-full" onclick={() => onView?.()}>
          View Details
        </Button>
      </div>
    {/if}
  </Card.Content>
</Card.Root>
