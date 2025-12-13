<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { TemplateBuilder, DocumentPreview } from '$lib/components/document-templates';
  import { documentTemplatesApi, type DocumentTemplate, type MergeFieldVariable } from '$lib/api/document-templates';

  let variables: Record<string, MergeFieldVariable[]> = {};
  let loading = false;
  let previewOpen = false;
  let previewHtml = '';
  let previewLoading = false;

  onMount(async () => {
    await loadVariables();
  });

  async function loadVariables() {
    try {
      variables = await documentTemplatesApi.getMergeFields();
    } catch (error) {
      console.error('Failed to load merge fields:', error);
    }
  }

  async function handleSave(event: CustomEvent<Partial<DocumentTemplate>>) {
    loading = true;
    try {
      await documentTemplatesApi.create(event.detail);
      goto('/admin/document-templates');
    } catch (error) {
      console.error('Failed to create template:', error);
    } finally {
      loading = false;
    }
  }

  function handleCancel() {
    goto('/admin/document-templates');
  }

  function handlePreview() {
    previewOpen = true;
    // Preview would typically call an API to render the template
    previewHtml = '<p>Preview functionality coming soon...</p>';
  }
</script>

<svelte:head>
  <title>Create Template | VRTX</title>
</svelte:head>

<div class="container py-6 max-w-6xl">
  <TemplateBuilder
    {variables}
    {loading}
    on:save={handleSave}
    on:cancel={handleCancel}
    on:preview={handlePreview}
  />
</div>

<DocumentPreview
  bind:open={previewOpen}
  html={previewHtml}
  loading={previewLoading}
  on:close={() => previewOpen = false}
/>
