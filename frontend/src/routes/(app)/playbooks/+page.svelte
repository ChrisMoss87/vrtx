<script lang="ts">
  import { goto } from '$app/navigation';
  import { PlaybookList, MyPlaybookTasks } from '$lib/components/playbooks';
  import type { Playbook } from '$lib/api/playbooks';

  function handleSelect(playbook: Playbook) {
    goto(`/playbooks/${playbook.id}`);
  }

  function handleCreate() {
    goto('/playbooks/create');
  }
</script>

<svelte:head>
  <title>Playbooks | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
  <div>
    <h1 class="text-3xl font-bold">Playbooks</h1>
    <p class="text-muted-foreground">
      Manage onboarding and process automation playbooks
    </p>
  </div>

  <div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
      <PlaybookList onSelect={handleSelect} onCreate={handleCreate} />
    </div>
    <div>
      <MyPlaybookTasks />
    </div>
  </div>
</div>
