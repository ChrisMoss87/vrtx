<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import * as Card from '$lib/components/ui/card';
  import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
  import * as Select from '$lib/components/ui/select';
  import type { Proposal } from '$lib/api/proposals';

  interface Props {
    proposals?: Proposal[];
    loading?: boolean;
    onCreate?: () => void;
    onView?: (id: number) => void;
    onEdit?: (id: number) => void;
    onDuplicate?: (id: number) => void;
    onDelete?: (id: number) => void;
    onSend?: (id: number) => void;
  }

  let {
    proposals = [],
    loading = false,
    onCreate,
    onView,
    onEdit,
    onDuplicate,
    onDelete,
    onSend,
  }: Props = $props();

  let search = $state('');
  let statusFilter = $state('');

  const statuses = [
    { value: '', label: 'All Statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'viewed', label: 'Viewed' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'declined', label: 'Declined' },
    { value: 'expired', label: 'Expired' },
  ];

  const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    sent: 'bg-blue-100 text-blue-800',
    viewed: 'bg-purple-100 text-purple-800',
    accepted: 'bg-green-100 text-green-800',
    declined: 'bg-red-100 text-red-800',
    expired: 'bg-orange-100 text-orange-800',
  };

  const filteredProposals = $derived(proposals.filter(p => {
    const matchesSearch = !search ||
      p.title?.toLowerCase().includes(search.toLowerCase()) ||
      p.client_name?.toLowerCase().includes(search.toLowerCase()) ||
      p.sent_to_email?.toLowerCase().includes(search.toLowerCase());
    const matchesStatus = !statusFilter || p.status === statusFilter;
    return matchesSearch && matchesStatus;
  }));

  function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }

  function formatCurrency(amount: number): string {
    return amount.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
  }

  function getStatusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
  }
</script>

<div class="space-y-4">
  <div class="flex justify-between items-center">
    <div class="flex gap-4 items-center">
      <Input
        bind:value={search}
        placeholder="Search proposals..."
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
      New Proposal
    </Button>
  </div>

  {#if loading}
    <div class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>
  {:else if filteredProposals.length === 0}
    <Card.Root>
      <Card.Content class="py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <line x1="16" y1="13" x2="8" y2="13" />
          <line x1="16" y1="17" x2="8" y2="17" />
          <polyline points="10 9 9 9 8 9" />
        </svg>
        <p class="text-muted-foreground">No proposals found</p>
        {#if search || statusFilter}
          <p class="text-sm text-muted-foreground mt-2">Try adjusting your filters</p>
        {:else}
          <Button variant="outline" class="mt-4" onclick={() => onCreate?.()}>
            Create your first proposal
          </Button>
        {/if}
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {#each filteredProposals as proposal}
        <Card.Root class="hover:shadow-md transition-shadow cursor-pointer" onclick={() => onView?.(proposal.id)}>
          <Card.Header class="pb-2">
            <div class="flex justify-between items-start">
              <div class="space-y-1">
                <Card.Title class="text-lg line-clamp-1">{proposal.title}</Card.Title>
                <Card.Description class="line-clamp-1">
                  {proposal.client_name}
                  {#if proposal.client_company}
                    - {proposal.client_company}
                  {/if}
                </Card.Description>
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
                  <DropdownMenu.Item onclick={() => onView?.(proposal.id)}>
                    View
                  </DropdownMenu.Item>
                  <DropdownMenu.Item onclick={() => onEdit?.(proposal.id)}>
                    Edit
                  </DropdownMenu.Item>
                  <DropdownMenu.Item onclick={() => onDuplicate?.(proposal.id)}>
                    Duplicate
                  </DropdownMenu.Item>
                  {#if proposal.status === 'draft'}
                    <DropdownMenu.Separator />
                    <DropdownMenu.Item onclick={() => onSend?.(proposal.id)}>
                      Send to Client
                    </DropdownMenu.Item>
                  {/if}
                  <DropdownMenu.Separator />
                  <DropdownMenu.Item class="text-destructive" onclick={() => onDelete?.(proposal.id)}>
                    Delete
                  </DropdownMenu.Item>
                </DropdownMenu.Content>
              </DropdownMenu.Root>
            </div>
          </Card.Header>
          <Card.Content>
            <div class="flex flex-wrap gap-2 mb-3">
              <Badge class={statusColors[proposal.status]}>
                {getStatusLabel(proposal.status)}
              </Badge>
              {#if proposal.total_amount}
                <Badge variant="outline">{formatCurrency(proposal.total_amount)}</Badge>
              {/if}
            </div>

            <div class="text-xs text-muted-foreground space-y-1">
              <p>Created {formatDate(proposal.created_at)}</p>
              {#if proposal.expires_at}
                <p class="flex items-center gap-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                  Expires {formatDate(proposal.expires_at)}
                </p>
              {/if}
              {#if proposal.view_count > 0}
                <p class="flex items-center gap-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  Viewed {proposal.view_count} times
                </p>
              {/if}
            </div>
          </Card.Content>
        </Card.Root>
      {/each}
    </div>
  {/if}
</div>
