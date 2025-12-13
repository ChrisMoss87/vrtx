<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/stores';
  import { PublicSigningView } from '$lib/components/e-signatures';
  import { publicSigningApi, type SignatureRequest, type SignatureSigner, type SignatureField } from '$lib/api/signatures';

  const uuid = $derived($page.params.uuid);

  let request = $state<SignatureRequest | null>(null);
  let signer = $state<SignatureSigner | null>(null);
  let fields = $state<SignatureField[]>([]);
  let documentUrl = $state('');
  let loading = $state(true);
  let error = $state('');

  onMount(async () => {
    await loadSigningData();
  });

  async function loadSigningData() {
    loading = true;
    error = '';
    try {
      const data = await publicSigningApi.getSigningData(uuid);
      request = data.request;
      signer = data.signer;
      fields = data.fields;
      documentUrl = data.document_url;
    } catch (err) {
      console.error('Failed to load signing data:', err);
      error = 'This signing link is invalid or has expired.';
    } finally {
      loading = false;
    }
  }

  async function handleSign(event: CustomEvent<{ fieldId: number; signature: string }>) {
    try {
      await publicSigningApi.signField(uuid, event.detail.fieldId, event.detail.signature);
    } catch (err) {
      console.error('Failed to sign field:', err);
    }
  }

  async function handleComplete() {
    loading = true;
    try {
      await publicSigningApi.complete(uuid);
      // Show success message
      request = { ...request!, status: 'completed' } as SignatureRequest;
    } catch (err) {
      console.error('Failed to complete signing:', err);
    } finally {
      loading = false;
    }
  }

  async function handleDecline(event: CustomEvent<string>) {
    loading = true;
    try {
      await publicSigningApi.decline(uuid, event.detail);
      // Show declined message
      request = { ...request!, status: 'declined' } as SignatureRequest;
    } catch (err) {
      console.error('Failed to decline:', err);
    } finally {
      loading = false;
    }
  }
</script>

<svelte:head>
  <title>{request?.title || 'Sign Document'}</title>
</svelte:head>

{#if loading && !request}
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
      <p class="text-muted-foreground">Loading document...</p>
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
      <h1 class="text-2xl font-bold mb-2">Unable to Load Document</h1>
      <p class="text-muted-foreground">{error}</p>
    </div>
  </div>
{:else if request?.status === 'completed'}
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="text-center max-w-md">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-green-500 mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
      </svg>
      <h1 class="text-2xl font-bold mb-2">Document Signed Successfully</h1>
      <p class="text-muted-foreground">Thank you for signing. You will receive a copy via email.</p>
    </div>
  </div>
{:else if request?.status === 'declined'}
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="text-center max-w-md">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10" />
        <line x1="15" y1="9" x2="9" y2="15" />
        <line x1="9" y1="9" x2="15" y2="15" />
      </svg>
      <h1 class="text-2xl font-bold mb-2">Signing Declined</h1>
      <p class="text-muted-foreground">You have declined to sign this document. The sender has been notified.</p>
    </div>
  </div>
{:else if request && signer}
  <PublicSigningView
    {request}
    {signer}
    {fields}
    {documentUrl}
    {loading}
    on:sign={handleSign}
    on:complete={handleComplete}
    on:decline={handleDecline}
  />
{/if}
