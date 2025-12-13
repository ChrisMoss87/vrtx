<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { ProposalBuilder } from '$lib/components/proposals';
  import { proposalsApi, type Proposal } from '$lib/api/proposals';

  let templates: Array<{ id: number; name: string }> = [];
  let loading = false;

  onMount(async () => {
    await loadTemplates();
  });

  async function loadTemplates() {
    try {
      const response = await proposalsApi.getTemplates();
      templates = response.data;
    } catch (error) {
      console.error('Failed to load templates:', error);
    }
  }

  async function handleSave(event: CustomEvent<Partial<Proposal>>) {
    loading = true;
    try {
      const created = await proposalsApi.create(event.detail);
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
    // Would open preview modal
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
    on:save={handleSave}
    on:cancel={handleCancel}
    on:preview={handlePreview}
  />
</div>
