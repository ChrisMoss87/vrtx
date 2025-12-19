<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { ProposalList } from '$lib/components/proposals';
  import { proposalsApi, type Proposal } from '$lib/api/proposals';

  let proposals = $state<Proposal[]>([]);
  let loading = $state(true);

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

  function handleView(id: number) {
    goto(`/proposals/${id}`);
  }

  function handleEdit(id: number) {
    goto(`/proposals/${id}/edit`);
  }

  async function handleDuplicate(id: number) {
    try {
      const duplicated = await proposalsApi.duplicate(id);
      goto(`/proposals/${duplicated.id}/edit`);
    } catch (error) {
      console.error('Failed to duplicate proposal:', error);
    }
  }

  async function handleDelete(id: number) {
    if (confirm('Are you sure you want to delete this proposal?')) {
      try {
        await proposalsApi.delete(id);
        await loadProposals();
      } catch (error) {
        console.error('Failed to delete proposal:', error);
      }
    }
  }

  async function handleSend(id: number) {
    const email = prompt('Enter recipient email address:');
    if (!email) return;

    try {
      await proposalsApi.send(id, email);
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
    onCreate={handleCreate}
    onView={handleView}
    onEdit={handleEdit}
    onDuplicate={handleDuplicate}
    onDelete={handleDelete}
    onSend={handleSend}
  />
</div>
