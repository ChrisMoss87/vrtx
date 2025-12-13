<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { ApprovalRuleList } from '$lib/components/approvals';
  import { approvalsApi, type ApprovalRule } from '$lib/api/approvals';

  let rules: ApprovalRule[] = [];
  let loading = true;

  onMount(async () => {
    await loadRules();
  });

  async function loadRules() {
    loading = true;
    try {
      const response = await approvalsApi.listRules();
      rules = response.data;
    } catch (error) {
      console.error('Failed to load approval rules:', error);
    } finally {
      loading = false;
    }
  }

  function handleCreate() {
    goto('/admin/approval-rules/create');
  }

  function handleEdit(event: CustomEvent<number>) {
    goto(`/admin/approval-rules/${event.detail}/edit`);
  }

  async function handleDuplicate(event: CustomEvent<number>) {
    try {
      const rule = rules.find(r => r.id === event.detail);
      if (rule) {
        const duplicated = await approvalsApi.createRule({
          ...rule,
          name: `${rule.name} (Copy)`,
        });
        goto(`/admin/approval-rules/${duplicated.id}/edit`);
      }
    } catch (error) {
      console.error('Failed to duplicate rule:', error);
    }
  }

  async function handleDelete(event: CustomEvent<number>) {
    if (confirm('Are you sure you want to delete this approval rule?')) {
      try {
        await approvalsApi.deleteRule(event.detail);
        await loadRules();
      } catch (error) {
        console.error('Failed to delete rule:', error);
      }
    }
  }

  async function handleToggle(event: CustomEvent<number>) {
    try {
      const rule = rules.find(r => r.id === event.detail);
      if (rule) {
        await approvalsApi.updateRule(event.detail, { is_active: !rule.is_active });
        await loadRules();
      }
    } catch (error) {
      console.error('Failed to toggle rule:', error);
    }
  }
</script>

<svelte:head>
  <title>Approval Rules | VRTX</title>
</svelte:head>

<div class="container py-6">
  <div class="mb-6">
    <h1 class="text-2xl font-bold">Approval Rules</h1>
    <p class="text-muted-foreground">Configure approval workflows for quotes, discounts, and contracts</p>
  </div>

  <ApprovalRuleList
    {rules}
    {loading}
    on:create={handleCreate}
    on:edit={handleEdit}
    on:duplicate={handleDuplicate}
    on:delete={handleDelete}
    on:toggle={handleToggle}
  />
</div>
