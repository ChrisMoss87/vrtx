<script lang="ts">
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import { PlaybookInstanceView } from '$lib/components/playbooks';

  let instanceId = $derived(Number($page.params.id));

  function handleBack() {
    goto('/playbooks/instances');
  }
</script>

<svelte:head>
  <title>Playbook Instance | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6">
  <PlaybookInstanceView instanceId={instanceId} onBack={handleBack} />
</div>
