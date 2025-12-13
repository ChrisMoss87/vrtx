<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { ApprovalRuleBuilder } from '$lib/components/approvals';
  import { approvalsApi, type ApprovalRule } from '$lib/api/approvals';

  let users: Array<{ id: number; name: string }> = [];
  let roles: Array<{ id: number; name: string }> = [];
  let loading = false;

  onMount(async () => {
    // Load users and roles for the approval step builder
    // This would typically come from a users API
    try {
      // Placeholder - in real implementation, fetch from API
      users = [];
      roles = [];
    } catch (error) {
      console.error('Failed to load users/roles:', error);
    }
  });

  async function handleSave(event: CustomEvent<Partial<ApprovalRule>>) {
    loading = true;
    try {
      await approvalsApi.createRule(event.detail);
      goto('/admin/approval-rules');
    } catch (error) {
      console.error('Failed to create approval rule:', error);
    } finally {
      loading = false;
    }
  }

  function handleCancel() {
    goto('/admin/approval-rules');
  }
</script>

<svelte:head>
  <title>Create Approval Rule | VRTX</title>
</svelte:head>

<div class="container py-6 max-w-4xl">
  <ApprovalRuleBuilder
    {users}
    {roles}
    {loading}
    on:save={handleSave}
    on:cancel={handleCancel}
  />
</div>
