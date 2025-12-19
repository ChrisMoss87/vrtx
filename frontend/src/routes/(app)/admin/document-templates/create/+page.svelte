<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { TemplateBuilder, DocumentPreview } from '$lib/components/document-templates';
  import { documentTemplatesApi, type DocumentTemplate, type MergeFieldVariable, type CreateTemplateData } from '$lib/api/document-templates';

  let variables = $state<Record<string, MergeFieldVariable[]>>({});
  let loading = $state(false);
  let previewOpen = $state(false);
  let previewHtml = $state('');
  let previewLoading = $state(false);

  onMount(async () => {
    await loadVariables();
  });

  async function loadVariables() {
    try {
      variables = await documentTemplatesApi.getVariables();
    } catch (error) {
      console.error('Failed to load merge fields:', error);
    }
  }

  async function handleSave(data: Partial<DocumentTemplate>) {
    loading = true;
    try {
      const createData: CreateTemplateData = {
        name: data.name || 'Untitled Template',
        content: data.content || '',
        category: data.category ?? undefined,
        description: data.description ?? undefined,
        output_format: data.output_format,
        page_settings: data.page_settings ?? undefined,
        header_settings: data.header_settings ?? undefined,
        footer_settings: data.footer_settings ?? undefined,
        conditional_blocks: data.conditional_blocks ?? undefined,
        is_shared: data.is_shared,
      };
      await documentTemplatesApi.create(createData);
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
    onSave={handleSave}
    onCancel={handleCancel}
    onPreview={handlePreview}
  />
</div>

<DocumentPreview
  bind:open={previewOpen}
  html={previewHtml}
  loading={previewLoading}
  onClose={() => previewOpen = false}
/>
