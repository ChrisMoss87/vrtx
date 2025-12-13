<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Dialog from '$lib/components/ui/dialog';

  export let open = false;
  export let html = '';
  export let title = 'Document Preview';
  export let loading = false;

  const dispatch = createEventDispatcher<{
    close: void;
    download: void;
  }>();
</script>

<Dialog.Root bind:open on:close={() => dispatch('close')}>
  <Dialog.Content class="max-w-4xl max-h-[90vh]">
    <Dialog.Header>
      <Dialog.Title>{title}</Dialog.Title>
      <Dialog.Description>
        Preview how your document will look when generated
      </Dialog.Description>
    </Dialog.Header>

    <div class="mt-4 border rounded-lg bg-white overflow-hidden">
      {#if loading}
        <div class="flex items-center justify-center h-96">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>
      {:else if html}
        <div class="max-h-[60vh] overflow-auto p-4">
          {@html html}
        </div>
      {:else}
        <div class="flex items-center justify-center h-96 text-muted-foreground">
          No preview available
        </div>
      {/if}
    </div>

    <Dialog.Footer class="mt-4">
      <Button variant="outline" on:click={() => dispatch('close')}>Close</Button>
      <Button on:click={() => dispatch('download')} disabled={loading || !html}>
        Download PDF
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
