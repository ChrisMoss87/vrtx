<script lang="ts">
	import { goto } from '$app/navigation';
	import { ApprovalRuleBuilder } from '$lib/components/approvals';
	import { approvalRulesApi, type CreateApprovalRuleData } from '$lib/api/approvals';
	import { toast } from 'svelte-sonner';

	let users = $state<Array<{ id: number; name: string }>>([]);
	let roles = $state<Array<{ id: number; name: string }>>([]);
	let loading = $state(false);

	async function handleSave(data: Record<string, unknown>) {
		loading = true;
		try {
			await approvalRulesApi.create(data as unknown as CreateApprovalRuleData);
			toast.success('Approval rule created');
			goto('/admin/approval-rules');
		} catch (error) {
			toast.error('Failed to create approval rule');
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
		onSave={handleSave}
		onCancel={handleCancel}
	/>
</div>
