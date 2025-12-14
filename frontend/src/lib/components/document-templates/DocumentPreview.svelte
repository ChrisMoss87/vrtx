<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import * as Dialog from '$lib/components/ui/dialog';

  interface Props {
    open?: boolean;
    html?: string;
    title?: string;
    loading?: boolean;
    onClose?: () => void;
    onDownload?: () => void;
  }

  let {
    open = $bindable(false),
    html = '',
    title = 'Document Preview',
    loading = false,
    onClose,
    onDownload,
  }: Props = $props();
</script>

<Dialog.Root bind:open onOpenChange={(isOpen) => !isOpen && onClose?.()}>
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
      <Button variant="outline" onclick={() => onClose?.()}>Close</Button>
      <Button onclick={() => onDownload?.()} disabled={loading || !html}>
        Download PDF
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
