<script lang="ts">
	import { onMount } from 'svelte';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Progress } from '$lib/components/ui/progress';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import {
		Target,
		Plus,
		Trophy,
		TrendingUp,
		Calendar,
		Users,
		Building2,
		User,
		Pause,
		Play,
		MoreVertical,
		CheckCircle2,
		Clock,
		AlertCircle,
		Flag
	} from 'lucide-svelte';
	import {
		getGoals,
		getActiveGoals,
		getMyGoals,
		createGoal,
		pauseGoal,
		resumeGoal,
		deleteGoal,
		getMetricTypes,
		type Goal,
		type GoalType,
		type GoalStatus,
		type MetricType
	} from '$lib/api/quotas';

	let goals = $state<Goal[]>([]);
	let activeGoals = $state<{ individual: Goal[]; team: Goal[]; company: Goal[] }>({
		individual: [],
		team: [],
		company: []
	});
	let metricTypes = $state<Record<MetricType, string>>({} as Record<MetricType, string>);
	let loading = $state(true);
	let activeTab = $state('my-goals');
	let showCreateDialog = $state(false);

	// Create form state
	let newGoal = $state({
		name: '',
		description: '',
		goal_type: 'individual' as GoalType,
		metric_type: 'revenue' as MetricType,
		target_value: 0,
		currency: 'USD',
		start_date: new Date().toISOString().split('T')[0],
		end_date: '',
		milestones: [] as { name: string; target_value: number; target_date?: string }[]
	});

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [goalsRes, activeRes, typesRes] = await Promise.all([
				getMyGoals({ current: true }),
				getActiveGoals(),
				getMetricTypes()
			]);
			goals = goalsRes;
			activeGoals = activeRes;
			metricTypes = typesRes;
		} catch (error) {
			console.error('Failed to load goals:', error);
		} finally {
			loading = false;
		}
	}

	async function handleCreateGoal() {
		try {
			await createGoal(newGoal);
			showCreateDialog = false;
			resetForm();
			await loadData();
		} catch (error) {
			console.error('Failed to create goal:', error);
		}
	}

	async function handlePauseGoal(goal: Goal) {
		try {
			await pauseGoal(goal.id);
			await loadData();
		} catch (error) {
			console.error('Failed to pause goal:', error);
		}
	}

	async function handleResumeGoal(goal: Goal) {
		try {
			await resumeGoal(goal.id);
			await loadData();
		} catch (error) {
			console.error('Failed to resume goal:', error);
		}
	}

	async function handleDeleteGoal(goal: Goal) {
		if (!confirm('Are you sure you want to delete this goal?')) return;
		try {
			await deleteGoal(goal.id);
			await loadData();
		} catch (error) {
			console.error('Failed to delete goal:', error);
		}
	}

	function resetForm() {
		newGoal = {
			name: '',
			description: '',
			goal_type: 'individual',
			metric_type: 'revenue',
			target_value: 0,
			currency: 'USD',
			start_date: new Date().toISOString().split('T')[0],
			end_date: '',
			milestones: []
		};
	}

	function addMilestone() {
		newGoal.milestones = [
			...newGoal.milestones,
			{ name: '', target_value: 0, target_date: undefined }
		];
	}

	function removeMilestone(index: number) {
		newGoal.milestones = newGoal.milestones.filter((_, i) => i !== index);
	}

	function getStatusBadge(status: GoalStatus) {
		switch (status) {
			case 'achieved':
				return { variant: 'default' as const, class: 'bg-green-500', label: 'Achieved' };
			case 'in_progress':
				return { variant: 'default' as const, class: 'bg-blue-500', label: 'In Progress' };
			case 'missed':
				return { variant: 'destructive' as const, class: '', label: 'Missed' };
			case 'paused':
				return { variant: 'secondary' as const, class: '', label: 'Paused' };
			default:
				return { variant: 'outline' as const, class: '', label: status };
		}
	}

	function getGoalTypeIcon(type: GoalType) {
		switch (type) {
			case 'individual':
				return User;
			case 'team':
				return Users;
			case 'company':
				return Building2;
		}
	}

	function formatCurrency(value: number, currency: string = 'USD') {
		return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
	}

	function formatDate(dateString: string) {
		return new Date(dateString).toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric',
			year: 'numeric'
		});
	}
</script>

<div class="container mx-auto space-y-6 p-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-3xl font-bold tracking-tight">Goals</h1>
			<p class="text-muted-foreground">Track and achieve your sales objectives</p>
		</div>
		<Button onclick={() => (showCreateDialog = true)}>
			<Plus class="mr-2 h-4 w-4" />
			New Goal
		</Button>
	</div>

	<!-- Stats Overview -->
	<div class="grid gap-4 md:grid-cols-4">
		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center gap-3">
					<div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900">
						<Target class="h-5 w-5 text-blue-600 dark:text-blue-400" />
					</div>
					<div>
						<p class="text-muted-foreground text-sm">Active Goals</p>
						<p class="text-2xl font-bold">{goals.filter((g) => g.status === 'in_progress').length}</p>
					</div>
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center gap-3">
					<div class="rounded-lg bg-green-100 p-2 dark:bg-green-900">
						<Trophy class="h-5 w-5 text-green-600 dark:text-green-400" />
					</div>
					<div>
						<p class="text-muted-foreground text-sm">Achieved</p>
						<p class="text-2xl font-bold">{goals.filter((g) => g.status === 'achieved').length}</p>
					</div>
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center gap-3">
					<div class="rounded-lg bg-orange-100 p-2 dark:bg-orange-900">
						<Clock class="h-5 w-5 text-orange-600 dark:text-orange-400" />
					</div>
					<div>
						<p class="text-muted-foreground text-sm">Due Soon</p>
						<p class="text-2xl font-bold">
							{goals.filter((g) => g.days_remaining <= 7 && g.days_remaining > 0).length}
						</p>
					</div>
				</div>
			</Card.Content>
		</Card.Root>

		<Card.Root>
			<Card.Content class="pt-6">
				<div class="flex items-center gap-3">
					<div class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900">
						<TrendingUp class="h-5 w-5 text-purple-600 dark:text-purple-400" />
					</div>
					<div>
						<p class="text-muted-foreground text-sm">Avg. Attainment</p>
						<p class="text-2xl font-bold">
							{goals.length > 0
								? Math.round(goals.reduce((sum, g) => sum + g.attainment_percent, 0) / goals.length)
								: 0}%
						</p>
					</div>
				</div>
			</Card.Content>
		</Card.Root>
	</div>

	<!-- Tabs -->
	<Tabs.Root bind:value={activeTab}>
		<Tabs.List>
			<Tabs.Trigger value="my-goals">My Goals</Tabs.Trigger>
			<Tabs.Trigger value="team">Team Goals</Tabs.Trigger>
			<Tabs.Trigger value="company">Company Goals</Tabs.Trigger>
		</Tabs.List>

		<Tabs.Content value="my-goals" class="mt-4">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<div class="text-muted-foreground">Loading goals...</div>
				</div>
			{:else if goals.length === 0}
				<Card.Root>
					<Card.Content class="flex flex-col items-center justify-center py-12">
						<Target class="text-muted-foreground mb-4 h-12 w-12" />
						<h3 class="mb-2 text-lg font-semibold">No goals yet</h3>
						<p class="text-muted-foreground mb-4">Create your first goal to start tracking progress</p>
						<Button onclick={() => (showCreateDialog = true)}>
							<Plus class="mr-2 h-4 w-4" />
							Create Goal
						</Button>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
					{#each goals as goal}
						{@const statusBadge = getStatusBadge(goal.status)}
						{@const TypeIcon = getGoalTypeIcon(goal.goal_type)}
						<Card.Root class="relative">
							<Card.Header class="pb-2">
								<div class="flex items-start justify-between">
									<div class="flex items-center gap-2">
										<TypeIcon class="text-muted-foreground h-4 w-4" />
										<Badge variant={statusBadge.variant} class={statusBadge.class}>
											{statusBadge.label}
										</Badge>
									</div>
									<div class="flex items-center gap-1">
										{#if goal.status === 'in_progress'}
											<Button
												variant="ghost"
												size="icon"
												class="h-8 w-8"
												onclick={() => handlePauseGoal(goal)}
											>
												<Pause class="h-4 w-4" />
											</Button>
										{:else if goal.status === 'paused'}
											<Button
												variant="ghost"
												size="icon"
												class="h-8 w-8"
												onclick={() => handleResumeGoal(goal)}
											>
												<Play class="h-4 w-4" />
											</Button>
										{/if}
									</div>
								</div>
								<Card.Title class="text-lg">{goal.name}</Card.Title>
								{#if goal.description}
									<Card.Description class="line-clamp-2">{goal.description}</Card.Description>
								{/if}
							</Card.Header>
							<Card.Content class="space-y-4">
								<!-- Progress -->
								<div>
									<div class="mb-1 flex justify-between text-sm">
										<span class="text-muted-foreground">Progress</span>
										<span class="font-medium">{goal.attainment_percent.toFixed(1)}%</span>
									</div>
									<Progress value={Math.min(goal.attainment_percent, 100)} class="h-2" />
									<div class="text-muted-foreground mt-1 flex justify-between text-xs">
										<span>{formatCurrency(goal.current_value, goal.currency)}</span>
										<span>{formatCurrency(goal.target_value, goal.currency)}</span>
									</div>
								</div>

								<!-- Milestones -->
								{#if goal.milestones && goal.milestones.length > 0}
									<div class="space-y-2">
										<p class="text-muted-foreground text-xs font-medium uppercase">Milestones</p>
										{#each goal.milestones.slice(0, 3) as milestone}
											<div class="flex items-center gap-2 text-sm">
												{#if milestone.is_achieved}
													<CheckCircle2 class="h-4 w-4 text-green-500" />
												{:else}
													<Flag class="text-muted-foreground h-4 w-4" />
												{/if}
												<span class:line-through={milestone.is_achieved}>{milestone.name}</span>
											</div>
										{/each}
									</div>
								{/if}

								<!-- Timeline -->
								<div class="flex items-center justify-between border-t pt-3 text-sm">
									<div class="flex items-center gap-1 text-muted-foreground">
										<Calendar class="h-4 w-4" />
										<span>{formatDate(goal.end_date)}</span>
									</div>
									{#if goal.is_overdue}
										<Badge variant="destructive">Overdue</Badge>
									{:else if goal.days_remaining <= 7}
										<Badge variant="outline" class="border-orange-500 text-orange-500">
											{goal.days_remaining} days left
										</Badge>
									{:else}
										<span class="text-muted-foreground">{goal.days_remaining} days left</span>
									{/if}
								</div>
							</Card.Content>
						</Card.Root>
					{/each}
				</div>
			{/if}
		</Tabs.Content>

		<Tabs.Content value="team" class="mt-4">
			{#if activeGoals.team.length === 0}
				<Card.Root>
					<Card.Content class="flex flex-col items-center justify-center py-12">
						<Users class="text-muted-foreground mb-4 h-12 w-12" />
						<h3 class="mb-2 text-lg font-semibold">No team goals</h3>
						<p class="text-muted-foreground">Team goals will appear here when created</p>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
					{#each activeGoals.team as goal}
						{@const statusBadge = getStatusBadge(goal.status)}
						<Card.Root>
							<Card.Header class="pb-2">
								<div class="flex items-center gap-2">
									<Users class="text-muted-foreground h-4 w-4" />
									<Badge variant={statusBadge.variant} class={statusBadge.class}>
										{statusBadge.label}
									</Badge>
								</div>
								<Card.Title class="text-lg">{goal.name}</Card.Title>
							</Card.Header>
							<Card.Content>
								<div class="mb-1 flex justify-between text-sm">
									<span class="text-muted-foreground">Progress</span>
									<span class="font-medium">{goal.attainment_percent.toFixed(1)}%</span>
								</div>
								<Progress value={Math.min(goal.attainment_percent, 100)} class="h-2" />
								<div class="text-muted-foreground mt-1 flex justify-between text-xs">
									<span>{formatCurrency(goal.current_value, goal.currency)}</span>
									<span>{formatCurrency(goal.target_value, goal.currency)}</span>
								</div>
							</Card.Content>
						</Card.Root>
					{/each}
				</div>
			{/if}
		</Tabs.Content>

		<Tabs.Content value="company" class="mt-4">
			{#if activeGoals.company.length === 0}
				<Card.Root>
					<Card.Content class="flex flex-col items-center justify-center py-12">
						<Building2 class="text-muted-foreground mb-4 h-12 w-12" />
						<h3 class="mb-2 text-lg font-semibold">No company goals</h3>
						<p class="text-muted-foreground">Company-wide goals will appear here when created</p>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
					{#each activeGoals.company as goal}
						{@const statusBadge = getStatusBadge(goal.status)}
						<Card.Root>
							<Card.Header class="pb-2">
								<div class="flex items-center gap-2">
									<Building2 class="text-muted-foreground h-4 w-4" />
									<Badge variant={statusBadge.variant} class={statusBadge.class}>
										{statusBadge.label}
									</Badge>
								</div>
								<Card.Title class="text-lg">{goal.name}</Card.Title>
							</Card.Header>
							<Card.Content>
								<div class="mb-1 flex justify-between text-sm">
									<span class="text-muted-foreground">Progress</span>
									<span class="font-medium">{goal.attainment_percent.toFixed(1)}%</span>
								</div>
								<Progress value={Math.min(goal.attainment_percent, 100)} class="h-2" />
								<div class="text-muted-foreground mt-1 flex justify-between text-xs">
									<span>{formatCurrency(goal.current_value, goal.currency)}</span>
									<span>{formatCurrency(goal.target_value, goal.currency)}</span>
								</div>
							</Card.Content>
						</Card.Root>
					{/each}
				</div>
			{/if}
		</Tabs.Content>
	</Tabs.Root>
</div>

<!-- Create Goal Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Create New Goal</Dialog.Title>
			<Dialog.Description>Set a new goal to track your progress</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="name">Goal Name</Label>
				<Input id="name" bind:value={newGoal.name} placeholder="e.g., Q1 Revenue Target" />
			</div>

			<div class="space-y-2">
				<Label for="description">Description (optional)</Label>
				<Textarea
					id="description"
					bind:value={newGoal.description}
					placeholder="Describe your goal..."
					rows={2}
				/>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label>Goal Type</Label>
					<Select.Root
						type="single"
						value={newGoal.goal_type}
						onValueChange={(v) => v && (newGoal.goal_type = v as GoalType)}
					>
						<Select.Trigger>
							<span class="capitalize">{newGoal.goal_type}</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="individual">Individual</Select.Item>
							<Select.Item value="team">Team</Select.Item>
							<Select.Item value="company">Company</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-2">
					<Label>Metric Type</Label>
					<Select.Root
						type="single"
						value={newGoal.metric_type}
						onValueChange={(v) => v && (newGoal.metric_type = v as MetricType)}
					>
						<Select.Trigger>
							<span class="capitalize">{newGoal.metric_type}</span>
						</Select.Trigger>
						<Select.Content>
							{#each Object.entries(metricTypes) as [value, label]}
								<Select.Item {value}>{label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="target">Target Value</Label>
					<Input
						id="target"
						type="number"
						bind:value={newGoal.target_value}
						placeholder="100000"
					/>
				</div>

				<div class="space-y-2">
					<Label for="currency">Currency</Label>
					<Input id="currency" bind:value={newGoal.currency} placeholder="USD" />
				</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="start_date">Start Date</Label>
					<Input id="start_date" type="date" bind:value={newGoal.start_date} />
				</div>

				<div class="space-y-2">
					<Label for="end_date">End Date</Label>
					<Input id="end_date" type="date" bind:value={newGoal.end_date} />
				</div>
			</div>

			<!-- Milestones -->
			<div class="space-y-2">
				<div class="flex items-center justify-between">
					<Label>Milestones (optional)</Label>
					<Button variant="outline" size="sm" onclick={addMilestone}>
						<Plus class="mr-1 h-3 w-3" />
						Add
					</Button>
				</div>
				{#each newGoal.milestones as milestone, index}
					<div class="flex gap-2">
						<Input
							bind:value={milestone.name}
							placeholder="Milestone name"
							class="flex-1"
						/>
						<Input
							type="number"
							bind:value={milestone.target_value}
							placeholder="Value"
							class="w-24"
						/>
						<Button variant="ghost" size="icon" onclick={() => removeMilestone(index)}>
							×
						</Button>
					</div>
				{/each}
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showCreateDialog = false)}>Cancel</Button>
			<Button onclick={handleCreateGoal} disabled={!newGoal.name || !newGoal.end_date}>
				Create Goal
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
