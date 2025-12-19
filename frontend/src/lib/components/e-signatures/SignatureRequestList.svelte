<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import * as Card from '$lib/components/ui/card';
  import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
  import * as Select from '$lib/components/ui/select';
  import * as Progress from '$lib/components/ui/progress';
  import type { SignatureRequest } from '$lib/api/signatures';

  interface Props {
    requests?: SignatureRequest[];
    loading?: boolean;
    onCreate?: () => void;
    onView?: (id: number) => void;
    onVoid?: (id: number) => void;
    onRemind?: (id: number) => void;
    onDownload?: (id: number) => void;
  }

  let {
    requests = [],
    loading = false,
    onCreate,
    onView,
    onVoid,
    onRemind,
    onDownload,
  }: Props = $props();

  let search = $state('');
  let statusFilter = $state('');

  const statuses = [
    { value: '', label: 'All Statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'declined', label: 'Declined' },
    { value: 'voided', label: 'Voided' },
    { value: 'expired', label: 'Expired' },
  ];

  const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    pending: 'bg-yellow-100 text-yellow-800',
    in_progress: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    declined: 'bg-red-100 text-red-800',
    voided: 'bg-gray-100 text-gray-800',
    expired: 'bg-orange-100 text-orange-800',
  };

  const filteredRequests = $derived(requests.filter(r => {
    const matchesSearch = !search ||
      r.title.toLowerCase().includes(search.toLowerCase()) ||
      r.signers?.some(s => s.name.toLowerCase().includes(search.toLowerCase()) || s.email.toLowerCase().includes(search.toLowerCase()));
    const matchesStatus = !statusFilter || r.status === statusFilter;
    return matchesSearch && matchesStatus;
  }));

  function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }

  function getStatusLabel(status: string): string {
    return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  function getSigningProgress(request: SignatureRequest): number {
    const signers = request.signers || [];
    if (signers.length === 0) return 0;
    const signed = signers.filter(s => s.status === 'signed').length;
    return (signed / signers.length) * 100;
  }
</script>

<div class="space-y-4">
  <div class="flex justify-between items-center">
    <div class="flex gap-4 items-center">
      <Input
        bind:value={search}
        placeholder="Search requests..."
        class="w-64"
      />
      <Select.Root type="single" bind:value={statusFilter}>
        <Select.Trigger class="w-40">
          {statuses.find(s => s.value === statusFilter)?.label || 'All Statuses'}
        </Select.Trigger>
        <Select.Content>
          {#each statuses as status}
            <Select.Item value={status.value} label={status.label}>{status.label}</Select.Item>
          {/each}
        </Select.Content>
      </Select.Root>
    </div>

    <Button onclick={() => onCreate?.()}>
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19" />
        <line x1="5" y1="12" x2="19" y2="12" />
      </svg>
      New Request
    </Button>
  </div>

  {#if loading}
    <div class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>
  {:else if filteredRequests.length === 0}
    <Card.Root>
      <Card.Content class="py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <path d="M12 18v-6" />
          <path d="m9 15 3 3 3-3" />
        </svg>
        <p class="text-muted-foreground">No signature requests found</p>
        {#if search || statusFilter}
          <p class="text-sm text-muted-foreground mt-2">Try adjusting your filters</p>
        {:else}
          <Button variant="outline" class="mt-4" onclick={() => onCreate?.()}>
            Create your first signature request
          </Button>
        {/if}
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="space-y-3">
      {#each filteredRequests as request}
        <Card.Root class="hover:shadow-md transition-shadow cursor-pointer">
          <button type="button" class="w-full text-left" onclick={() => onView?.(request.id)}>
            <Card.Content class="p-4">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="font-medium">{request.title}</h3>
                    <Badge class={statusColors[request.status]}>
                      {getStatusLabel(request.status)}
                    </Badge>
                  </div>

                  <div class="flex items-center gap-6 text-sm text-muted-foreground">
                    <span class="flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                      </svg>
                      {request.signers?.length || 0} signers
                    </span>
                    <span>Created {formatDate(request.created_at)}</span>
                    {#if request.expires_at}
                      <span class="flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10" />
                          <polyline points="12 6 12 12 16 14" />
                        </svg>
                        Expires {formatDate(request.expires_at)}
                      </span>
                    {/if}
                  </div>

                  {#if request.signers && request.signers.length > 0}
                    <div class="mt-3">
                      <div class="flex items-center gap-2 mb-1">
                        <Progress.Root value={getSigningProgress(request)} class="h-1.5 flex-1" />
                        <span class="text-xs text-muted-foreground">
                          {request.signers.filter(s => s.status === 'signed').length}/{request.signers.length}
                        </span>
                      </div>
                      <div class="flex gap-2 flex-wrap">
                        {#each request.signers.slice(0, 3) as signer}
                          <span class="text-xs px-2 py-0.5 rounded-full bg-muted">
                            {signer.name}
                            {#if signer.status === 'signed'}
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12" />
                              </svg>
                            {/if}
                          </span>
                        {/each}
                        {#if request.signers.length > 3}
                          <span class="text-xs px-2 py-0.5 rounded-full bg-muted">
                            +{request.signers.length - 3} more
                          </span>
                        {/if}
                      </div>
                    </div>
                  {/if}
                </div>

                <DropdownMenu.Root>
                  <DropdownMenu.Trigger>
                    <Button variant="ghost" size="sm" onclick={(e: MouseEvent) => e.stopPropagation()}>
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1" />
                        <circle cx="12" cy="5" r="1" />
                        <circle cx="12" cy="19" r="1" />
                      </svg>
                    </Button>
                  </DropdownMenu.Trigger>
                  <DropdownMenu.Content align="end">
                    <DropdownMenu.Item onclick={() => onView?.(request.id)}>
                      View Details
                    </DropdownMenu.Item>
                    {#if request.status === 'pending' || request.status === 'in_progress'}
                      <DropdownMenu.Item onclick={() => onRemind?.(request.id)}>
                        Send Reminder
                      </DropdownMenu.Item>
                      <DropdownMenu.Separator />
                      <DropdownMenu.Item class="text-destructive" onclick={() => onVoid?.(request.id)}>
                        Void Request
                      </DropdownMenu.Item>
                    {/if}
                    {#if request.status === 'completed'}
                      <DropdownMenu.Item onclick={() => onDownload?.(request.id)}>
                        Download Document
                      </DropdownMenu.Item>
                    {/if}
                  </DropdownMenu.Content>
                </DropdownMenu.Root>
              </div>
            </Card.Content>
          </button>
        </Card.Root>
      {/each}
    </div>
  {/if}
</div>
