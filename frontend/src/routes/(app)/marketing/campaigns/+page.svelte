<script lang="ts">
	import type { Campaign, CampaignStatus, CampaignType } from '$lib/api/campaigns';
	import {
		getCampaigns,
		getCampaignTypes,
		getCampaignStatuses,
		deleteCampaign,
		duplicateCampaign,
		startCampaign,
		pauseCampaign,
		cancelCampaign
	} from '$lib/api/campaigns';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import * as Select from '$lib/components/ui/select';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Badge } from '$lib/components/ui/badge';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import {
		Plus,
		Search,
		MoreHorizontal,
		Eye,
		Edit,
		Copy,
		Trash2,
		Play,
		Pause,
		XCircle,
		Mail,
		Megaphone,
		Calendar,
		Rocket,
		Newspaper,
		UserPlus,
		Users,
		Send
	} from 'lucide-svelte';
	import { goto } from '$app/navigation';

	let loading = $state(true);
	let campaigns = $state<Campaign[]>([]);
	let campaignTypes = $state<Record<CampaignType, string>>({} as Record<CampaignType, string>);
	let campaignStatuses = $state<Record<CampaignStatus, string>>(
		{} as Record<CampaignStatus, string>
	);

	let search = $state('');
	let typeFilter = $state<CampaignType | ''>('');
	let statusFilter = $state<CampaignStatus | ''>('');

	let currentPage = $state(1);
	let totalPages = $state(1);
	let total = $state(0);

	let deleteDialogOpen = $state(false);
	let campaignToDelete = $state<Campaign | null>(null);
	let deleting = $state(false);

	const typeIcons: Record<CampaignType, typeof Mail> = {
		email: Mail,
		drip: Megaphone,
		event: Calendar,
		product_launch: Rocket,
		newsletter: Newspaper,
		re_engagement: UserPlus
	};

	const statusColors: Record<CampaignStatus, string> = {
		draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
		scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
		active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
		paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
		completed: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
		cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
	};

	async function loadData() {
		loading = true;
		try {
			const [typesData, statusesData] = await Promise.all([
				getCampaignTypes(),
				getCampaignStatuses()
			]);
			campaignTypes = typesData;
			campaignStatuses = statusesData;
			await loadCampaigns();
		} catch (error) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load campaigns');
		} finally {
			loading = false;
		}
	}

	async function loadCampaigns() {
		try {
			const result = await getCampaigns({
				search: search || undefined,
				type: typeFilter || undefined,
				status: statusFilter || undefined,
				page: currentPage,
				per_page: 20
			});
			campaigns = result.data;
			totalPages = result.meta.last_page;
			total = result.meta.total;
		} catch (error) {
			console.error('Failed to load campaigns:', error);
		}
	}

	function handleSearch() {
		currentPage = 1;
		loadCampaigns();
	}

	function handleTypeChange(value: string | undefined) {
		typeFilter = (value as CampaignType) || '';
		currentPage = 1;
		loadCampaigns();
	}

	function handleStatusChange(value: string | undefined) {
		statusFilter = (value as CampaignStatus) || '';
		currentPage = 1;
		loadCampaigns();
	}

	async function handleDuplicate(campaign: Campaign) {
		try {
			await duplicateCampaign(campaign.id);
			toast.success('Campaign duplicated');
			loadCampaigns();
		} catch (error) {
			console.error('Failed to duplicate:', error);
			toast.error('Failed to duplicate campaign');
		}
	}

	async function handleStart(campaign: Campaign) {
		try {
			await startCampaign(campaign.id);
			toast.success('Campaign started');
			loadCampaigns();
		} catch (error) {
			console.error('Failed to start:', error);
			toast.error('Failed to start campaign');
		}
	}

	async function handlePause(campaign: Campaign) {
		try {
			await pauseCampaign(campaign.id);
			toast.success('Campaign paused');
			loadCampaigns();
		} catch (error) {
			console.error('Failed to pause:', error);
			toast.error('Failed to pause campaign');
		}
	}

	async function handleCancel(campaign: Campaign) {
		try {
			await cancelCampaign(campaign.id);
			toast.success('Campaign cancelled');
			loadCampaigns();
		} catch (error) {
			console.error('Failed to cancel:', error);
			toast.error('Failed to cancel campaign');
		}
	}

	function confirmDelete(campaign: Campaign) {
		campaignToDelete = campaign;
		deleteDialogOpen = true;
	}

	async function handleDelete() {
		if (!campaignToDelete) return;

		deleting = true;
		try {
			await deleteCampaign(campaignToDelete.id);
			toast.success('Campaign deleted');
			deleteDialogOpen = false;
			campaignToDelete = null;
			loadCampaigns();
		} catch (error) {
			console.error('Failed to delete:', error);
			toast.error('Failed to delete campaign');
		} finally {
			deleting = false;
		}
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return '-';
		return new Date(dateString).toLocaleDateString();
	}

	function formatNumber(num: number): string {
		if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
		if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
		return num.toString();
	}

	$effect(() => {
		loadData();
	});
</script>

<svelte:head>
	<title>Marketing Campaigns | VRTX</title>
</svelte:head>

<div class="container mx-auto space-y-6 p-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-3xl font-bold tracking-tight">Marketing Campaigns</h1>
			<p class="text-muted-foreground">Create and manage your marketing campaigns</p>
		</div>
		<Button href="/marketing/campaigns/create">
			<Plus class="mr-2 h-4 w-4" />
			New Campaign
		</Button>
	</div>

	<!-- Filters -->
	<Card.Root>
		<Card.Content class="pt-6">
			<div class="flex flex-wrap items-center gap-4">
				<div class="relative flex-1 min-w-[200px]">
					<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
					<Input
						bind:value={search}
						placeholder="Search campaigns..."
						class="pl-9"
						onkeydown={(e) => e.key === 'Enter' && handleSearch()}
					/>
				</div>

				<Select.Root type="single" value={typeFilter} onValueChange={handleTypeChange}>
					<Select.Trigger class="w-[160px]">
						<span>{typeFilter ? campaignTypes[typeFilter] : 'All Types'}</span>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="">All Types</Select.Item>
						{#each Object.entries(campaignTypes) as [value, label]}
							<Select.Item {value}>{label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>

				<Select.Root type="single" value={statusFilter} onValueChange={handleStatusChange}>
					<Select.Trigger class="w-[160px]">
						<span>{statusFilter ? campaignStatuses[statusFilter] : 'All Statuses'}</span>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="">All Statuses</Select.Item>
						{#each Object.entries(campaignStatuses) as [value, label]}
							<Select.Item {value}>{label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>

				<Button variant="outline" onclick={handleSearch}>Search</Button>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Campaigns Table -->
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if campaigns.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<Mail class="h-12 w-12 text-muted-foreground" />
				<h3 class="mt-4 text-lg font-medium">No campaigns found</h3>
				<p class="mt-1 text-sm text-muted-foreground">
					{search || typeFilter || statusFilter
						? 'Try adjusting your filters'
						: 'Get started by creating your first campaign'}
				</p>
				{#if !search && !typeFilter && !statusFilter}
					<Button class="mt-4" href="/marketing/campaigns/create">
						<Plus class="mr-2 h-4 w-4" />
						Create Campaign
					</Button>
				{/if}
			</Card.Content>
		</Card.Root>
	{:else}
		<Card.Root>
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Campaign</Table.Head>
						<Table.Head>Type</Table.Head>
						<Table.Head>Status</Table.Head>
						<Table.Head class="text-right">Audiences</Table.Head>
						<Table.Head class="text-right">Sends</Table.Head>
						<Table.Head>Schedule</Table.Head>
						<Table.Head class="w-[50px]"></Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each campaigns as campaign}
						{@const Icon = typeIcons[campaign.type]}
						<Table.Row class="cursor-pointer" onclick={() => goto(`/marketing/campaigns/${campaign.id}`)}>
							<Table.Cell>
								<div class="flex items-center gap-3">
									<div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
										<Icon class="h-5 w-5 text-muted-foreground" />
									</div>
									<div>
										<p class="font-medium">{campaign.name}</p>
										{#if campaign.description}
											<p class="text-sm text-muted-foreground line-clamp-1">
												{campaign.description}
											</p>
										{/if}
									</div>
								</div>
							</Table.Cell>
							<Table.Cell>
								<Badge variant="outline">{campaignTypes[campaign.type]}</Badge>
							</Table.Cell>
							<Table.Cell>
								<Badge class={statusColors[campaign.status]}>
									{campaignStatuses[campaign.status]}
								</Badge>
							</Table.Cell>
							<Table.Cell class="text-right">
								<div class="flex items-center justify-end gap-1">
									<Users class="h-4 w-4 text-muted-foreground" />
									{campaign.audiences_count ?? 0}
								</div>
							</Table.Cell>
							<Table.Cell class="text-right">
								<div class="flex items-center justify-end gap-1">
									<Send class="h-4 w-4 text-muted-foreground" />
									{formatNumber(campaign.sends_count ?? 0)}
								</div>
							</Table.Cell>
							<Table.Cell>
								{#if campaign.start_date}
									<p class="text-sm">{formatDate(campaign.start_date)}</p>
									{#if campaign.end_date}
										<p class="text-xs text-muted-foreground">to {formatDate(campaign.end_date)}</p>
									{/if}
								{:else}
									<span class="text-muted-foreground">-</span>
								{/if}
							</Table.Cell>
							<Table.Cell>
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button variant="ghost" size="icon" {...props} onclick={(e) => e.stopPropagation()}>
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => goto(`/marketing/campaigns/${campaign.id}`)}>
											<Eye class="mr-2 h-4 w-4" />
											View
										</DropdownMenu.Item>
										<DropdownMenu.Item onclick={() => goto(`/marketing/campaigns/${campaign.id}/edit`)}>
											<Edit class="mr-2 h-4 w-4" />
											Edit
										</DropdownMenu.Item>
										<DropdownMenu.Item onclick={() => handleDuplicate(campaign)}>
											<Copy class="mr-2 h-4 w-4" />
											Duplicate
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										{#if campaign.status === 'draft' || campaign.status === 'paused'}
											<DropdownMenu.Item onclick={() => handleStart(campaign)}>
												<Play class="mr-2 h-4 w-4" />
												Start
											</DropdownMenu.Item>
										{/if}
										{#if campaign.status === 'active'}
											<DropdownMenu.Item onclick={() => handlePause(campaign)}>
												<Pause class="mr-2 h-4 w-4" />
												Pause
											</DropdownMenu.Item>
										{/if}
										{#if campaign.status !== 'completed' && campaign.status !== 'cancelled'}
											<DropdownMenu.Item onclick={() => handleCancel(campaign)}>
												<XCircle class="mr-2 h-4 w-4" />
												Cancel
											</DropdownMenu.Item>
										{/if}
										<DropdownMenu.Separator />
										<DropdownMenu.Item
											class="text-destructive"
											onclick={() => confirmDelete(campaign)}
										>
											<Trash2 class="mr-2 h-4 w-4" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		</Card.Root>

		<!-- Pagination -->
		{#if totalPages > 1}
			<div class="flex items-center justify-between">
				<p class="text-sm text-muted-foreground">
					Showing {(currentPage - 1) * 20 + 1} to {Math.min(currentPage * 20, total)} of {total} campaigns
				</p>
				<div class="flex gap-2">
					<Button
						variant="outline"
						size="sm"
						disabled={currentPage === 1}
						onclick={() => {
							currentPage--;
							loadCampaigns();
						}}
					>
						Previous
					</Button>
					<Button
						variant="outline"
						size="sm"
						disabled={currentPage === totalPages}
						onclick={() => {
							currentPage++;
							loadCampaigns();
						}}
					>
						Next
					</Button>
				</div>
			</div>
		{/if}
	{/if}
</div>

<!-- Delete Confirmation -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Campaign</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{campaignToDelete?.name}"? This action cannot be undone
				and all associated data will be permanently removed.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
				onclick={handleDelete}
				disabled={deleting}
			>
				{#if deleting}
					<Spinner class="mr-2 h-4 w-4" />
				{/if}
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
