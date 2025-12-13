<script lang="ts">
  import { Badge } from '$lib/components/ui/badge';
  import { Button } from '$lib/components/ui/button';
  import * as Card from '$lib/components/ui/card';
  import * as Progress from '$lib/components/ui/progress';
  import type { SignatureRequest } from '$lib/api/signatures';
  import { createEventDispatcher } from 'svelte';

  export let request: SignatureRequest;

  const dispatch = createEventDispatcher<{
    remind: number;
    void: void;
    download: void;
    viewAuditLog: void;
  }>();

  const statusColors: Record<string, string> = {
    draft: 'bg-gray-500',
    pending: 'bg-yellow-500',
    in_progress: 'bg-blue-500',
    completed: 'bg-green-500',
    declined: 'bg-red-500',
    voided: 'bg-gray-500',
    expired: 'bg-orange-500',
  };

  const signerStatusColors: Record<string, string> = {
    pending: 'text-yellow-600 bg-yellow-50',
    sent: 'text-blue-600 bg-blue-50',
    viewed: 'text-purple-600 bg-purple-50',
    signed: 'text-green-600 bg-green-50',
    declined: 'text-red-600 bg-red-50',
  };

  const signedCount = $derived(request.signers?.filter(s => s.status === 'signed').length || 0);
  const totalSigners = $derived(request.signers?.length || 0);
  const progress = $derived(totalSigners > 0 ? (signedCount / totalSigners) * 100 : 0);

  function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function getStatusLabel(status: string): string {
    return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
  }
</script>

<Card.Root>
  <Card.Header>
    <div class="flex justify-between items-start">
      <div>
        <Card.Title>{request.title}</Card.Title>
        <Card.Description>
          Created {formatDate(request.created_at)}
        </Card.Description>
      </div>
      <Badge class={statusColors[request.status]}>
        {getStatusLabel(request.status)}
      </Badge>
    </div>
  </Card.Header>
  <Card.Content class="space-y-6">
    <!-- Progress -->
    <div class="space-y-2">
      <div class="flex justify-between text-sm">
        <span>Signing Progress</span>
        <span class="text-muted-foreground">{signedCount} of {totalSigners} signed</span>
      </div>
      <Progress.Root value={progress} class="h-2" />
    </div>

    <!-- Signers List -->
    <div class="space-y-3">
      <h4 class="text-sm font-medium">Signers</h4>
      {#each request.signers || [] as signer, index}
        <div class="flex items-center justify-between p-3 rounded-lg border">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium">
              {index + 1}
            </div>
            <div>
              <p class="font-medium">{signer.name}</p>
              <p class="text-sm text-muted-foreground">{signer.email}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <Badge variant="outline" class={signerStatusColors[signer.status]}>
              {getStatusLabel(signer.status)}
            </Badge>
            {#if signer.status === 'pending' || signer.status === 'sent'}
              <Button variant="ghost" size="sm" onclick={() => dispatch('remind', signer.id)}>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                  <polyline points="22,6 12,13 2,6" />
                </svg>
              </Button>
            {/if}
          </div>
        </div>
        {#if signer.signed_at}
          <div class="ml-11 text-xs text-muted-foreground">
            Signed on {formatDate(signer.signed_at)}
          </div>
        {/if}
        {#if signer.viewed_at && !signer.signed_at}
          <div class="ml-11 text-xs text-muted-foreground">
            Viewed on {formatDate(signer.viewed_at)}
          </div>
        {/if}
      {/each}
    </div>

    <!-- Timeline -->
    {#if request.expires_at}
      <div class="p-3 rounded-lg bg-muted">
        <div class="flex items-center gap-2 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
          </svg>
          <span>Expires on {formatDate(request.expires_at)}</span>
        </div>
      </div>
    {/if}
  </Card.Content>
  <Card.Footer class="flex justify-between">
    <div class="flex gap-2">
      <Button variant="outline" size="sm" onclick={() => dispatch('viewAuditLog')}>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <line x1="16" y1="13" x2="8" y2="13" />
          <line x1="16" y1="17" x2="8" y2="17" />
          <polyline points="10 9 9 9 8 9" />
        </svg>
        Audit Log
      </Button>
      {#if request.status === 'pending' || request.status === 'in_progress'}
        <Button variant="outline" size="sm" class="text-destructive" onclick={() => dispatch('void')}>
          Void Request
        </Button>
      {/if}
    </div>
    {#if request.status === 'completed'}
      <Button onclick={() => dispatch('download')}>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" />
          <line x1="12" y1="15" x2="12" y2="3" />
        </svg>
        Download Signed Document
      </Button>
    {/if}
  </Card.Footer>
</Card.Root>
