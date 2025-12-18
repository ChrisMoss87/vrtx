<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { TemplateList } from '$lib/components/document-templates';
  import { documentTemplatesApi, type DocumentTemplate } from '$lib/api/document-templates';

  let templates = $state<DocumentTemplate[]>([]);
  let loading = $state(true);

  onMount(async () => {
    await loadTemplates();
  });

  async function loadTemplates() {
    loading = true;
    try {
      const response = await documentTemplatesApi.list();
      templates = response.data;
    } catch (error) {
      console.error('Failed to load templates:', error);
    } finally {
      loading = false;
    }
  }

  function handleCreate() {
    goto('/admin/document-templates/create');
  }

  function handleEdit(id: number) {
    goto(`/admin/document-templates/${id}/edit`);
  }

  async function handleDuplicate(id: number) {
    try {
      await documentTemplatesApi.duplicate(id);
      await loadTemplates();
    } catch (error) {
      console.error('Failed to duplicate template:', error);
    }
  }

  async function handleDelete(id: number) {
    if (confirm('Are you sure you want to delete this template?')) {
      try {
        await documentTemplatesApi.delete(id);
        await loadTemplates();
      } catch (error) {
        console.error('Failed to delete template:', error);
      }
    }
  }

  function handleGenerate(templateId: number) {
    goto(`/admin/document-templates/${templateId}/generate`);
  }
</script>

<svelte:head>
  <title>Document Templates | VRTX</title>
</svelte:head>

<div class="container py-6">
  <div class="mb-6">
    <h1 class="text-2xl font-bold">Document Templates</h1>
    <p class="text-muted-foreground">Create and manage document templates with merge fields</p>
  </div>

  <TemplateList
    {templates}
    {loading}
    onCreate={handleCreate}
    onEdit={handleEdit}
    onDuplicate={handleDuplicate}
    onDelete={handleDelete}
    onGenerate={handleGenerate}
  />
</div>
