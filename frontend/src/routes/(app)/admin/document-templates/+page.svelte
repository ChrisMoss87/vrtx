<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { TemplateList } from '$lib/components/document-templates';
  import { documentTemplatesApi, type DocumentTemplate } from '$lib/api/document-templates';

  let templates: DocumentTemplate[] = [];
  let loading = true;

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

  function handleEdit(event: CustomEvent<number>) {
    goto(`/admin/document-templates/${event.detail}/edit`);
  }

  async function handleDuplicate(event: CustomEvent<number>) {
    try {
      await documentTemplatesApi.duplicate(event.detail);
      await loadTemplates();
    } catch (error) {
      console.error('Failed to duplicate template:', error);
    }
  }

  async function handleDelete(event: CustomEvent<number>) {
    if (confirm('Are you sure you want to delete this template?')) {
      try {
        await documentTemplatesApi.delete(event.detail);
        await loadTemplates();
      } catch (error) {
        console.error('Failed to delete template:', error);
      }
    }
  }

  function handleGenerate(event: CustomEvent<{ templateId: number }>) {
    goto(`/admin/document-templates/${event.detail.templateId}/generate`);
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
    on:create={handleCreate}
    on:edit={handleEdit}
    on:duplicate={handleDuplicate}
    on:delete={handleDelete}
    on:generate={handleGenerate}
  />
</div>
