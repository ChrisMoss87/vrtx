<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Card from '$lib/components/ui/card';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Textarea } from '$lib/components/ui/textarea';
  import { Label } from '$lib/components/ui/label';
  import type { SignatureRequest, SignatureSigner, SignatureField } from '$lib/api/signatures';
  import SignaturePad from './SignaturePad.svelte';

  export let request: SignatureRequest;
  export let signer: SignatureSigner;
  export let fields: SignatureField[] = [];
  export let documentUrl: string;
  export let loading = false;

  const dispatch = createEventDispatcher<{
    sign: { fieldId: number; signature: string };
    complete: void;
    decline: string;
  }>();

  let currentPage = 1;
  let totalPages = 1;
  let showSignaturePad = false;
  let activeFieldId: number | null = null;
  let showDeclineDialog = false;
  let declineReason = '';
  let signedFields: Set<number> = new Set();

  $: pageFields = fields.filter(f => f.page === currentPage && f.signer_id === signer.id);
  $: allFieldsSigned = fields.filter(f => f.signer_id === signer.id && f.required).every(f => signedFields.has(f.id));
  $: pendingRequiredFields = fields.filter(f => f.signer_id === signer.id && f.required && !signedFields.has(f.id)).length;

  function openSignaturePad(fieldId: number) {
    activeFieldId = fieldId;
    showSignaturePad = true;
  }

  function handleSignature(signature: string) {
    if (activeFieldId) {
      dispatch('sign', { fieldId: activeFieldId, signature });
      signedFields.add(activeFieldId);
      signedFields = signedFields;
    }
    showSignaturePad = false;
    activeFieldId = null;
  }

  function handleDecline() {
    dispatch('decline', declineReason);
    showDeclineDialog = false;
  }

  function getFieldTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      signature: 'Click to sign',
      initials: 'Click to initial',
      date: 'Date',
      text: 'Enter text',
      checkbox: 'Check',
      name: 'Full name',
      email: 'Email',
      company: 'Company',
      title: 'Title',
    };
    return labels[type] || type;
  }
</script>

<div class="min-h-screen bg-gray-100">
  <!-- Header -->
  <header class="bg-white border-b sticky top-0 z-10">
    <div class="max-w-6xl mx-auto px-4 py-4">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-xl font-semibold">{request.title}</h1>
          <p class="text-sm text-muted-foreground">
            Signing as {signer.name} ({signer.email})
          </p>
        </div>
        <div class="flex items-center gap-4">
          {#if pendingRequiredFields > 0}
            <span class="text-sm text-muted-foreground">
              {pendingRequiredFields} required field{pendingRequiredFields !== 1 ? 's' : ''} remaining
            </span>
          {/if}
          {#if request.settings?.allow_decline}
            <Button variant="outline" on:click={() => showDeclineDialog = true}>
              Decline to Sign
            </Button>
          {/if}
          <Button on:click={() => dispatch('complete')} disabled={!allFieldsSigned || loading}>
            {loading ? 'Submitting...' : 'Finish Signing'}
          </Button>
        </div>
      </div>
    </div>
  </header>

  <!-- Document Viewer -->
  <main class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex gap-6">
      <!-- Document -->
      <div class="flex-1">
        <Card.Root>
          <Card.Content class="p-0">
            <!-- Page Navigation -->
            <div class="flex items-center justify-between p-3 bg-muted border-b">
              <Button variant="outline" size="sm" disabled={currentPage === 1} on:click={() => currentPage--}>
                Previous
              </Button>
              <span class="text-sm">Page {currentPage} of {totalPages}</span>
              <Button variant="outline" size="sm" disabled={currentPage === totalPages} on:click={() => currentPage++}>
                Next
              </Button>
            </div>

            <!-- Document Area -->
            <div class="relative bg-white">
              <img src={documentUrl} alt="Document page {currentPage}" class="w-full" />

              <!-- Signature Fields -->
              {#each pageFields as field}
                <button
                  type="button"
                  class="absolute border-2 rounded transition-all {signedFields.has(field.id) ? 'border-green-500 bg-green-50' : 'border-primary bg-primary/5 hover:bg-primary/10 animate-pulse'}"
                  style="left: {field.x}px; top: {field.y}px; width: {field.width}px; height: {field.height}px;"
                  on:click={() => openSignaturePad(field.id)}
                  disabled={signedFields.has(field.id)}
                >
                  {#if signedFields.has(field.id)}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  {:else}
                    <span class="text-xs text-primary font-medium">
                      {getFieldTypeLabel(field.type)}
                    </span>
                  {/if}
                </button>
              {/each}
            </div>
          </Card.Content>
        </Card.Root>
      </div>

      <!-- Sidebar -->
      <div class="w-80">
        <Card.Root class="sticky top-24">
          <Card.Header>
            <Card.Title class="text-lg">Signing Progress</Card.Title>
          </Card.Header>
          <Card.Content class="space-y-4">
            {#each fields.filter(f => f.signer_id === signer.id) as field}
              <div class="flex items-center justify-between p-2 rounded border {signedFields.has(field.id) ? 'border-green-200 bg-green-50' : 'border-muted'}">
                <div class="flex items-center gap-2">
                  {#if signedFields.has(field.id)}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  {:else}
                    <div class="h-4 w-4 rounded-full border-2 border-muted"></div>
                  {/if}
                  <span class="text-sm">{getFieldTypeLabel(field.type)}</span>
                </div>
                <span class="text-xs text-muted-foreground">Page {field.page}</span>
              </div>
            {/each}

            {#if request.message}
              <div class="pt-4 border-t">
                <p class="text-sm font-medium mb-2">Message from sender:</p>
                <p class="text-sm text-muted-foreground">{request.message}</p>
              </div>
            {/if}
          </Card.Content>
        </Card.Root>
      </div>
    </div>
  </main>
</div>

<!-- Signature Pad Dialog -->
<Dialog.Root bind:open={showSignaturePad}>
  <Dialog.Content class="max-w-lg">
    <Dialog.Header>
      <Dialog.Title>Add Your Signature</Dialog.Title>
      <Dialog.Description>
        Draw, type, or upload your signature below
      </Dialog.Description>
    </Dialog.Header>
    <SignaturePad
      on:sign={(e) => handleSignature(e.detail)}
      on:cancel={() => showSignaturePad = false}
    />
  </Dialog.Content>
</Dialog.Root>

<!-- Decline Dialog -->
<Dialog.Root bind:open={showDeclineDialog}>
  <Dialog.Content>
    <Dialog.Header>
      <Dialog.Title>Decline to Sign</Dialog.Title>
      <Dialog.Description>
        Are you sure you want to decline signing this document?
      </Dialog.Description>
    </Dialog.Header>
    <div class="py-4">
      {#if request.settings?.require_reason}
        <div class="space-y-2">
          <Label for="reason">Reason for declining (required)</Label>
          <Textarea
            id="reason"
            bind:value={declineReason}
            placeholder="Please provide a reason for declining..."
          />
        </div>
      {/if}
    </div>
    <Dialog.Footer>
      <Button variant="outline" on:click={() => showDeclineDialog = false}>Cancel</Button>
      <Button
        variant="destructive"
        on:click={handleDecline}
        disabled={request.settings?.require_reason && !declineReason}
      >
        Decline to Sign
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
