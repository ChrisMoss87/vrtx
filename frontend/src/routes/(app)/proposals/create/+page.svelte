<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { ProposalBuilder } from '$lib/components/proposals';
  import { proposalsApi, proposalTemplatesApi, type Proposal, type CreateProposalData } from '$lib/api/proposals';

  let templates = $state<Array<{ id: number; name: string }>>([]);
  let loading = $state(false);

  onMount(async () => {
    await loadTemplates();
  });

  async function loadTemplates() {
    try {
      const response = await proposalTemplatesApi.list();
      templates = response.map(t => ({ id: t.id, name: t.name }));
    } catch (error) {
      console.error('Failed to load templates:', error);
    }
  }

  async function handleSave(data: Partial<Proposal>) {
    loading = true;
    try {
      // Convert Proposal to CreateProposalData
      const createData: CreateProposalData = {
        name: data.title || 'Untitled Proposal',
        template_id: data.template_id ?? undefined,
        deal_id: data.deal_id ?? undefined,
        contact_id: data.contact_id ?? undefined,
        company_id: data.company_id ?? undefined,
        cover_page: data.cover_page ?? undefined,
        styling: data.styling ?? undefined,
        currency: data.currency,
        valid_until: data.valid_until ?? undefined,
        assigned_to: data.assigned_to ?? undefined,
        sections: data.sections?.map(s => ({
          section_type: s.section_type,
          title: s.title,
          content: s.content ?? undefined,
          settings: s.settings ?? undefined,
          display_order: s.display_order,
        })),
        pricing_items: data.pricing_items?.map(p => ({
          name: p.name,
          description: p.description ?? undefined,
          quantity: p.quantity,
          unit: p.unit ?? undefined,
          unit_price: p.unit_price,
          discount_percent: p.discount_percent ?? undefined,
          is_optional: p.is_optional,
          pricing_type: p.pricing_type,
          billing_frequency: p.billing_frequency ?? undefined,
          product_id: p.product_id ?? undefined,
        })),
      };
      const created = await proposalsApi.create(createData);
      goto(`/proposals/${created.id}`);
    } catch (error) {
      console.error('Failed to create proposal:', error);
    } finally {
      loading = false;
    }
  }

  function handleCancel() {
    goto('/proposals');
  }

  function handlePreview() {
    alert('Preview functionality coming soon');
  }
</script>

<svelte:head>
  <title>Create Proposal | VRTX</title>
</svelte:head>

<div class="container py-6 max-w-6xl">
  <ProposalBuilder
    {templates}
    {loading}
    onSave={handleSave}
    onCancel={handleCancel}
    onPreview={handlePreview}
  />
</div>
