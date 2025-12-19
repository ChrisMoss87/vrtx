<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import { PublicProposalView } from '$lib/components/proposals';
  import { publicProposalApi, type Proposal, type ProposalSection, type ProposalPricingItem, type ProposalComment } from '$lib/api/proposals';

  const uuid = $derived($page.params.uuid ?? '');

  let proposal = $state<Proposal | null>(null);
  let sections = $state<ProposalSection[]>([]);
  let pricingItems = $state<ProposalPricingItem[]>([]);
  let comments = $state<ProposalComment[]>([]);
  let loading = $state(true);
  let error = $state('');

  onMount(async () => {
    await loadProposal();
  });

  async function loadProposal() {
    loading = true;
    error = '';
    try {
      const response = await publicProposalApi.get(uuid);
      // Check if response has proposal data or is an error response
      if ('proposal' in response) {
        proposal = response.proposal;
        // Load additional data
        sections = proposal.sections || [];
        pricingItems = proposal.pricing_items || [];
        // Load comments separately
        try {
          comments = await publicProposalApi.getComments(uuid);
        } catch {
          comments = [];
        }
        // Track view
        await publicProposalApi.trackView(uuid);
      } else {
        // Error response
        error = response.message || 'This proposal link is invalid or has expired.';
      }
    } catch (err) {
      console.error('Failed to load proposal:', err);
      error = 'This proposal link is invalid or has expired.';
    } finally {
      loading = false;
    }
  }

  async function handleAccept(acceptedBy: string, signature?: string) {
    loading = true;
    try {
      await publicProposalApi.accept(uuid, acceptedBy, signature);
      proposal = { ...proposal!, status: 'accepted' } as Proposal;
    } catch (err) {
      console.error('Failed to accept proposal:', err);
    } finally {
      loading = false;
    }
  }

  async function handleDecline(rejectedBy: string, reason?: string) {
    loading = true;
    try {
      await publicProposalApi.reject(uuid, rejectedBy, reason);
      proposal = { ...proposal!, status: 'rejected' } as Proposal;
    } catch (err) {
      console.error('Failed to decline proposal:', err);
    } finally {
      loading = false;
    }
  }

  async function handleAddComment(sectionId: number | null, content: string, authorEmail: string, authorName?: string) {
    try {
      const newComment = await publicProposalApi.addComment(uuid, {
        section_id: sectionId ?? undefined,
        comment: content,
        author_email: authorEmail,
        author_name: authorName
      });
      comments = [...comments, newComment];
    } catch (err) {
      console.error('Failed to add comment:', err);
    }
  }

  async function handleTogglePricingItem(itemId: number) {
    try {
      const result = await publicProposalApi.toggleItem(uuid, itemId);
      const itemIndex = pricingItems.findIndex(i => i.id === itemId);
      if (itemIndex >= 0) {
        pricingItems[itemIndex].is_selected = result.is_selected;
        pricingItems = [...pricingItems];
      }
    } catch (err) {
      console.error('Failed to toggle pricing item:', err);
    }
  }
</script>

<svelte:head>
  <title>{proposal?.title || 'View Proposal'}</title>
</svelte:head>

{#if loading && !proposal}
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
      <p class="text-muted-foreground">Loading proposal...</p>
    </div>
  </div>
{:else if error}
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="text-center max-w-md">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-destructive mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10" />
        <line x1="12" y1="8" x2="12" y2="12" />
        <line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <h1 class="text-2xl font-bold mb-2">Unable to Load Proposal</h1>
      <p class="text-muted-foreground">{error}</p>
    </div>
  </div>
{:else if proposal}
  <PublicProposalView
    {proposal}
    {sections}
    {pricingItems}
    {comments}
    {loading}
    onAccept={() => handleAccept('Client')}
    onDecline={(reason) => handleDecline('Client', reason)}
    onAddComment={(data) => handleAddComment(data.sectionId, data.content, 'client@example.com')}
    onTogglePricingItem={handleTogglePricingItem}
  />
{/if}
