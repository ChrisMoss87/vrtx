<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import type { SignatureSigner } from '$lib/api/signatures';

  export let signers: Partial<SignatureSigner>[] = [];

  const signerColors = [
    'bg-blue-500',
    'bg-green-500',
    'bg-purple-500',
    'bg-orange-500',
    'bg-pink-500',
    'bg-cyan-500',
  ];

  function addSigner() {
    signers = [
      ...signers,
      {
        name: '',
        email: '',
        role: 'signer',
        order: signers.length + 1,
      },
    ];
  }

  function removeSigner(index: number) {
    signers = signers.filter((_: Partial<SignatureSigner>, i: number) => i !== index);
    // Reorder remaining signers
    signers = signers.map((s: Partial<SignatureSigner>, i: number) => ({ ...s, order: i + 1 }));
  }

  function moveUp(index: number) {
    if (index === 0) return;
    const newSigners = [...signers];
    [newSigners[index - 1], newSigners[index]] = [newSigners[index], newSigners[index - 1]];
    signers = newSigners.map((s: Partial<SignatureSigner>, i: number) => ({ ...s, order: i + 1 }));
  }

  function moveDown(index: number) {
    if (index === signers.length - 1) return;
    const newSigners = [...signers];
    [newSigners[index], newSigners[index + 1]] = [newSigners[index + 1], newSigners[index]];
    signers = newSigners.map((s: Partial<SignatureSigner>, i: number) => ({ ...s, order: i + 1 }));
  }

  function updateSignerRole(index: number, role: string) {
    signers[index].role = role as 'signer' | 'approver' | 'viewer';
    signers = signers;
  }

  function getRoleLabel(role: string | undefined): string {
    if (role === 'approver') return 'Approver';
    if (role === 'viewer') return 'Viewer (CC)';
    return 'Signer';
  }
</script>

<div class="space-y-4">
  <div class="flex justify-between items-center">
    <div>
      <h4 class="font-medium">Signers</h4>
      <p class="text-sm text-muted-foreground">Add people who need to sign this document</p>
    </div>
    <Button variant="outline" onclick={addSigner}>
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19" />
        <line x1="5" y1="12" x2="19" y2="12" />
      </svg>
      Add Signer
    </Button>
  </div>

  {#if signers.length === 0}
    <Card.Root>
      <Card.Content class="py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        <p class="text-muted-foreground">No signers added yet</p>
        <Button variant="outline" class="mt-4" onclick={addSigner}>
          Add your first signer
        </Button>
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="space-y-3">
      {#each signers as signer, index}
        <Card.Root class="p-4">
          <div class="flex gap-4">
            <div class="flex flex-col items-center gap-1">
              <div class="w-8 h-8 rounded-full {signerColors[index % signerColors.length]} flex items-center justify-center text-white font-medium text-sm">
                {index + 1}
              </div>
              <div class="flex flex-col gap-0.5">
                <button
                  type="button"
                  class="p-0.5 hover:bg-muted rounded disabled:opacity-30"
                  disabled={index === 0}
                  onclick={() => moveUp(index)}
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="18 15 12 9 6 15" />
                  </svg>
                </button>
                <button
                  type="button"
                  class="p-0.5 hover:bg-muted rounded disabled:opacity-30"
                  disabled={index === signers.length - 1}
                  onclick={() => moveDown(index)}
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9" />
                  </svg>
                </button>
              </div>
            </div>

            <div class="flex-1 grid grid-cols-3 gap-4">
              <div class="space-y-1">
                <Label class="text-xs text-muted-foreground">Name</Label>
                <Input bind:value={signer.name} placeholder="Full name" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs text-muted-foreground">Email</Label>
                <Input bind:value={signer.email} type="email" placeholder="email@example.com" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs text-muted-foreground">Role</Label>
                <Select.Root type="single" value={signer.role || 'signer'} onValueChange={(v) => updateSignerRole(index, v)}>
                  <Select.Trigger>
                    {getRoleLabel(signer.role)}
                  </Select.Trigger>
                  <Select.Content>
                    <Select.Item value="signer" label="Signer">Signer</Select.Item>
                    <Select.Item value="approver" label="Approver">Approver</Select.Item>
                    <Select.Item value="viewer" label="Viewer (CC)">Viewer (CC)</Select.Item>
                  </Select.Content>
                </Select.Root>
              </div>
            </div>

            <Button variant="ghost" size="sm" onclick={() => removeSigner(index)}>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-destructive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </Button>
          </div>
        </Card.Root>
      {/each}
    </div>
  {/if}

  <div class="text-sm text-muted-foreground">
    <p>Signing order is determined by the numbers above. Drag to reorder or use the arrows.</p>
  </div>
</div>
