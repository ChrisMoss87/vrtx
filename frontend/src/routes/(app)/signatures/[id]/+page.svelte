<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import { SignatureStatus, AuditLog } from '$lib/components/e-signatures';
  import { signaturesApi, type SignatureRequest, type SignatureAuditLog } from '$lib/api/signatures';

  let request: SignatureRequest | null = null;
  let auditLogs: SignatureAuditLog[] = [];
  let loading = true;
  let showAuditLog = false;

  $: requestId = parseInt($page.params.id);

  onMount(async () => {
    await loadRequest();
  });

  async function loadRequest() {
    loading = true;
    try {
      request = await signaturesApi.get(requestId);
      auditLogs = await signaturesApi.getAuditLog(requestId);
    } catch (error) {
      console.error('Failed to load signature request:', error);
    } finally {
      loading = false;
    }
  }

  async function handleRemind(event: CustomEvent<number>) {
    try {
      await signaturesApi.remindSigner(requestId, event.detail);
      alert('Reminder sent successfully');
    } catch (error) {
      console.error('Failed to send reminder:', error);
    }
  }

  async function handleVoid() {
    if (confirm('Are you sure you want to void this signature request?')) {
      try {
        await signaturesApi.void(requestId);
        goto('/signatures');
      } catch (error) {
        console.error('Failed to void request:', error);
      }
    }
  }

  async function handleDownload() {
    try {
      const blob = await signaturesApi.downloadSigned(requestId);
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `signed-document-${requestId}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Failed to download document:', error);
    }
  }
</script>

<svelte:head>
  <title>{request?.title || 'Signature Request'} | VRTX</title>
</svelte:head>

<div class="container py-6 max-w-4xl">
  {#if loading}
    <div class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>
  {:else if request}
    <div class="mb-6">
      <button class="text-sm text-muted-foreground hover:text-foreground" on:click={() => goto('/signatures')}>
        ← Back to Signatures
      </button>
    </div>

    <div class="space-y-6">
      <SignatureStatus
        {request}
        on:remind={handleRemind}
        on:void={handleVoid}
        on:download={handleDownload}
        on:viewAuditLog={() => showAuditLog = !showAuditLog}
      />

      {#if showAuditLog}
        <AuditLog logs={auditLogs} />
      {/if}
    </div>
  {:else}
    <div class="text-center py-12">
      <p class="text-muted-foreground">Signature request not found</p>
    </div>
  {/if}
</div>
