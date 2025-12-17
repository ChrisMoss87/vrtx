<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import * as Card from '$lib/components/ui/card';
  import * as Tabs from '$lib/components/ui/tabs';
  import { ProposalAnalytics } from '$lib/components/proposals';
  import { proposalsApi, type Proposal, type ProposalView } from '$lib/api/proposals';

  let proposal = $state<Proposal | null>(null);
  let views = $state<ProposalView[]>([]);
  let loading = $state(true);

  const proposalId = $derived(parseInt($page.params.id));

  onMount(async () => {
    await loadProposal();
  });

  async function loadProposal() {
    loading = true;
    try {
      proposal = await proposalsApi.get(proposalId);
      views = await proposalsApi.getViews(proposalId);
    } catch (error) {
      console.error('Failed to load proposal:', error);
    } finally {
      loading = false;
    }
  }

  async function handleSend() {
    try {
      await proposalsApi.send(proposalId);
      await loadProposal();
      alert('Proposal sent successfully');
    } catch (error) {
      console.error('Failed to send proposal:', error);
    }
  }

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

  const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    sent: 'bg-blue-100 text-blue-800',
    viewed: 'bg-purple-100 text-purple-800',
    accepted: 'bg-green-100 text-green-800',
    declined: 'bg-red-100 text-red-800',
    expired: 'bg-orange-100 text-orange-800',
  };
</script>

<svelte:head>
  <title>{proposal?.title || 'Proposal'} | VRTX</title>
</svelte:head>

<div class="container py-6">
  {#if loading}
    <div class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>
  {:else if proposal}
    <div class="mb-6 flex justify-between items-start">
      <div>
        <button class="text-sm text-muted-foreground hover:text-foreground mb-2" onclick={() => goto('/proposals')}>
          ← Back to Proposals
        </button>
        <h1 class="text-2xl font-bold">{proposal.title}</h1>
        <div class="flex items-center gap-4 mt-2">
          <Badge class={statusColors[proposal.status]}>
            {proposal.status.charAt(0).toUpperCase() + proposal.status.slice(1)}
          </Badge>
          <span class="text-muted-foreground">
            {proposal.client_name}
            {#if proposal.client_company}
              - {proposal.client_company}
            {/if}
          </span>
        </div>
      </div>
      <div class="flex gap-2">
        <Button variant="outline" onclick={() => goto(`/proposals/${proposalId}/edit`)}>
          Edit
        </Button>
        {#if proposal.status === 'draft'}
          <Button onclick={handleSend}>Send to Client</Button>
        {:else if proposal.public_url}
          <Button variant="outline" onclick={() => window.open(proposal?.public_url, '_blank')}>
            View Public Page
          </Button>
        {/if}
      </div>
    </div>

    <Tabs.Root value="overview">
      <Tabs.List>
        <Tabs.Trigger value="overview">Overview</Tabs.Trigger>
        <Tabs.Trigger value="analytics">Analytics</Tabs.Trigger>
        <Tabs.Trigger value="comments">Comments</Tabs.Trigger>
      </Tabs.List>

      <Tabs.Content value="overview" class="mt-6">
        <div class="grid grid-cols-3 gap-6">
          <Card.Root>
            <Card.Header class="pb-2">
              <Card.Description>Total Value</Card.Description>
              <Card.Title class="text-2xl">{formatCurrency(proposal.total_amount || 0)}</Card.Title>
            </Card.Header>
          </Card.Root>
          <Card.Root>
            <Card.Header class="pb-2">
              <Card.Description>Views</Card.Description>
              <Card.Title class="text-2xl">{proposal.view_count || 0}</Card.Title>
            </Card.Header>
          </Card.Root>
          <Card.Root>
            <Card.Header class="pb-2">
              <Card.Description>Created</Card.Description>
              <Card.Title class="text-2xl">{formatDate(proposal.created_at)}</Card.Title>
            </Card.Header>
          </Card.Root>
        </div>

        <Card.Root class="mt-6">
          <Card.Header>
            <Card.Title>Proposal Details</Card.Title>
          </Card.Header>
          <Card.Content class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-muted-foreground">Client Name</p>
                <p class="font-medium">{proposal.client_name}</p>
              </div>
              <div>
                <p class="text-sm text-muted-foreground">Client Email</p>
                <p class="font-medium">{proposal.sent_to_email || '-'}</p>
              </div>
              {#if proposal.client_company}
                <div>
                  <p class="text-sm text-muted-foreground">Company</p>
                  <p class="font-medium">{proposal.client_company}</p>
                </div>
              {/if}
              {#if proposal.expires_at}
                <div>
                  <p class="text-sm text-muted-foreground">Expires</p>
                  <p class="font-medium">{formatDate(proposal.expires_at)}</p>
                </div>
              {/if}
            </div>

            {#if proposal.cover_letter}
              <div class="pt-4 border-t">
                <p class="text-sm text-muted-foreground mb-2">Cover Letter</p>
                <p class="text-sm">{proposal.cover_letter}</p>
              </div>
            {/if}

            {#if proposal.sections && proposal.sections.length > 0}
              <div class="pt-4 border-t">
                <p class="text-sm text-muted-foreground mb-2">Sections ({proposal.sections.length})</p>
                <div class="space-y-2">
                  {#each proposal.sections as section}
                    <div class="p-3 rounded border">
                      <p class="font-medium">{section.title}</p>
                    </div>
                  {/each}
                </div>
              </div>
            {/if}
          </Card.Content>
        </Card.Root>
      </Tabs.Content>

      <Tabs.Content value="analytics" class="mt-6">
        <ProposalAnalytics
          {views}
          totalViews={proposal.view_count || 0}
          uniqueViews={views.length}
          averageViewTime={views.reduce((sum, v) => sum + (v.duration_seconds || 0), 0) / Math.max(views.length, 1)}
        />
      </Tabs.Content>

      <Tabs.Content value="comments" class="mt-6">
        <Card.Root>
          <Card.Content class="py-12 text-center">
            <p class="text-muted-foreground">No comments yet</p>
          </Card.Content>
        </Card.Root>
      </Tabs.Content>
    </Tabs.Root>
  {:else}
    <div class="text-center py-12">
      <p class="text-muted-foreground">Proposal not found</p>
    </div>
  {/if}
</div>
