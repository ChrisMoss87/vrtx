<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { SignatureRequestList } from '$lib/components/e-signatures';
  import { signaturesApi, type SignatureRequest } from '$lib/api/signatures';

  let requests = $state<SignatureRequest[]>([]);
  let loading = $state(true);

  onMount(async () => {
    await loadRequests();
  });

  async function loadRequests() {
    loading = true;
    try {
      const response = await signaturesApi.list();
      requests = response.data;
    } catch (error) {
      console.error('Failed to load signature requests:', error);
    } finally {
      loading = false;
    }
  }

  function handleCreate() {
    goto('/signatures/create');
  }

  function handleView(id: number) {
    goto(`/signatures/${id}`);
  }

  async function handleVoid(id: number) {
    const reason = prompt('Please provide a reason for voiding this request:');
    if (reason) {
      try {
        await signaturesApi.void(id, reason);
        await loadRequests();
      } catch (error) {
        console.error('Failed to void request:', error);
      }
    }
  }

  async function handleRemind(id: number) {
    try {
      await signaturesApi.remind(id);
      alert('Reminder sent successfully');
    } catch (error) {
      console.error('Failed to send reminder:', error);
    }
  }

  async function handleDownload(id: number) {
    try {
      // Find the request to get the signed file URL
      const request = requests.find(r => r.id === id);
      if (request?.signed_file_url) {
        window.open(request.signed_file_url, '_blank');
      } else {
        console.error('No signed document available');
      }
    } catch (error) {
      console.error('Failed to download document:', error);
    }
  }
</script>

<svelte:head>
  <title>E-Signatures | VRTX</title>
</svelte:head>

<div class="container py-6">
  <div class="mb-6">
    <h1 class="text-2xl font-bold">E-Signatures</h1>
    <p class="text-muted-foreground">Manage electronic signature requests</p>
  </div>

  <SignatureRequestList
    {requests}
    {loading}
    onCreate={handleCreate}
    onView={handleView}
    onVoid={handleVoid}
    onRemind={handleRemind}
    onDownload={handleDownload}
  />
</div>
