<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import * as Dialog from '$lib/components/ui/dialog';
  import * as Table from '$lib/components/ui/table';
  import type { Proposal, ProposalSection, ProposalPricingItem, ProposalComment } from '$lib/api/proposals';

  interface Props {
    proposal: Proposal;
    sections?: ProposalSection[];
    pricingItems?: ProposalPricingItem[];
    comments?: ProposalComment[];
    loading?: boolean;
    onAccept?: () => void;
    onDecline?: (reason: string) => void;
    onAddComment?: (data: { sectionId: number | null; content: string }) => void;
    onTogglePricingItem?: (id: number) => void;
  }

  let {
    proposal,
    sections = [],
    pricingItems = [],
    comments = [],
    loading = false,
    onAccept,
    onDecline,
    onAddComment,
    onTogglePricingItem,
  }: Props = $props();

  let showDeclineDialog = $state(false);
  let declineReason = $state('');
  let newComment = $state('');
  let activeSectionId = $state<number | null>(null);

  const visibleSections = $derived(sections.filter(s => s.is_visible));
  const selectedItems = $derived(pricingItems.filter(item => !item.is_optional || item.is_selected));
  const totalAmount = $derived(selectedItems.reduce((sum, item) => {
    const subtotal = item.quantity * item.unit_price;
    const discount = item.discount_percent ? subtotal * (item.discount_percent / 100) : 0;
    return sum + (subtotal - discount);
  }, 0));

  function formatCurrency(amount: number): string {
    return amount.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
  }

  function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    });
  }

  function handleAddComment() {
    if (newComment.trim()) {
      onAddComment?.({ sectionId: activeSectionId, content: newComment });
      newComment = '';
    }
  }

  function handleDecline() {
    onDecline?.(declineReason);
    showDeclineDialog = false;
  }

  function toggleOptionalItem(itemId: number) {
    onTogglePricingItem?.(itemId);
  }
</script>

<div class="min-h-screen bg-gray-50" style="--primary: {proposal.settings?.branding?.primary_color || '#3b82f6'}; --accent: {proposal.settings?.branding?.accent_color || '#10b981'};">
  <!-- Header -->
  <header class="bg-white border-b sticky top-0 z-10">
    <div class="max-w-4xl mx-auto px-4 py-4">
      <div class="flex justify-between items-center">
        <div class="flex items-center gap-4">
          {#if proposal.settings?.branding?.logo_url}
            <img src={proposal.settings.branding.logo_url} alt="Company logo" class="h-10" />
          {/if}
          <div>
            <h1 class="text-xl font-semibold">{proposal.title}</h1>
            <p class="text-sm text-muted-foreground">
              Prepared for {proposal.client_name}
            </p>
          </div>
        </div>
        {#if proposal.status === 'sent' || proposal.status === 'viewed'}
          <div class="flex gap-2">
            <Button variant="outline" onclick={() => showDeclineDialog = true}>
              Decline
            </Button>
            <Button onclick={() => onAccept?.()} disabled={loading} style="background-color: var(--primary);">
              {loading ? 'Processing...' : 'Accept Proposal'}
            </Button>
          </div>
        {:else if proposal.status === 'accepted'}
          <Badge class="bg-green-100 text-green-800">Accepted</Badge>
        {:else if proposal.status === 'declined'}
          <Badge class="bg-red-100 text-red-800">Declined</Badge>
        {/if}
      </div>
    </div>
  </header>

  <!-- Content -->
  <main class="max-w-4xl mx-auto px-4 py-8">
    <!-- Cover Letter -->
    {#if proposal.cover_letter}
      <Card.Root class="mb-8">
        <Card.Content class="pt-6">
          <div class="prose prose-sm max-w-none">
            {@html proposal.cover_letter.replace(/\n/g, '<br>')}
          </div>
        </Card.Content>
      </Card.Root>
    {/if}

    <!-- Sections -->
    {#each visibleSections as section, index}
      <Card.Root class="mb-6" id="section-{section.id}">
        <Card.Header>
          <Card.Title>{section.title}</Card.Title>
        </Card.Header>
        <Card.Content>
          <div class="prose prose-sm max-w-none">
            {@html section.content.replace(/\n/g, '<br>')}
          </div>

          {#if section.media_urls && section.media_urls.length > 0}
            <div class="mt-4 grid grid-cols-2 gap-4">
              {#each section.media_urls as url}
                <img src={url} alt="Section media" class="rounded-lg" />
              {/each}
            </div>
          {/if}
        </Card.Content>

        {#if proposal.settings?.allow_comments}
          <Card.Footer class="flex-col items-start gap-4 border-t pt-4">
            <!-- Section Comments -->
            {#each comments.filter(c => c.section_id === section.id) as comment}
              <div class="w-full p-3 rounded-lg bg-muted">
                <div class="flex justify-between items-start mb-1">
                  <span class="font-medium text-sm">{comment.author_name}</span>
                  <span class="text-xs text-muted-foreground">{formatDate(comment.created_at)}</span>
                </div>
                <p class="text-sm">{comment.content}</p>
              </div>
            {/each}

            <div class="w-full flex gap-2">
              <Textarea
                bind:value={newComment}
                placeholder="Add a comment..."
                class="flex-1 min-h-[60px]"
                onfocus={() => activeSectionId = section.id}
              />
              <Button variant="outline" size="sm" onclick={handleAddComment}>
                Comment
              </Button>
            </div>
          </Card.Footer>
        {/if}
      </Card.Root>
    {/each}

    <!-- Pricing -->
    {#if proposal.settings?.show_pricing_breakdown && pricingItems.length > 0}
      <Card.Root class="mb-8">
        <Card.Header>
          <Card.Title>Pricing</Card.Title>
        </Card.Header>
        <Card.Content>
          <Table.Root>
            <Table.Header>
              <Table.Row>
                <Table.Head>Item</Table.Head>
                <Table.Head class="text-right">Qty</Table.Head>
                <Table.Head class="text-right">Unit Price</Table.Head>
                <Table.Head class="text-right">Total</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each pricingItems as item}
                <Table.Row class={item.is_optional ? 'bg-muted/30' : ''}>
                  <Table.Cell>
                    <div class="flex items-center gap-3">
                      {#if item.is_optional}
                        <input
                          type="checkbox"
                          checked={item.is_selected}
                          onchange={() => toggleOptionalItem(item.id)}
                          class="rounded"
                        />
                      {/if}
                      <div>
                        <p class="font-medium">{item.name}</p>
                        {#if item.description}
                          <p class="text-sm text-muted-foreground">{item.description}</p>
                        {/if}
                        {#if item.is_optional}
                          <Badge variant="outline" class="text-xs mt-1">Optional Add-on</Badge>
                        {/if}
                      </div>
                    </div>
                  </Table.Cell>
                  <Table.Cell class="text-right">{item.quantity}</Table.Cell>
                  <Table.Cell class="text-right">{formatCurrency(item.unit_price)}</Table.Cell>
                  <Table.Cell class="text-right">
                    {formatCurrency(item.quantity * item.unit_price * (1 - (item.discount_percent || 0) / 100))}
                    {#if item.discount_percent}
                      <span class="text-xs text-green-600 block">-{item.discount_percent}%</span>
                    {/if}
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>

          <div class="flex justify-end mt-4">
            <div class="w-64 space-y-2">
              <div class="flex justify-between">
                <span>Subtotal</span>
                <span>{formatCurrency(totalAmount)}</span>
              </div>
              <div class="flex justify-between text-lg font-semibold pt-2 border-t">
                <span>Total</span>
                <span style="color: var(--primary);">{formatCurrency(totalAmount)}</span>
              </div>
            </div>
          </div>
        </Card.Content>
      </Card.Root>
    {/if}

    <!-- Expiration Notice -->
    {#if proposal.expires_at && (proposal.status === 'sent' || proposal.status === 'viewed')}
      <Card.Root class="border-orange-200 bg-orange-50">
        <Card.Content class="py-4">
          <div class="flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
            <p class="text-sm text-orange-800">
              This proposal expires on <strong>{formatDate(proposal.expires_at)}</strong>
            </p>
          </div>
        </Card.Content>
      </Card.Root>
    {/if}

    <!-- Table of Contents (Sticky Sidebar) -->
    <div class="fixed right-4 top-24 w-48 hidden xl:block">
      <Card.Root>
        <Card.Header class="py-3">
          <Card.Title class="text-sm">Contents</Card.Title>
        </Card.Header>
        <Card.Content class="py-2">
          <nav class="space-y-1">
            {#each visibleSections as section}
              <a
                href="#section-{section.id}"
                class="block text-sm text-muted-foreground hover:text-foreground transition-colors py-1"
              >
                {section.title}
              </a>
            {/each}
            {#if proposal.settings?.show_pricing_breakdown}
              <a
                href="#pricing"
                class="block text-sm text-muted-foreground hover:text-foreground transition-colors py-1"
              >
                Pricing
              </a>
            {/if}
          </nav>
        </Card.Content>
      </Card.Root>
    </div>
  </main>
</div>

<!-- Decline Dialog -->
<Dialog.Root bind:open={showDeclineDialog}>
  <Dialog.Content>
    <Dialog.Header>
      <Dialog.Title>Decline Proposal</Dialog.Title>
      <Dialog.Description>
        Please let us know why you're declining this proposal.
      </Dialog.Description>
    </Dialog.Header>
    <div class="py-4">
      <Textarea
        bind:value={declineReason}
        placeholder="Reason for declining (optional)..."
        class="min-h-[100px]"
      />
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => showDeclineDialog = false}>Cancel</Button>
      <Button variant="destructive" onclick={handleDecline}>
        Decline Proposal
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
