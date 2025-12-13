<script lang="ts">
	import { onMount } from 'svelte';
	import type { SalesQuota, PeriodType } from '$lib/api/forecasts';
	import * as forecastApi from '$lib/api/forecasts';
	import { formatCurrency } from '$lib/api/forecasts';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import { Badge } from '$lib/components/ui/badge';
	import { toast } from 'svelte-sonner';
	import ArrowLeft from 'lucide-svelte/icons/arrow-left';
	import Plus from 'lucide-svelte/icons/plus';
	import Pencil from 'lucide-svelte/icons/pencil';
	import Trash2 from 'lucide-svelte/icons/trash-2';
	import Target from 'lucide-svelte/icons/target';

	// State
	let quotas = $state<SalesQuota[]>([]);
	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let dialogOpen = $state(false);
	let editingQuota = $state<SalesQuota | null>(null);
	let saving = $state(false);

	// Form state
	let formModuleApiName = $state<string | null>(null);
	let formPeriodType = $state<PeriodType>('month');
	let formPeriodStart = $state('');
	let formPeriodEnd = $state('');
	let formAmount = $state<number>(0);
	let formNotes = $state('');

	// Period options
	const periodOptions: { value: PeriodType; label: string }[] = [
		{ value: 'week', label: 'Weekly' },
		{ value: 'month', label: 'Monthly' },
		{ value: 'quarter', label: 'Quarterly' },
		{ value: 'year', label: 'Yearly' }
	];

	async function loadData() {
		loading = true;
		try {
			const [quotasData, modulesData] = await Promise.all([
				forecastApi.getQuotas(),
				modulesApi.getActive()
			]);
			quotas = quotasData;
			modules = modulesData;
		} catch (error) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load quotas');
		} finally {
			loading = false;
		}
	}

	function openCreateDialog() {
		editingQuota = null;
		formModuleApiName = modules[0]?.api_name || null;
		formPeriodType = 'month';
		formPeriodStart = getDefaultPeriodStart('month');
		formPeriodEnd = getDefaultPeriodEnd('month', formPeriodStart);
		formAmount = 0;
		formNotes = '';
		dialogOpen = true;
	}

	function openEditDialog(quota: SalesQuota) {
		editingQuota = quota;
		formModuleApiName = quota.module_api_name;
		formPeriodType = quota.period_type;
		formPeriodStart = quota.period_start;
		formPeriodEnd = quota.period_end;
		formAmount = quota.quota_amount;
		formNotes = quota.notes || '';
		dialogOpen = true;
	}

	function getDefaultPeriodStart(type: PeriodType): string {
		const now = new Date();
		switch (type) {
			case 'week': {
				const dayOfWeek = now.getDay();
				const diff = now.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
				const monday = new Date(now.setDate(diff));
				return monday.toISOString().split('T')[0];
			}
			case 'month':
				return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
			case 'quarter': {
				const quarterMonth = Math.floor(now.getMonth() / 3) * 3 + 1;
				return `${now.getFullYear()}-${String(quarterMonth).padStart(2, '0')}-01`;
			}
			case 'year':
				return `${now.getFullYear()}-01-01`;
		}
	}

	function getDefaultPeriodEnd(type: PeriodType, start: string): string {
		const startDate = new Date(start);
		switch (type) {
			case 'week': {
				const end = new Date(startDate);
				end.setDate(end.getDate() + 6);
				return end.toISOString().split('T')[0];
			}
			case 'month': {
				const end = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0);
				return end.toISOString().split('T')[0];
			}
			case 'quarter': {
				const end = new Date(startDate.getFullYear(), startDate.getMonth() + 3, 0);
				return end.toISOString().split('T')[0];
			}
			case 'year':
				return `${startDate.getFullYear()}-12-31`;
		}
	}

	function handlePeriodTypeChange(value: string) {
		formPeriodType = value as PeriodType;
		formPeriodStart = getDefaultPeriodStart(formPeriodType);
		formPeriodEnd = getDefaultPeriodEnd(formPeriodType, formPeriodStart);
	}

	async function handleSave() {
		if (!formModuleApiName || !formPeriodStart || !formPeriodEnd || formAmount <= 0) {
			toast.error('Please fill in all required fields');
			return;
		}

		saving = true;
		try {
			if (editingQuota) {
				const updated = await forecastApi.updateQuota(editingQuota.id, {
					quota_amount: formAmount,
					period_end: formPeriodEnd,
					notes: formNotes || undefined
				});
				quotas = quotas.map((q) => (q.id === editingQuota!.id ? updated : q));
				toast.success('Quota updated');
			} else {
				const created = await forecastApi.createQuota({
					module_api_name: formModuleApiName,
					period_type: formPeriodType,
					period_start: formPeriodStart,
					period_end: formPeriodEnd,
					quota_amount: formAmount,
					notes: formNotes || undefined
				});
				quotas = [...quotas, created];
				toast.success('Quota created');
			}
			dialogOpen = false;
		} catch (error) {
			console.error('Failed to save quota:', error);
			toast.error('Failed to save quota');
		} finally {
			saving = false;
		}
	}

	async function handleDelete(quota: SalesQuota) {
		if (!confirm('Are you sure you want to delete this quota?')) return;

		try {
			await forecastApi.deleteQuota(quota.id);
			quotas = quotas.filter((q) => q.id !== quota.id);
			toast.success('Quota deleted');
		} catch (error) {
			console.error('Failed to delete quota:', error);
			toast.error('Failed to delete quota');
		}
	}

	function formatPeriod(quota: SalesQuota): string {
		const start = new Date(quota.period_start);

		switch (quota.period_type) {
			case 'week':
				return `Week of ${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
			case 'month':
				return start.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
			case 'quarter': {
				const quarter = Math.floor(start.getMonth() / 3) + 1;
				return `Q${quarter} ${start.getFullYear()}`;
			}
			case 'year':
				return start.getFullYear().toString();
			default:
				return `${start.toLocaleDateString()} - ${new Date(quota.period_end).toLocaleDateString()}`;
		}
	}

	function isCurrentPeriod(quota: SalesQuota): boolean {
		const now = new Date();
		const start = new Date(quota.period_start);
		const end = new Date(quota.period_end);
		return now >= start && now <= end;
	}

	onMount(() => {
		loadData();
	});

	// Get module name
	function getModuleName(moduleApiName: string | null): string {
		if (!moduleApiName) return 'All Modules';
		return modules.find((m) => m.api_name === moduleApiName)?.name || moduleApiName;
	}
</script>

<svelte:head>
	<title>Manage Quotas | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" href="/forecasts">
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold tracking-tight">Sales Quotas</h1>
				<p class="text-muted-foreground">
					Set and manage revenue targets for your team
				</p>
			</div>
		</div>
		<Button onclick={openCreateDialog}>
			<Plus class="mr-2 h-4 w-4" />
			Add Quota
		</Button>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading quotas...</div>
		</div>
	{:else if quotas.length === 0}
		<Card.Root>
			<Card.Content class="py-12 text-center">
				<Target class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
				<h3 class="text-lg font-semibold">No Quotas Set</h3>
				<p class="text-muted-foreground mb-4">
					Create your first quota to start tracking sales targets
				</p>
				<Button onclick={openCreateDialog}>
					<Plus class="mr-2 h-4 w-4" />
					Create Quota
				</Button>
			</Card.Content>
		</Card.Root>
	{:else}
		<Card.Root>
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Module</Table.Head>
						<Table.Head>Period</Table.Head>
						<Table.Head class="text-right">Target</Table.Head>
						<Table.Head>Status</Table.Head>
						<Table.Head>Notes</Table.Head>
						<Table.Head class="w-[100px]">Actions</Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each quotas as quota (quota.id)}
						<Table.Row>
							<Table.Cell class="font-medium">
								{getModuleName(quota.module_api_name)}
							</Table.Cell>
							<Table.Cell>
								<div class="flex items-center gap-2">
									<span>{formatPeriod(quota)}</span>
									<Badge variant="outline" class="text-xs">
										{quota.period_type}
									</Badge>
								</div>
							</Table.Cell>
							<Table.Cell class="text-right font-mono">
								{formatCurrency(quota.quota_amount, quota.currency)}
							</Table.Cell>
							<Table.Cell>
								{#if isCurrentPeriod(quota)}
									<Badge variant="default">Current</Badge>
								{:else if new Date(quota.period_end) < new Date()}
									<Badge variant="secondary">Past</Badge>
								{:else}
									<Badge variant="outline">Upcoming</Badge>
								{/if}
							</Table.Cell>
							<Table.Cell class="max-w-[200px] truncate text-muted-foreground">
								{quota.notes || '-'}
							</Table.Cell>
							<Table.Cell>
								<div class="flex items-center gap-1">
									<Button
										variant="ghost"
										size="icon"
										class="h-8 w-8"
										onclick={() => openEditDialog(quota)}
									>
										<Pencil class="h-4 w-4" />
									</Button>
									<Button
										variant="ghost"
										size="icon"
										class="h-8 w-8 text-destructive hover:bg-destructive/10"
										onclick={() => handleDelete(quota)}
									>
										<Trash2 class="h-4 w-4" />
									</Button>
								</div>
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		</Card.Root>
	{/if}
</div>

<!-- Create/Edit Dialog -->
<Dialog.Root bind:open={dialogOpen}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>{editingQuota ? 'Edit Quota' : 'Create Quota'}</Dialog.Title>
			<Dialog.Description>
				{editingQuota ? 'Update the quota details' : 'Set a new revenue target'}
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			{#if !editingQuota}
				<div class="space-y-2">
					<Label for="module">Module</Label>
					<Select.Root
						type="single"
						value={formModuleApiName || ''}
						onValueChange={(v) => (formModuleApiName = v)}
					>
						<Select.Trigger id="module">
							{modules.find((m) => m.api_name === formModuleApiName)?.name || 'Select module'}
						</Select.Trigger>
						<Select.Content>
							{#each modules as mod}
								<Select.Item value={mod.api_name}>
									{mod.name}
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-2">
					<Label for="period-type">Period Type</Label>
					<Select.Root
						type="single"
						value={formPeriodType}
						onValueChange={handlePeriodTypeChange}
					>
						<Select.Trigger id="period-type">
							{periodOptions.find((p) => p.value === formPeriodType)?.label}
						</Select.Trigger>
						<Select.Content>
							{#each periodOptions as option}
								<Select.Item value={option.value}>
									{option.label}
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="period-start">Start Date</Label>
						<Input
							id="period-start"
							type="date"
							bind:value={formPeriodStart}
						/>
					</div>
					<div class="space-y-2">
						<Label for="period-end">End Date</Label>
						<Input
							id="period-end"
							type="date"
							bind:value={formPeriodEnd}
						/>
					</div>
				</div>
			{:else}
				<div class="rounded-lg bg-muted p-3 text-sm">
					<p><strong>Module:</strong> {getModuleName(editingQuota.module_api_name)}</p>
					<p><strong>Period:</strong> {formatPeriod(editingQuota)}</p>
				</div>
			{/if}

			<div class="space-y-2">
				<Label for="amount">Quota Amount</Label>
				<Input
					id="amount"
					type="number"
					min="0"
					step="1000"
					bind:value={formAmount}
					placeholder="100000"
				/>
			</div>

			<div class="space-y-2">
				<Label for="notes">Notes (optional)</Label>
				<Textarea
					id="notes"
					bind:value={formNotes}
					placeholder="Any additional notes..."
					rows={2}
				/>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (dialogOpen = false)}>
				Cancel
			</Button>
			<Button onclick={handleSave} disabled={saving}>
				{saving ? 'Saving...' : editingQuota ? 'Update' : 'Create'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
