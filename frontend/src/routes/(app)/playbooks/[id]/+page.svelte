<script lang="ts">
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import { PlaybookBuilder } from '$lib/components/playbooks';
  import type { Playbook } from '$lib/api/playbooks';

  let playbookId = $derived(Number($page.params.id));

  function handleSave(playbook: Playbook) {
    goto('/playbooks');
  }

  function handleCancel() {
    goto('/playbooks');
  }
</script>

<svelte:head>
  <title>Edit Playbook | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6">
  <PlaybookBuilder playbookId={playbookId} onSave={handleSave} onCancel={handleCancel} />
</div>
