<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import * as Dialog from '$lib/components/ui/dialog';
	import {
		getDuplicateRules,
		getDuplicateStats,
		scanForDuplicates,
		deleteDuplicateRule,
		type DuplicateRule,
		type DuplicateStats
	} from '$lib/api/duplicates';
	import { getActiveModules, type Module } from '$lib/api/modules';
	import { DuplicateCandidatesList, DuplicateMergeWizard, DuplicateRuleBuilder } from '$lib/components/duplicates';
	import { toast } from 'svelte-sonner';
	import { Trash2, Plus, Search, Settings, AlertTriangle, CheckCircle, XCircle, GitMerge } from 'lucide-svelte';

	// State
	let activeTab = $state('candidates');
	let selectedModuleId = $state<number | null>(null);
	let modules = $state<Module[]>([]);
	let rules = $state<DuplicateRule[]>([]);
	let stats = $state<DuplicateStats | null>(null);
	let showRuleBuilder = $state(false);
	let editingRule = $state<DuplicateRule | null>(null);
	let showMergeWizard = $state(false);
	let mergeRecordAId = $state<number | null>(null);
	let mergeRecordBId = $state<number | null>(null);

	// Loading states
	let loadingModules = $state(true);
	let loadingRules = $state(true);
	let loadingStats = $state(true);
	let scanning = $state(false);

	async function loadModules() {
		loadingModules = true;
		try {
			const response = await getActiveModules();
			modules = response;
			if (modules.length > 0 && !selectedModuleId) {
				selectedModuleId = modules[0].id;
			}
		} catch (e) {
			toast.error('Failed to load modules');
		} finally {
			loadingModules = false;
		}
	}

	async function loadRules() {
		if (!selectedModuleId) return;
		loadingRules = true;
		try {
			rules = await getDuplicateRules(selectedModuleId);
		} catch (e) {
			toast.error('Failed to load rules');
		} finally {
			loadingRules = false;
		}
	}

	async function loadStats() {
		loadingStats = true;
		try {
			stats = await getDuplicateStats(selectedModuleId ?? undefined);
		} catch (e) {
			stats = null;
		} finally {
			loadingStats = false;
		}
	}

	async function handleScan() {
		if (!selectedModuleId) return;
		scanning = true;
		try {
			await scanForDuplicates(selectedModuleId);
			toast.success('Duplicate scan started');
			loadStats();
		} catch (e) {
			toast.error('Failed to start scan');
		} finally {
			scanning = false;
		}
	}

	async function handleDeleteRule(rule: DuplicateRule) {
		if (!confirm(`Delete rule "${rule.name}"?`)) return;
		try {
			await deleteDuplicateRule(rule.id);
			toast.success('Rule deleted');
			loadRules();
		} catch (e) {
			toast.error('Failed to delete rule');
		}
	}

	function handleMerge(recordAId: number, recordBId: number) {
		mergeRecordAId = recordAId;
		mergeRecordBId = recordBId;
		showMergeWizard = true;
	}

	function handleMergeComplete() {
		showMergeWizard = false;
		mergeRecordAId = null;
		mergeRecordBId = null;
		loadStats();
	}

	function handleRuleSaved() {
		showRuleBuilder = false;
		editingRule = null;
		loadRules();
	}

	$effect(() => {
		loadModules();
	});

	$effect(() => {
		if (selectedModuleId) {
			loadRules();
			loadStats();
		}
	});

	const selectedModule = $derived(modules.find((m) => m.id === selectedModuleId));
</script>

<svelte:head>
	<title>Duplicate Detection | Admin | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Duplicate Detection</h1>
			<p class="text-muted-foreground">Find and merge duplicate records</p>
		</div>
		<div class="flex items-center gap-2">
			<Select.Root type="single" value={selectedModuleId?.toString()} onValueChange={(v) => v && (selectedModuleId = parseInt(v))}>
				<Select.Trigger class="w-48">
					{selectedModule?.name ?? 'Select module'}
				</Select.Trigger>
				<Select.Content>
					{#each modules as module}
						<Select.Item value={module.id.toString()}>{module.name}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			<Button variant="outline" onclick={handleScan} disabled={scanning || !selectedModuleId}>
				<Search class="mr-2 h-4 w-4" />
				{scanning ? 'Scanning...' : 'Scan for Duplicates'}
			</Button>
		</div>
	</div>

	{#if stats}
		<div class="grid grid-cols-2 md:grid-cols-5 gap-4">
			<Card>
				<CardContent class="pt-4">
					<div class="flex items-center gap-2">
						<AlertTriangle class="h-5 w-5 text-yellow-500" />
						<div>
							<div class="text-2xl font-bold">{stats.candidates.pending}</div>
							<div class="text-sm text-muted-foreground">Pending</div>
						</div>
					</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="flex items-center gap-2">
						<GitMerge class="h-5 w-5 text-green-500" />
						<div>
							<div class="text-2xl font-bold">{stats.candidates.merged}</div>
							<div class="text-sm text-muted-foreground">Merged</div>
						</div>
					</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="flex items-center gap-2">
						<XCircle class="h-5 w-5 text-gray-500" />
						<div>
							<div class="text-2xl font-bold">{stats.candidates.dismissed}</div>
							<div class="text-sm text-muted-foreground">Dismissed</div>
						</div>
					</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="flex items-center gap-2">
						<CheckCircle class="h-5 w-5 text-blue-500" />
						<div>
							<div class="text-2xl font-bold">{stats.rules.active}</div>
							<div class="text-sm text-muted-foreground">Active Rules</div>
						</div>
					</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="flex items-center gap-2">
						<Settings class="h-5 w-5 text-purple-500" />
						<div>
							<div class="text-2xl font-bold">{stats.rules.total}</div>
							<div class="text-sm text-muted-foreground">Total Rules</div>
						</div>
					</div>
				</CardContent>
			</Card>
		</div>
	{/if}

	<Tabs.Root bind:value={activeTab}>
		<Tabs.List>
			<Tabs.Trigger value="candidates">
				Candidates
				{#if stats && stats.candidates.pending > 0}
					<Badge variant="destructive" class="ml-2">{stats.candidates.pending}</Badge>
				{/if}
			</Tabs.Trigger>
			<Tabs.Trigger value="rules">Rules</Tabs.Trigger>
		</Tabs.List>

		<Tabs.Content value="candidates" class="mt-4">
			{#if selectedModuleId}
				<DuplicateCandidatesList
					moduleId={selectedModuleId}
					onMerge={handleMerge}
					onViewRecord={(id) => window.open(`/records/${selectedModule?.api_name}/${id}`, '_blank')}
				/>
			{:else}
				<Card>
					<CardContent class="py-12 text-center text-muted-foreground">
						Select a module to view duplicate candidates
					</CardContent>
				</Card>
			{/if}
		</Tabs.Content>

		<Tabs.Content value="rules" class="mt-4 space-y-4">
			<div class="flex justify-end">
				<Button onclick={() => { editingRule = null; showRuleBuilder = true; }}>
					<Plus class="mr-2 h-4 w-4" />
					New Rule
				</Button>
			</div>

			{#if loadingRules}
				<div class="space-y-4">
					{#each Array(3) as _}
						<Card>
							<CardContent class="p-4">
								<Skeleton class="h-6 w-1/3 mb-2" />
								<Skeleton class="h-4 w-2/3" />
							</CardContent>
						</Card>
					{/each}
				</div>
			{:else if rules.length === 0}
				<Card>
					<CardContent class="py-12 text-center">
						<Settings class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
						<h3 class="text-lg font-medium mb-2">No duplicate detection rules</h3>
						<p class="text-muted-foreground mb-4">Create rules to automatically detect duplicate records</p>
						<Button onclick={() => { editingRule = null; showRuleBuilder = true; }}>
							Create First Rule
						</Button>
					</CardContent>
				</Card>
			{:else}
				<div class="space-y-3">
					{#each rules as rule}
						<Card>
							<CardContent class="p-4">
								<div class="flex items-start justify-between">
									<div class="flex-1">
										<div class="flex items-center gap-2 mb-1">
											<h3 class="font-medium">{rule.name}</h3>
											<Badge variant={rule.is_active ? 'default' : 'secondary'}>
												{rule.is_active ? 'Active' : 'Inactive'}
											</Badge>
											<Badge variant={rule.action === 'block' ? 'destructive' : 'outline'}>
												{rule.action === 'block' ? 'Blocks' : 'Warns'}
											</Badge>
										</div>
										{#if rule.description}
											<p class="text-sm text-muted-foreground mb-2">{rule.description}</p>
										{/if}
										<div class="flex flex-wrap gap-1">
											{#each rule.conditions.rules as condition}
												{#if 'field' in condition}
													<Badge variant="outline" class="text-xs">
														{condition.field}: {condition.match_type}
														{#if condition.threshold}
															({condition.threshold}%)
														{/if}
													</Badge>
												{/if}
											{/each}
										</div>
									</div>
									<div class="flex items-center gap-1">
										<Button
											variant="ghost"
											size="sm"
											onclick={() => { editingRule = rule; showRuleBuilder = true; }}
										>
											Edit
										</Button>
										<Button
											variant="ghost"
											size="icon"
											class="text-destructive"
											onclick={() => handleDeleteRule(rule)}
										>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								</div>
							</CardContent>
						</Card>
					{/each}
				</div>
			{/if}
		</Tabs.Content>
	</Tabs.Root>
</div>

<!-- Rule Builder Dialog -->
<Dialog.Root bind:open={showRuleBuilder}>
	<Dialog.Content class="max-w-2xl max-h-[90vh] overflow-y-auto">
		{#if selectedModuleId && selectedModule}
			<DuplicateRuleBuilder
				moduleId={selectedModuleId}
				fields={[
					{ name: 'name', label: 'Name' },
					{ name: 'email', label: 'Email' },
					{ name: 'phone', label: 'Phone' },
					{ name: 'company_name', label: 'Company' }
				]}
				rule={editingRule}
				onSaved={handleRuleSaved}
				onClose={() => { showRuleBuilder = false; editingRule = null; }}
			/>
		{/if}
	</Dialog.Content>
</Dialog.Root>

<!-- Merge Wizard Dialog -->
<Dialog.Root bind:open={showMergeWizard}>
	<Dialog.Content class="max-w-3xl max-h-[90vh] overflow-y-auto">
		{#if mergeRecordAId && mergeRecordBId}
			<DuplicateMergeWizard
				recordAId={mergeRecordAId}
				recordBId={mergeRecordBId}
				onMerged={handleMergeComplete}
				onClose={() => { showMergeWizard = false; }}
			/>
		{/if}
	</Dialog.Content>
</Dialog.Root>
