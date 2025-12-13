<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { ProposalList } from '$lib/components/proposals';
  import { proposalsApi, type Proposal } from '$lib/api/proposals';

  let proposals: Proposal[] = [];
  let loading = true;

  onMount(async () => {
    await loadProposals();
  });

  async function loadProposals() {
    loading = true;
    try {
      const response = await proposalsApi.list();
      proposals = response.data;
    } catch (error) {
      console.error('Failed to load proposals:', error);
    } finally {
      loading = false;
    }
  }

  function handleCreate() {
    goto('/proposals/create');
  }

  function handleView(event: CustomEvent<number>) {
    goto(`/proposals/${event.detail}`);
  }

  function handleEdit(event: CustomEvent<number>) {
    goto(`/proposals/${event.detail}/edit`);
  }

  async function handleDuplicate(event: CustomEvent<number>) {
    try {
      const duplicated = await proposalsApi.duplicate(event.detail);
      goto(`/proposals/${duplicated.id}/edit`);
    } catch (error) {
      console.error('Failed to duplicate proposal:', error);
    }
  }

  async function handleDelete(event: CustomEvent<number>) {
    if (confirm('Are you sure you want to delete this proposal?')) {
      try {
        await proposalsApi.delete(event.detail);
        await loadProposals();
      } catch (error) {
        console.error('Failed to delete proposal:', error);
      }
    }
  }

  async function handleSend(event: CustomEvent<number>) {
    try {
      await proposalsApi.send(event.detail);
      await loadProposals();
      alert('Proposal sent successfully');
    } catch (error) {
      console.error('Failed to send proposal:', error);
    }
  }
</script>

<svelte:head>
  <title>Proposals | VRTX</title>
</svelte:head>

<div class="container py-6">
  <div class="mb-6">
    <h1 class="text-2xl font-bold">Proposals</h1>
    <p class="text-muted-foreground">Create and manage interactive proposals</p>
  </div>

  <ProposalList
    {proposals}
    {loading}
    on:create={handleCreate}
    on:view={handleView}
    on:edit={handleEdit}
    on:duplicate={handleDuplicate}
    on:delete={handleDelete}
    on:send={handleSend}
  />
</div>
