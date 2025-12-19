<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { ApprovalRuleBuilder } from '$lib/components/approvals';
	import { approvalRulesApi, type ApprovalRule } from '$lib/api/approvals';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { toast } from 'svelte-sonner';

	const ruleId = $derived(parseInt($page.params.id ?? '0'));

	let rule = $state<ApprovalRule | null>(null);
	let users = $state<Array<{ id: number; name: string }>>([]);
	let roles = $state<Array<{ id: number; name: string }>>([]);
	let loadingRule = $state(true);
	let saving = $state(false);

	async function loadRule() {
		loadingRule = true;
		try {
			rule = await approvalRulesApi.get(ruleId);
		} catch (error) {
			toast.error('Failed to load approval rule');
			goto('/admin/approval-rules');
		} finally {
			loadingRule = false;
		}
	}

	async function handleSave(data: Record<string, unknown>) {
		saving = true;
		try {
			await approvalRulesApi.update(ruleId, data);
			toast.success('Approval rule updated');
			goto('/admin/approval-rules');
		} catch (error) {
			toast.error('Failed to update approval rule');
		} finally {
			saving = false;
		}
	}

	function handleCancel() {
		goto('/admin/approval-rules');
	}

	$effect(() => {
		loadRule();
	});
</script>

<svelte:head>
	<title>Edit Approval Rule | VRTX</title>
</svelte:head>

<div class="container py-6 max-w-4xl">
	{#if loadingRule}
		<div class="space-y-4">
			<Skeleton class="h-8 w-64" />
			<Skeleton class="h-64 w-full" />
		</div>
	{:else if rule}
		<ApprovalRuleBuilder
			{rule}
			{users}
			{roles}
			loading={saving}
			onSave={handleSave}
			onCancel={handleCancel}
		/>
	{/if}
</div>
