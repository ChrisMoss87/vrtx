<script lang="ts">
	import { goto } from '$app/navigation';
	import { ApprovalRuleList } from '$lib/components/approvals';
	import { approvalRulesApi, type ApprovalRule, type CreateApprovalRuleData } from '$lib/api/approvals';
	import { toast } from 'svelte-sonner';

	let rules = $state<ApprovalRule[]>([]);
	let loading = $state(true);

	async function loadRules() {
		loading = true;
		try {
			rules = await approvalRulesApi.list();
		} catch (error) {
			toast.error('Failed to load approval rules');
		} finally {
			loading = false;
		}
	}

	function handleCreate() {
		goto('/admin/approval-rules/create');
	}

	function handleEdit(id: number) {
		goto(`/admin/approval-rules/${id}/edit`);
	}

	async function handleDuplicate(id: number) {
		try {
			const rule = rules.find((r) => r.id === id);
			if (rule) {
				const createData: CreateApprovalRuleData = {
					name: `${rule.name} (Copy)`,
					entity_type: rule.entity_type,
					approver_chain: rule.approver_chain,
					description: rule.description ?? undefined,
					module_id: rule.module_id ?? undefined,
					conditions: rule.conditions ?? undefined,
					approval_type: rule.approval_type,
					allow_self_approval: rule.allow_self_approval,
					require_comments: rule.require_comments,
					sla_hours: rule.sla_hours ?? undefined,
					escalation_rules: rule.escalation_rules ?? undefined,
					notification_settings: rule.notification_settings ?? undefined,
					priority: rule.priority
				};
				const duplicated = await approvalRulesApi.create(createData);
				goto(`/admin/approval-rules/${duplicated.id}/edit`);
			}
		} catch (error) {
			toast.error('Failed to duplicate rule');
		}
	}

	async function handleDelete(id: number) {
		if (confirm('Are you sure you want to delete this approval rule?')) {
			try {
				await approvalRulesApi.delete(id);
				toast.success('Rule deleted');
				await loadRules();
			} catch (error) {
				toast.error('Failed to delete rule');
			}
		}
	}

	async function handleToggle(id: number) {
		try {
			const rule = rules.find((r) => r.id === id);
			if (rule) {
				await approvalRulesApi.update(id, { is_active: !rule.is_active });
				toast.success(rule.is_active ? 'Rule deactivated' : 'Rule activated');
				await loadRules();
			}
		} catch (error) {
			toast.error('Failed to toggle rule');
		}
	}

	$effect(() => {
		loadRules();
	});
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
		onCreate={handleCreate}
		onEdit={handleEdit}
		onDuplicate={handleDuplicate}
		onDelete={handleDelete}
		onToggle={handleToggle}
	/>
</div>
