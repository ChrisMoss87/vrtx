<script lang="ts">
	import { goto } from '$app/navigation';
	import { toast } from 'svelte-sonner';
	import ReportBuilder from '$lib/components/reporting/ReportBuilder.svelte';
	import { reportsApi, type Report } from '$lib/api/reports';

	async function handleSave(report: Report) {
		toast.success('Report created successfully');
		goto(`/reports/${report.id}`);
	}

	function handleCancel() {
		goto('/reports');
	}
</script>

<svelte:head>
	<title>Create Report | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<div class="mb-6">
		<h1 class="text-2xl font-bold">Create Report</h1>
		<p class="text-muted-foreground">Build a new report to analyze your data</p>
	</div>

	<ReportBuilder onSave={handleSave} onCancel={handleCancel} />
</div>
