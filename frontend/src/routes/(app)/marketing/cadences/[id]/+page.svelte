<script lang="ts">
	import { page } from '$app/stores';
	import type { Cadence, CadenceStep } from '$lib/api/cadences';
	import {
		getCadence,
		getCadenceStatuses,
		activateCadence,
		pauseCadence,
		archiveCadence,
		reorderSteps
	} from '$lib/api/cadences';
	import { CadenceStepEditor, EnrollmentManager, CadenceAnalytics } from '$lib/components/cadences';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Badge } from '$lib/components/ui/badge';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import { goto } from '$app/navigation';
	import {
		ArrowLeft,
		Edit,
		Play,
		Pause,
		Archive,
		Workflow,
		Users,
		BarChart3,
		Plus,
		Mail,
		Phone,
		MessageSquare,
		Linkedin,
		ClipboardList,
		Clock
	} from 'lucide-svelte';

	const cadenceId = $derived(parseInt($page.params.id ?? '0'));

	let loading = $state(true);
	let cadence = $state<Cadence | null>(null);
	let cadenceStatuses = $state<Record<string, string>>({});

	let activeTab = $state('steps');
	let showStepEditor = $state(false);
	let editingStep = $state<CadenceStep | undefined>(undefined);

	const statusColors: Record<string, string> = {
		draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
		active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
		paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
		archived: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
	};

	const channelIcons: Record<string, typeof Mail> = {
		email: Mail,
		call: Phone,
		sms: MessageSquare,
		linkedin: Linkedin,
		task: ClipboardList,
		wait: Clock
	};

	async function loadData() {
		loading = true;
		try {
			const [result, statusesData] = await Promise.all([
				getCadence(cadenceId),
				getCadenceStatuses()
			]);
			cadence = result.cadence;
			cadenceStatuses = statusesData;
		} catch (error) {
			console.error('Failed to load cadence:', error);
			toast.error('Failed to load cadence');
		} finally {
			loading = false;
		}
	}

	async function handleActivate() {
		try {
			cadence = await activateCadence(cadenceId);
			toast.success('Cadence activated');
		} catch (error) {
			console.error('Failed to activate:', error);
			toast.error('Failed to activate cadence');
		}
	}

	async function handlePause() {
		try {
			cadence = await pauseCadence(cadenceId);
			toast.success('Cadence paused');
		} catch (error) {
			console.error('Failed to pause:', error);
			toast.error('Failed to pause cadence');
		}
	}

	async function handleArchive() {
		try {
			cadence = await archiveCadence(cadenceId);
			toast.success('Cadence archived');
		} catch (error) {
			console.error('Failed to archive:', error);
			toast.error('Failed to archive cadence');
		}
	}

	function handleAddStep() {
		editingStep = undefined;
		showStepEditor = true;
	}

	function handleEditStep(step: CadenceStep) {
		editingStep = step;
		showStepEditor = true;
	}

	function handleStepSaved() {
		showStepEditor = false;
		editingStep = undefined;
		loadData();
	}

	function handleStepDeleted() {
		showStepEditor = false;
		editingStep = undefined;
		loadData();
	}

	function getDelayText(step: CadenceStep): string {
		if (step.delay_type === 'immediate') return 'Immediately';
		const unit = step.delay_type === 'business_days' ? 'business day' : step.delay_type.replace('s', '');
		return `After ${step.delay_value} ${unit}${step.delay_value !== 1 ? 's' : ''}`;
	}

	$effect(() => {
		loadData();
	});
</script>

<svelte:head>
	<title>{cadence?.name ?? 'Cadence'} | VRTX</title>
</svelte:head>

{#if loading}
	<div class="flex items-center justify-center py-12">
		<Spinner class="h-8 w-8" />
	</div>
{:else if !cadence}
	<div class="container mx-auto p-6">
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<Workflow class="h-12 w-12 text-muted-foreground" />
				<h3 class="mt-4 text-lg font-medium">Cadence not found</h3>
				<Button class="mt-4" href="/marketing/cadences">Back to Cadences</Button>
			</Card.Content>
		</Card.Root>
	</div>
{:else}
	<div class="container mx-auto space-y-6 p-6">
		<!-- Header -->
		<div class="flex items-start justify-between">
			<div class="flex items-start gap-4">
				<Button variant="ghost" size="icon" href="/marketing/cadences">
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div class="flex items-start gap-4">
					<div class="flex h-12 w-12 items-center justify-center rounded-lg bg-muted">
						<Workflow class="h-6 w-6 text-muted-foreground" />
					</div>
					<div>
						<div class="flex items-center gap-3">
							<h1 class="text-2xl font-bold tracking-tight">{cadence.name}</h1>
							<Badge class={statusColors[cadence.status]}>
								{cadenceStatuses[cadence.status]}
							</Badge>
						</div>
						{#if cadence.description}
							<p class="mt-1 text-muted-foreground">{cadence.description}</p>
						{/if}
						<div class="mt-2 flex items-center gap-4 text-sm text-muted-foreground">
							<span>{cadence.module?.name ?? 'No module'}</span>
							<span>{cadence.steps?.length ?? 0} steps</span>
							<span class="flex items-center gap-1">
								<Users class="h-3 w-3" />
								{cadence.active_enrollments_count ?? 0} active
							</span>
						</div>
					</div>
				</div>
			</div>

			<div class="flex items-center gap-2">
				{#if cadence.status === 'draft' || cadence.status === 'paused'}
					<Button onclick={handleActivate}>
						<Play class="mr-2 h-4 w-4" />
						Activate
					</Button>
				{/if}
				{#if cadence.status === 'active'}
					<Button variant="outline" onclick={handlePause}>
						<Pause class="mr-2 h-4 w-4" />
						Pause
					</Button>
				{/if}
				{#if cadence.status !== 'archived'}
					<Button variant="outline" onclick={handleArchive}>
						<Archive class="mr-2 h-4 w-4" />
						Archive
					</Button>
				{/if}
				<Button variant="outline" href={`/marketing/cadences/${cadence.id}/edit`}>
					<Edit class="mr-2 h-4 w-4" />
					Edit
				</Button>
			</div>
		</div>

		<!-- Tabs -->
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List>
				<Tabs.Trigger value="steps">
					<Workflow class="mr-2 h-4 w-4" />
					Steps ({cadence.steps?.length ?? 0})
				</Tabs.Trigger>
				<Tabs.Trigger value="enrollments">
					<Users class="mr-2 h-4 w-4" />
					Enrollments
				</Tabs.Trigger>
				<Tabs.Trigger value="analytics">
					<BarChart3 class="mr-2 h-4 w-4" />
					Analytics
				</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="steps" class="mt-6">
				{#if showStepEditor}
					<CadenceStepEditor
						cadenceId={cadence.id}
						step={editingStep}
						stepOrder={(cadence.steps?.length ?? 0) + 1}
						existingSteps={cadence.steps ?? []}
						onSave={handleStepSaved}
						onDelete={handleStepDeleted}
						onCancel={() => (showStepEditor = false)}
					/>
				{:else}
					<div class="space-y-4">
						<div class="flex justify-end">
							<Button onclick={handleAddStep}>
								<Plus class="mr-2 h-4 w-4" />
								Add Step
							</Button>
						</div>

						{#if !cadence.steps || cadence.steps.length === 0}
							<Card.Root>
								<Card.Content class="flex flex-col items-center justify-center py-12">
									<Workflow class="h-12 w-12 text-muted-foreground" />
									<h3 class="mt-4 text-lg font-medium">No steps</h3>
									<p class="mt-1 text-sm text-muted-foreground">
										Add steps to define your outreach sequence
									</p>
									<Button class="mt-4" onclick={handleAddStep}>
										<Plus class="mr-2 h-4 w-4" />
										Add First Step
									</Button>
								</Card.Content>
							</Card.Root>
						{:else}
							<div class="space-y-3">
								{#each cadence.steps as step, index}
									{@const Icon = channelIcons[step.channel] ?? Mail}
									<Card.Root
										class="cursor-pointer hover:border-primary/50 transition-colors {!step.is_active ? 'opacity-50' : ''}"
										onclick={() => handleEditStep(step)}
									>
										<Card.Content class="flex items-center gap-4 py-4">
											<div class="flex items-center gap-3">
												<Badge variant="outline" class="h-8 w-8 p-0 justify-center shrink-0">
													{index + 1}
												</Badge>
												{#if index > 0}
													<div class="text-xs text-muted-foreground w-24">
														{getDelayText(step)}
													</div>
												{/if}
											</div>

											<div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted shrink-0">
												<Icon class="h-5 w-5 text-muted-foreground" />
											</div>

											<div class="flex-1 min-w-0">
												<div class="flex items-center gap-2">
													<p class="font-medium">
														{step.name || `${step.channel.charAt(0).toUpperCase() + step.channel.slice(1)} Step`}
													</p>
													{#if !step.is_active}
														<Badge variant="secondary">Inactive</Badge>
													{/if}
												</div>
												{#if step.subject}
													<p class="text-sm text-muted-foreground truncate">
														{step.subject}
													</p>
												{:else if step.content}
													<p class="text-sm text-muted-foreground truncate">
														{step.content.substring(0, 100)}...
													</p>
												{/if}
											</div>

											<Badge variant="outline">{step.channel}</Badge>
										</Card.Content>
									</Card.Root>
								{/each}
							</div>
						{/if}
					</div>
				{/if}
			</Tabs.Content>

			<Tabs.Content value="enrollments" class="mt-6">
				<EnrollmentManager cadenceId={cadence.id} />
			</Tabs.Content>

			<Tabs.Content value="analytics" class="mt-6">
				<CadenceAnalytics {cadence} />
			</Tabs.Content>
		</Tabs.Root>
	</div>
{/if}
