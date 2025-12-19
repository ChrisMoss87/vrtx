<script lang="ts">
  import { goto } from '$app/navigation';
  import { SignatureRequestBuilder } from '$lib/components/e-signatures';
  import { signaturesApi } from '$lib/api/signatures';

  // Type that matches SignatureRequestBuilder's internal CreateSignatureRequestData
  interface BuilderSignatureData {
    title: string;
    message?: string | null;
    expires_at?: string | null;
    signers: { name: string; email: string; role?: string; order?: number }[];
    fields: { signer_index: number; type: string; page: number; x: number; y: number; width: number; height: number; required: boolean; label?: string }[];
    settings?: { reminder_days?: number; allow_decline?: boolean; require_reason?: boolean };
  }

  let loading = $state(false);
  let documentUrl = $state<string | null>(null);

  function handleSave(data: BuilderSignatureData) {
    loading = true;
    // Convert builder format to API format
    signaturesApi.create({
      title: data.title,
      description: data.message ?? undefined,
      expires_at: data.expires_at ?? undefined,
      settings: data.settings,
      signers: data.signers.map(s => ({
        name: s.name,
        email: s.email,
        role: s.role,
        sign_order: s.order,
      })),
      fields: data.fields.map(f => ({
        field_type: f.type,
        signer_order: f.signer_index,
        page_number: f.page,
        x_position: f.x,
        y_position: f.y,
        width: f.width,
        height: f.height,
        required: f.required,
        label: f.label,
      })),
    })
      .then(created => signaturesApi.send(created.id))
      .then(() => goto('/signatures'))
      .catch(error => console.error('Failed to create signature request:', error))
      .finally(() => loading = false);
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
    onSave={handleSave}
    onCancel={handleCancel}
    onUploadDocument={handleUploadDocument}
  />
</div>
