<script lang="ts">
  import { goto } from '$app/navigation';
  import { PlaybookBuilder } from '$lib/components/playbooks';
  import type { Playbook } from '$lib/api/playbooks';

  function handleSave(playbook: Playbook) {
    goto(`/playbooks/${playbook.id}`);
  }

  function handleCancel() {
    goto('/playbooks');
  }
</script>

<svelte:head>
  <title>Create Playbook | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6">
  <PlaybookBuilder onSave={handleSave} onCancel={handleCancel} />
</div>
