<script lang="ts">
  import { goto } from '$app/navigation';
  import { SignatureRequestBuilder } from '$lib/components/e-signatures';
  import { signaturesApi, type SignatureRequest } from '$lib/api/signatures';

  let loading = false;
  let documentUrl: string | null = null;

  async function handleSave(event: CustomEvent<Partial<SignatureRequest>>) {
    loading = true;
    try {
      const created = await signaturesApi.create(event.detail);
      await signaturesApi.send(created.id);
      goto('/signatures');
    } catch (error) {
      console.error('Failed to create signature request:', error);
    } finally {
      loading = false;
    }
  }

  function handleCancel() {
    goto('/signatures');
  }

  function handleUploadDocument() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,.docx';
    input.onchange = async (e) => {
      const file = (e.target as HTMLInputElement).files?.[0];
      if (file) {
        // In a real implementation, this would upload the file
        // For now, create a local URL for preview
        documentUrl = URL.createObjectURL(file);
      }
    };
    input.click();
  }
</script>

<svelte:head>
  <title>Create Signature Request | VRTX</title>
</svelte:head>

<div class="container py-6 max-w-6xl">
  <SignatureRequestBuilder
    {loading}
    {documentUrl}
    on:save={handleSave}
    on:cancel={handleCancel}
    on:uploadDocument={handleUploadDocument}
  />
</div>
