<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import type { SignatureRequest, SignatureSigner } from '$lib/api/signatures';
  import SignerManager from './SignerManager.svelte';
  import FieldPlacer from './FieldPlacer.svelte';

  export let request: Partial<SignatureRequest> = {};
  export let documentUrl: string | null = null;
  export let loading = false;

  const dispatch = createEventDispatcher<{
    save: Partial<SignatureRequest>;
    cancel: void;
    uploadDocument: void;
  }>();

  let title = $state(request.title || '');
  let message = $state(request.message || '');
  let expiresAt = $state(request.expires_at ? request.expires_at.split('T')[0] : '');
  let reminderDays = $state(request.settings?.reminder_days || 3);
  let allowDecline = $state(request.settings?.allow_decline ?? true);
  let requireReason = $state(request.settings?.require_reason ?? false);
  let signers = $state<Partial<SignatureSigner>[]>(request.signers || []);
  let fields = $state<Array<{
    signer_index: number;
    type: string;
    page: number;
    x: number;
    y: number;
    width: number;
    height: number;
    required: boolean;
    label?: string;
  }>>([]);

  let currentStep = $state<'details' | 'signers' | 'fields'>('details');

  function handleSave() {
    dispatch('save', {
      ...request,
      title,
      message: message || null,
      expires_at: expiresAt || null,
      signers,
      fields,
      settings: {
        reminder_days: reminderDays,
        allow_decline: allowDecline,
        require_reason: requireReason,
      },
    });
  }

  function canProceed(): boolean {
    if (currentStep === 'details') {
      return !!title && !!documentUrl;
    }
    if (currentStep === 'signers') {
      return signers.length > 0 && signers.every(s => s.name && s.email);
    }
    return true;
  }

  function nextStep() {
    if (currentStep === 'details') currentStep = 'signers';
    else if (currentStep === 'signers') currentStep = 'fields';
  }

  function prevStep() {
    if (currentStep === 'fields') currentStep = 'signers';
    else if (currentStep === 'signers') currentStep = 'details';
  }
</script>

<div class="space-y-6">
  <Card.Root>
    <Card.Header>
      <Card.Title>{request.id ? 'Edit Signature Request' : 'Create Signature Request'}</Card.Title>
      <Card.Description>
        Set up your document for electronic signatures
      </Card.Description>
    </Card.Header>
    <Card.Content>
      <!-- Progress Steps -->
      <div class="flex items-center justify-center mb-8">
        <div class="flex items-center">
          <div class="flex items-center justify-center w-10 h-10 rounded-full {currentStep === 'details' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}">
            1
          </div>
          <span class="ml-2 text-sm {currentStep === 'details' ? 'font-medium' : 'text-muted-foreground'}">Details</span>
        </div>
        <div class="w-16 h-0.5 mx-2 {currentStep !== 'details' ? 'bg-primary' : 'bg-muted'}"></div>
        <div class="flex items-center">
          <div class="flex items-center justify-center w-10 h-10 rounded-full {currentStep === 'signers' ? 'bg-primary text-primary-foreground' : currentStep === 'fields' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}">
            2
          </div>
          <span class="ml-2 text-sm {currentStep === 'signers' ? 'font-medium' : 'text-muted-foreground'}">Signers</span>
        </div>
        <div class="w-16 h-0.5 mx-2 {currentStep === 'fields' ? 'bg-primary' : 'bg-muted'}"></div>
        <div class="flex items-center">
          <div class="flex items-center justify-center w-10 h-10 rounded-full {currentStep === 'fields' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}">
            3
          </div>
          <span class="ml-2 text-sm {currentStep === 'fields' ? 'font-medium' : 'text-muted-foreground'}">Fields</span>
        </div>
      </div>

      {#if currentStep === 'details'}
        <div class="space-y-4">
          <div class="space-y-2">
            <Label for="title">Request Title</Label>
            <Input id="title" bind:value={title} placeholder="e.g., Sales Contract - Acme Corp" />
          </div>

          <div class="space-y-2">
            <Label for="message">Message to Signers (Optional)</Label>
            <Textarea
              id="message"
              bind:value={message}
              placeholder="Add a message that will be included in the signature request email"
              class="min-h-[100px]"
            />
          </div>

          <div class="space-y-2">
            <Label>Document</Label>
            {#if documentUrl}
              <div class="flex items-center gap-4 p-4 border rounded-lg bg-muted/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
                <div class="flex-1">
                  <p class="font-medium">Document uploaded</p>
                  <p class="text-sm text-muted-foreground">Ready for signature fields</p>
                </div>
                <Button variant="outline" size="sm" onclick={() => dispatch('uploadDocument')}>
                  Replace
                </Button>
              </div>
            {:else}
              <button
                type="button"
                class="flex flex-col items-center justify-center p-8 border-2 border-dashed rounded-lg cursor-pointer hover:border-primary transition-colors w-full"
                onclick={() => dispatch('uploadDocument')}
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="17 8 12 3 7 8" />
                  <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
                <p class="text-muted-foreground">Click to upload a document</p>
                <p class="text-sm text-muted-foreground">PDF, DOCX up to 10MB</p>
              </button>
            {/if}
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="expires">Expiration Date (Optional)</Label>
              <Input id="expires" type="date" bind:value={expiresAt} />
            </div>
            <div class="space-y-2">
              <Label for="reminder">Reminder Interval (Days)</Label>
              <Input id="reminder" type="number" bind:value={reminderDays} min="1" max="30" />
            </div>
          </div>

          <div class="flex gap-4">
            <div class="flex items-center gap-2">
              <input type="checkbox" id="allowDecline" bind:checked={allowDecline} class="rounded" />
              <Label for="allowDecline">Allow signers to decline</Label>
            </div>
            <div class="flex items-center gap-2">
              <input type="checkbox" id="requireReason" bind:checked={requireReason} class="rounded" />
              <Label for="requireReason">Require reason if declined</Label>
            </div>
          </div>
        </div>
      {:else if currentStep === 'signers'}
        <SignerManager bind:signers />
      {:else if currentStep === 'fields'}
        <FieldPlacer {documentUrl} {signers} bind:fields />
      {/if}
    </Card.Content>
    <Card.Footer class="flex justify-between">
      <div>
        {#if currentStep !== 'details'}
          <Button variant="outline" onclick={prevStep}>Back</Button>
        {:else}
          <Button variant="outline" onclick={() => dispatch('cancel')}>Cancel</Button>
        {/if}
      </div>
      <div class="flex gap-2">
        {#if currentStep !== 'fields'}
          <Button onclick={nextStep} disabled={!canProceed()}>
            Continue
          </Button>
        {:else}
          <Button onclick={handleSave} disabled={loading || !canProceed()}>
            {loading ? 'Sending...' : 'Send for Signature'}
          </Button>
        {/if}
      </div>
    </Card.Footer>
  </Card.Root>
</div>
