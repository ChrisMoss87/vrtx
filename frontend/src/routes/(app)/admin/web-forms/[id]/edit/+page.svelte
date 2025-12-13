<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Tabs from '$lib/components/ui/tabs';
	import { ArrowLeft, Loader2, Eye, BarChart3, FileText } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { WebFormBuilder } from '$lib/components/web-forms';
	import {
		getWebForm,
		updateWebForm,
		getModulesForForms,
		getWebFormSubmissions,
		getWebFormAnalytics,
		type WebForm,
		type WebFormSubmission,
		type WebFormAnalytics,
		type ModuleForForm,
		type WebFormData,
		formatSubmissionStatus
	} from '$lib/api/web-forms';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';

	const formId = $derived(parseInt($page.params.id ?? '0'));

	let form = $state<WebForm | null>(null);
	let modules = $state<ModuleForForm[]>([]);
	let submissions = $state<WebFormSubmission[]>([]);
	let analytics = $state<WebFormAnalytics | null>(null);
	let loading = $state(true);
	let saving = $state(false);
	let activeTab = $state('builder');

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [formData, modulesData] = await Promise.all([
				getWebForm(formId),
				getModulesForForms()
			]);
			form = formData;
			modules = modulesData;

			// Load submissions and analytics in background
			loadSubmissions();
			loadAnalytics();
		} catch (error) {
			console.error('Failed to load form:', error);
			toast.error('Failed to load form');
			goto('/admin/web-forms');
		} finally {
			loading = false;
		}
	}

	async function loadSubmissions() {
		try {
			const result = await getWebFormSubmissions(formId, { per_page: 50 });
			submissions = result.data;
		} catch (error) {
			console.error('Failed to load submissions:', error);
		}
	}

	async function loadAnalytics() {
		try {
			analytics = await getWebFormAnalytics(formId);
		} catch (error) {
			console.error('Failed to load analytics:', error);
		}
	}

	async function handleSave(data: WebFormData) {
		saving = true;
		try {
			await updateWebForm(formId, data);
			toast.success('Form updated successfully');
			await loadData();
		} catch (error) {
			console.error('Failed to update form:', error);
			toast.error('Failed to update form');
		} finally {
			saving = false;
		}
	}

	function handleCancel() {
		goto('/admin/web-forms');
	}

	function formatDate(dateString: string): string {
		return new Date(dateString).toLocaleString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});
	}
</script>

<svelte:head>
	<title>{form?.name ?? 'Edit Form'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/admin/web-forms')}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<div class="flex items-center gap-2">
					<h1 class="text-2xl font-bold">{form?.name ?? 'Loading...'}</h1>
					{#if form}
						<Badge variant={form.is_active ? 'default' : 'secondary'}>
							{form.is_active ? 'Active' : 'Inactive'}
						</Badge>
					{/if}
				</div>
				{#if form}
					<p class="text-muted-foreground">/{form.slug}</p>
				{/if}
			</div>
		</div>
		{#if form}
			<Button variant="outline" onclick={() => window.open(form?.public_url, '_blank')}>
				<Eye class="mr-2 h-4 w-4" />
				Preview Form
			</Button>
		{/if}
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
		</div>
	{:else if form}
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List class="mb-6">
				<Tabs.Trigger value="builder">Form Builder</Tabs.Trigger>
				<Tabs.Trigger value="submissions">
					Submissions ({form.submission_count})
				</Tabs.Trigger>
				<Tabs.Trigger value="analytics">Analytics</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="builder">
				<div class="h-[calc(100vh-280px)]">
					<WebFormBuilder {form} {modules} onSave={handleSave} onCancel={handleCancel} {saving} />
				</div>
			</Tabs.Content>

			<Tabs.Content value="submissions">
				<Card.Root>
					<Card.Header>
						<Card.Title>Form Submissions</Card.Title>
						<Card.Description>
							View all submissions received through this form
						</Card.Description>
					</Card.Header>
					<Card.Content class="p-0">
						{#if submissions.length === 0}
							<div class="flex flex-col items-center justify-center py-12">
								<FileText class="mb-4 h-12 w-12 text-muted-foreground" />
								<h3 class="mb-2 text-lg font-medium">No submissions yet</h3>
								<p class="text-muted-foreground">
									Submissions will appear here when visitors fill out the form
								</p>
							</div>
						{:else}
							<Table.Root>
								<Table.Header>
									<Table.Row>
										<Table.Head>ID</Table.Head>
										<Table.Head>Status</Table.Head>
										<Table.Head>Data</Table.Head>
										<Table.Head>Submitted</Table.Head>
									</Table.Row>
								</Table.Header>
								<Table.Body>
									{#each submissions as submission}
										{@const status = formatSubmissionStatus(submission.status)}
										<Table.Row>
											<Table.Cell class="font-mono text-sm">
												#{submission.id}
											</Table.Cell>
											<Table.Cell>
												<Badge variant={status.variant}>{status.label}</Badge>
											</Table.Cell>
											<Table.Cell>
												<div class="max-w-md">
													{#each Object.entries(submission.submission_data).slice(0, 3) as [key, value]}
														<div class="text-sm">
															<span class="text-muted-foreground">{key}:</span>
															<span class="ml-1">{String(value).slice(0, 50)}</span>
														</div>
													{/each}
													{#if Object.keys(submission.submission_data).length > 3}
														<span class="text-xs text-muted-foreground">
															+{Object.keys(submission.submission_data).length - 3} more fields
														</span>
													{/if}
												</div>
											</Table.Cell>
											<Table.Cell class="text-sm text-muted-foreground">
												{formatDate(submission.submitted_at)}
											</Table.Cell>
										</Table.Row>
									{/each}
								</Table.Body>
							</Table.Root>
						{/if}
					</Card.Content>
				</Card.Root>
			</Tabs.Content>

			<Tabs.Content value="analytics">
				{#if analytics}
					<div class="grid gap-4 md:grid-cols-4">
						<Card.Root>
							<Card.Header class="pb-2">
								<Card.Title class="text-sm font-medium text-muted-foreground">
									Total Views
								</Card.Title>
							</Card.Header>
							<Card.Content>
								<div class="text-2xl font-bold">{analytics.total_views}</div>
							</Card.Content>
						</Card.Root>
						<Card.Root>
							<Card.Header class="pb-2">
								<Card.Title class="text-sm font-medium text-muted-foreground">
									Total Submissions
								</Card.Title>
							</Card.Header>
							<Card.Content>
								<div class="text-2xl font-bold">{analytics.total_submissions}</div>
							</Card.Content>
						</Card.Root>
						<Card.Root>
							<Card.Header class="pb-2">
								<Card.Title class="text-sm font-medium text-muted-foreground">
									Conversion Rate
								</Card.Title>
							</Card.Header>
							<Card.Content>
								<div class="text-2xl font-bold">{analytics.conversion_rate}%</div>
							</Card.Content>
						</Card.Root>
						<Card.Root>
							<Card.Header class="pb-2">
								<Card.Title class="text-sm font-medium text-muted-foreground">
									Spam Blocked
								</Card.Title>
							</Card.Header>
							<Card.Content>
								<div class="text-2xl font-bold">{analytics.spam_blocked}</div>
							</Card.Content>
						</Card.Root>
					</div>

					{#if analytics.daily.length > 0}
						<Card.Root class="mt-6">
							<Card.Header>
								<Card.Title>Daily Performance</Card.Title>
							</Card.Header>
							<Card.Content class="p-0">
								<Table.Root>
									<Table.Header>
										<Table.Row>
											<Table.Head>Date</Table.Head>
											<Table.Head>Views</Table.Head>
											<Table.Head>Submissions</Table.Head>
											<Table.Head>Successful</Table.Head>
											<Table.Head>Conversion</Table.Head>
										</Table.Row>
									</Table.Header>
									<Table.Body>
										{#each analytics.daily as day}
											<Table.Row>
												<Table.Cell>{day.date}</Table.Cell>
												<Table.Cell>{day.views}</Table.Cell>
												<Table.Cell>{day.submissions}</Table.Cell>
												<Table.Cell>{day.successful}</Table.Cell>
												<Table.Cell>{day.conversion_rate}%</Table.Cell>
											</Table.Row>
										{/each}
									</Table.Body>
								</Table.Root>
							</Card.Content>
						</Card.Root>
					{/if}
				{:else}
					<div class="flex items-center justify-center py-12">
						<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
					</div>
				{/if}
			</Tabs.Content>
		</Tabs.Root>
	{/if}
</div>
