<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { SignatureRequestList } from '$lib/components/e-signatures';
  import { signaturesApi, type SignatureRequest } from '$lib/api/signatures';

  let requests: SignatureRequest[] = [];
  let loading = true;

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

  function handleView(event: CustomEvent<number>) {
    goto(`/signatures/${event.detail}`);
  }

  async function handleVoid(event: CustomEvent<number>) {
    if (confirm('Are you sure you want to void this signature request?')) {
      try {
        await signaturesApi.void(event.detail);
        await loadRequests();
      } catch (error) {
        console.error('Failed to void request:', error);
      }
    }
  }

  async function handleRemind(event: CustomEvent<number>) {
    try {
      await signaturesApi.sendReminder(event.detail);
      alert('Reminder sent successfully');
    } catch (error) {
      console.error('Failed to send reminder:', error);
    }
  }

  async function handleDownload(event: CustomEvent<number>) {
    try {
      const blob = await signaturesApi.downloadSigned(event.detail);
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `signed-document-${event.detail}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
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
    on:create={handleCreate}
    on:view={handleView}
    on:void={handleVoid}
    on:remind={handleRemind}
    on:download={handleDownload}
  />
</div>
