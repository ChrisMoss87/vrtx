<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import * as Tabs from '$lib/components/ui/tabs';
  import * as Select from '$lib/components/ui/select';
  import type { Proposal, ProposalSection, ProposalPricingItem } from '$lib/api/proposals';
  import SectionEditor from './SectionEditor.svelte';
  import PricingTable from './PricingTable.svelte';
  import ProposalSettings from './ProposalSettings.svelte';

  interface Props {
    proposal?: Partial<Proposal>;
    templates?: Array<{ id: number; name: string }>;
    loading?: boolean;
    onSave?: (data: Partial<Proposal>) => void;
    onPreview?: () => void;
    onSend?: () => void;
    onCancel?: () => void;
  }

  let {
    proposal = {},
    templates = [],
    loading = false,
    onSave,
    onPreview,
    onSend,
    onCancel,
  }: Props = $props();

  let title = $state(proposal.title || '');
  let clientName = $state(proposal.client_name || '');
  let clientEmail = $state(proposal.client_email || '');
  let clientCompany = $state(proposal.client_company || '');
  let expiresAt = $state(proposal.expires_at ? proposal.expires_at.split('T')[0] : '');
  let coverLetter = $state(proposal.cover_letter || '');
  let sections = $state<ProposalSection[]>(proposal.sections || []);
  let pricingItems = $state<ProposalPricingItem[]>(proposal.pricing_items || []);
  let settings = $state(proposal.settings || {
    allow_comments: true,
    allow_e_signature: true,
    show_pricing_breakdown: true,
    require_acceptance: true,
  });

  let selectedTemplateId = $state('');

  function handleSave() {
    onSave?.({
      ...proposal,
      title,
      client_name: clientName,
      client_email: clientEmail,
      client_company: clientCompany || null,
      expires_at: expiresAt || null,
      cover_letter: coverLetter || null,
      sections,
      pricing_items: pricingItems,
      settings,
    });
  }

  function addSection() {
    sections = [
      ...sections,
      {
        id: 0,
        proposal_id: proposal.id || 0,
        title: 'New Section',
        content: '',
        order: sections.length,
        is_visible: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      },
    ];
  }

  function removeSection(index: number) {
    sections = sections.filter((_: ProposalSection, i: number) => i !== index);
    sections = sections.map((s: ProposalSection, i: number) => ({ ...s, order: i }));
  }

  function moveSection(index: number, direction: 'up' | 'down') {
    if (direction === 'up' && index > 0) {
      [sections[index - 1], sections[index]] = [sections[index], sections[index - 1]];
    } else if (direction === 'down' && index < sections.length - 1) {
      [sections[index], sections[index + 1]] = [sections[index + 1], sections[index]];
    }
    sections = sections.map((s: ProposalSection, i: number) => ({ ...s, order: i }));
  }

  const totalAmount = $derived(pricingItems.reduce((sum: number, item: ProposalPricingItem) => {
    const subtotal = item.quantity * item.unit_price;
    const discount = item.discount_percent ? subtotal * (item.discount_percent / 100) : 0;
    return sum + (subtotal - discount);
  }, 0));
</script>

<div class="space-y-6">
  <Card.Root>
    <Card.Header>
      <div class="flex justify-between items-start">
        <div>
          <Card.Title>{proposal.id ? 'Edit Proposal' : 'Create Proposal'}</Card.Title>
          <Card.Description>
            Build an interactive proposal for your client
          </Card.Description>
        </div>
        <div class="flex gap-2">
          <Button variant="outline" onclick={() => onPreview?.()}>Preview</Button>
          {#if proposal.id && proposal.status === 'draft'}
            <Button onclick={() => onSend?.()}>Send to Client</Button>
          {/if}
        </div>
      </div>
    </Card.Header>
    <Card.Content>
      <Tabs.Root value="details">
        <Tabs.List class="mb-4">
          <Tabs.Trigger value="details">Details</Tabs.Trigger>
          <Tabs.Trigger value="content">Content</Tabs.Trigger>
          <Tabs.Trigger value="pricing">Pricing</Tabs.Trigger>
          <Tabs.Trigger value="settings">Settings</Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="details" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="title">Proposal Title</Label>
              <Input id="title" bind:value={title} placeholder="e.g., Website Redesign Proposal" />
            </div>
            <div class="space-y-2">
              <Label for="expires">Expiration Date</Label>
              <Input id="expires" type="date" bind:value={expiresAt} />
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label for="clientName">Client Name</Label>
              <Input id="clientName" bind:value={clientName} placeholder="John Doe" />
            </div>
            <div class="space-y-2">
              <Label for="clientEmail">Client Email</Label>
              <Input id="clientEmail" type="email" bind:value={clientEmail} placeholder="john@example.com" />
            </div>
            <div class="space-y-2">
              <Label for="clientCompany">Company (Optional)</Label>
              <Input id="clientCompany" bind:value={clientCompany} placeholder="Acme Inc." />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="coverLetter">Cover Letter / Introduction</Label>
            <Textarea
              id="coverLetter"
              bind:value={coverLetter}
              placeholder="Write a personalized introduction for your client..."
              class="min-h-[150px]"
            />
          </div>

          {#if templates.length > 0}
            <div class="p-4 rounded-lg border bg-muted/50">
              <Label class="mb-2 block">Start from Template</Label>
              <Select.Root type="single" bind:value={selectedTemplateId}>
                <Select.Trigger class="w-64">
                  {templates.find(t => t.id.toString() === selectedTemplateId)?.name || 'Select a template'}
                </Select.Trigger>
                <Select.Content>
                  {#each templates as template}
                    <Select.Item value={template.id.toString()} label={template.name}>{template.name}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>
          {/if}
        </Tabs.Content>

        <Tabs.Content value="content" class="space-y-4">
          <div class="flex justify-between items-center">
            <div>
              <h4 class="font-medium">Proposal Sections</h4>
              <p class="text-sm text-muted-foreground">Add and organize your proposal content</p>
            </div>
            <Button variant="outline" onclick={addSection}>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
              </svg>
              Add Section
            </Button>
          </div>

          {#if sections.length === 0}
            <Card.Root>
              <Card.Content class="py-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                  <line x1="12" y1="18" x2="12" y2="12" />
                  <line x1="9" y1="15" x2="15" y2="15" />
                </svg>
                <p class="text-muted-foreground">No sections added yet</p>
                <Button variant="outline" class="mt-4" onclick={addSection}>
                  Add your first section
                </Button>
              </Card.Content>
            </Card.Root>
          {:else}
            <div class="space-y-4">
              {#each sections as section, index}
                <SectionEditor
                  bind:section={sections[index]}
                  {index}
                  total={sections.length}
                  onRemove={() => removeSection(index)}
                  onMoveUp={() => moveSection(index, 'up')}
                  onMoveDown={() => moveSection(index, 'down')}
                />
              {/each}
            </div>
          {/if}
        </Tabs.Content>

        <Tabs.Content value="pricing" class="space-y-4">
          <PricingTable bind:items={pricingItems} />

          <div class="flex justify-end">
            <Card.Root class="w-80">
              <Card.Content class="pt-6">
                <div class="space-y-2">
                  <div class="flex justify-between text-sm">
                    <span>Subtotal</span>
                    <span>${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                  </div>
                  <div class="flex justify-between font-medium text-lg pt-2 border-t">
                    <span>Total</span>
                    <span>${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                  </div>
                </div>
              </Card.Content>
            </Card.Root>
          </div>
        </Tabs.Content>

        <Tabs.Content value="settings">
          <ProposalSettings bind:settings />
        </Tabs.Content>
      </Tabs.Root>
    </Card.Content>
    <Card.Footer class="flex justify-between">
      <Button variant="outline" onclick={() => onCancel?.()}>Cancel</Button>
      <Button onclick={handleSave} disabled={loading || !title || !clientName || !clientEmail}>
        {loading ? 'Saving...' : 'Save Proposal'}
      </Button>
    </Card.Footer>
  </Card.Root>
</div>
