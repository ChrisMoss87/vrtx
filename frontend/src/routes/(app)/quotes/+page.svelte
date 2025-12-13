<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Tooltip from '$lib/components/ui/tooltip';
	import {
		Plus,
		Search,
		MoreVertical,
		Copy,
		Trash2,
		Send,
		FileText,
		Eye,
		CheckCircle,
		XCircle,
		Clock,
		Receipt,
		DollarSign,
		ExternalLink,
		TrendingUp,
		ArrowUpRight,
		Calendar,
		User
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getQuotes,
		deleteQuote,
		duplicateQuote,
		getQuoteStats,
		type Quote,
		type QuoteStatus,
		type QuoteStats
	} from '$lib/api/billing';

	let quotes = $state<Quote[]>([]);
	let stats = $state<QuoteStats | null>(null);
	let loading = $state(true);
	let searchQuery = $state('');
	let selectedStatus = $state<string>('all');
	let activeTab = $state('all');
	let deleteDialogOpen = $state(false);
	let quoteToDelete = $state<Quote | null>(null);
	let currentPage = $state(1);
	let totalPages = $state(1);

	const statusConfig: Record<QuoteStatus, { color: string; bg: string; icon: typeof FileText; label: string }> = {
		draft: { color: 'text-slate-600', bg: 'bg-slate-100', icon: FileText, label: 'Draft' },
		sent: { color: 'text-blue-600', bg: 'bg-blue-50', icon: Send, label: 'Sent' },
		viewed: { color: 'text-violet-600', bg: 'bg-violet-50', icon: Eye, label: 'Viewed' },
		accepted: { color: 'text-emerald-600', bg: 'bg-emerald-50', icon: CheckCircle, label: 'Accepted' },
		rejected: { color: 'text-red-600', bg: 'bg-red-50', icon: XCircle, label: 'Rejected' },
		expired: { color: 'text-amber-600', bg: 'bg-amber-50', icon: Clock, label: 'Expired' }
	};

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [quotesResponse, statsData] = await Promise.all([
				getQuotes({
					status: selectedStatus !== 'all' ? (selectedStatus as QuoteStatus) : undefined,
					search: searchQuery || undefined,
					page: currentPage,
					per_page: 20
				}),
				getQuoteStats()
			]);
			quotes = quotesResponse.data;
			totalPages = quotesResponse.last_page;
			stats = statsData;
		} catch (error) {
			console.error('Failed to load quotes:', error);
			toast.error('Failed to load quotes');
		} finally {
			loading = false;
		}
	}

	async function handleSearch() {
		currentPage = 1;
		await loadData();
	}

	async function handleStatusChange() {
		currentPage = 1;
		await loadData();
	}

	async function handleDuplicate(quote: Quote) {
		try {
			const duplicated = await duplicateQuote(quote.id);
			quotes = [duplicated, ...quotes];
			toast.success('Quote duplicated');
		} catch (error) {
			console.error('Failed to duplicate quote:', error);
			toast.error('Failed to duplicate quote');
		}
	}

	async function handleDelete() {
		if (!quoteToDelete) return;

		try {
			await deleteQuote(quoteToDelete.id);
			quotes = quotes.filter((q) => q.id !== quoteToDelete!.id);
			toast.success('Quote deleted');
			deleteDialogOpen = false;
			quoteToDelete = null;
			await loadData();
		} catch (error) {
			console.error('Failed to delete quote:', error);
			toast.error('Failed to delete quote');
		}
	}

	function formatCurrency(amount: number, currency: string = 'USD'): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency,
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(amount);
	}

	function formatCurrencyFull(amount: number, currency: string = 'USD'): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency
		}).format(amount);
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return '-';
		return new Date(dateString).toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric',
			year: 'numeric'
		});
	}

	function formatRelativeDate(dateString: string | null): string {
		if (!dateString) return '-';
		const date = new Date(dateString);
		const now = new Date();
		const diffDays = Math.ceil((date.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

		if (diffDays < 0) return `${Math.abs(diffDays)}d ago`;
		if (diffDays === 0) return 'Today';
		if (diffDays === 1) return 'Tomorrow';
		if (diffDays <= 7) return `${diffDays}d left`;
		return formatDate(dateString);
	}

	function isExpiringSoon(dateString: string | null): boolean {
		if (!dateString) return false;
		const date = new Date(dateString);
		const now = new Date();
		const diffDays = Math.ceil((date.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
		return diffDays >= 0 && diffDays <= 7;
	}

	const filteredQuotes = $derived(() => {
		let result = quotes;

		if (activeTab === 'draft') {
			result = result.filter((q) => q.status === 'draft');
		} else if (activeTab === 'sent') {
			result = result.filter((q) => q.status === 'sent' || q.status === 'viewed');
		} else if (activeTab === 'accepted') {
			result = result.filter((q) => q.status === 'accepted');
		} else if (activeTab === 'rejected') {
			result = result.filter((q) => q.status === 'rejected' || q.status === 'expired');
		}

		return result;
	});

	const conversionRate = $derived(() => {
		if (!stats || stats.total === 0) return 0;
		return Math.round((stats.accepted / stats.total) * 100);
	});
</script>

<svelte:head>
	<title>Quotes | VRTX CRM</title>
</svelte:head>

<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white">
	<div class="container mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
		<!-- Header -->
		<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
			<div>
				<h1 class="text-3xl font-bold tracking-tight text-slate-900">Quotes</h1>
				<p class="mt-1 text-slate-500">Create and manage sales quotes for your customers</p>
			</div>
			<Button size="lg" onclick={() => goto('/quotes/new')} class="shadow-sm">
				<Plus class="mr-2 h-5 w-5" />
				New Quote
			</Button>
		</div>

		<!-- Stats Cards -->
		{#if stats}
			<div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
				<Card.Root class="relative overflow-hidden border-0 bg-white shadow-sm">
					<div class="absolute right-0 top-0 h-24 w-24 -translate-y-4 translate-x-4 rounded-full bg-blue-500/10"></div>
					<Card.Content class="p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-sm font-medium text-slate-500">Total Quotes</p>
								<p class="mt-2 text-3xl font-bold text-slate-900">{stats.total}</p>
								<p class="mt-1 text-xs text-slate-400">{stats.draft} drafts pending</p>
							</div>
							<div class="rounded-xl bg-blue-100 p-3">
								<FileText class="h-6 w-6 text-blue-600" />
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				<Card.Root class="relative overflow-hidden border-0 bg-white shadow-sm">
					<div class="absolute right-0 top-0 h-24 w-24 -translate-y-4 translate-x-4 rounded-full bg-emerald-500/10"></div>
					<Card.Content class="p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-sm font-medium text-slate-500">Conversion Rate</p>
								<p class="mt-2 text-3xl font-bold text-slate-900">{conversionRate()}%</p>
								<p class="mt-1 text-xs text-slate-400">{stats.accepted} of {stats.total} accepted</p>
							</div>
							<div class="rounded-xl bg-emerald-100 p-3">
								<TrendingUp class="h-6 w-6 text-emerald-600" />
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				<Card.Root class="relative overflow-hidden border-0 bg-white shadow-sm">
					<div class="absolute right-0 top-0 h-24 w-24 -translate-y-4 translate-x-4 rounded-full bg-violet-500/10"></div>
					<Card.Content class="p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-sm font-medium text-slate-500">Pending Value</p>
								<p class="mt-2 text-3xl font-bold text-slate-900">{formatCurrency(stats.pending_value)}</p>
								<p class="mt-1 text-xs text-slate-400">{stats.sent} quotes awaiting response</p>
							</div>
							<div class="rounded-xl bg-violet-100 p-3">
								<Clock class="h-6 w-6 text-violet-600" />
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				<Card.Root class="relative overflow-hidden border-0 bg-white shadow-sm">
					<div class="absolute right-0 top-0 h-24 w-24 -translate-y-4 translate-x-4 rounded-full bg-amber-500/10"></div>
					<Card.Content class="p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-sm font-medium text-slate-500">Won Revenue</p>
								<p class="mt-2 text-3xl font-bold text-slate-900">{formatCurrency(stats.total_value)}</p>
								<p class="mt-1 flex items-center gap-1 text-xs text-emerald-600">
									<ArrowUpRight class="h-3 w-3" />
									From accepted quotes
								</p>
							</div>
							<div class="rounded-xl bg-amber-100 p-3">
								<DollarSign class="h-6 w-6 text-amber-600" />
							</div>
						</div>
					</Card.Content>
				</Card.Root>
			</div>
		{/if}

		<!-- Main Content Card -->
		<Card.Root class="border-0 bg-white shadow-sm">
			<!-- Tabs & Filters -->
			<div class="border-b border-slate-100 px-6 pt-4">
				<div class="flex flex-col gap-4 pb-4 lg:flex-row lg:items-center lg:justify-between">
					<Tabs.Root bind:value={activeTab}>
						<Tabs.List class="h-10 bg-slate-100/50 p-1">
							<Tabs.Trigger value="all" class="px-4">All</Tabs.Trigger>
							<Tabs.Trigger value="draft" class="px-4">
								Draft
								{#if stats?.draft}<span class="ml-1.5 rounded-full bg-slate-200 px-2 py-0.5 text-xs">{stats.draft}</span>{/if}
							</Tabs.Trigger>
							<Tabs.Trigger value="sent" class="px-4">
								Pending
								{#if stats?.sent}<span class="ml-1.5 rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">{stats.sent}</span>{/if}
							</Tabs.Trigger>
							<Tabs.Trigger value="accepted" class="px-4">
								Won
								{#if stats?.accepted}<span class="ml-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">{stats.accepted}</span>{/if}
							</Tabs.Trigger>
							<Tabs.Trigger value="rejected" class="px-4">
								Lost
								{#if (stats?.rejected || 0) + (stats?.expired || 0) > 0}<span class="ml-1.5 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">{(stats?.rejected || 0) + (stats?.expired || 0)}</span>{/if}
							</Tabs.Trigger>
						</Tabs.List>
					</Tabs.Root>

					<div class="flex flex-wrap items-center gap-3">
						<div class="relative">
							<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
							<Input
								type="search"
								placeholder="Search quotes..."
								class="w-64 border-slate-200 bg-slate-50 pl-9 focus:bg-white"
								bind:value={searchQuery}
								onkeydown={(e) => e.key === 'Enter' && handleSearch()}
							/>
						</div>

						<Select.Root type="single" bind:value={selectedStatus} onValueChange={() => handleStatusChange()}>
							<Select.Trigger class="w-36 border-slate-200 bg-slate-50">
								{selectedStatus === 'all' ? 'All Status' : statusConfig[selectedStatus as QuoteStatus]?.label || selectedStatus}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="all">All Status</Select.Item>
								{#each Object.entries(statusConfig) as [key, config]}
									<Select.Item value={key}>
										<div class="flex items-center gap-2">
											<svelte:component this={config.icon} class="h-3.5 w-3.5 {config.color}" />
											{config.label}
										</div>
									</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
				</div>
			</div>

			<!-- Quotes List -->
			<div class="p-6">
				{#if loading}
					<div class="flex flex-col items-center justify-center py-16">
						<div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-blue-600"></div>
						<p class="mt-4 text-sm text-slate-500">Loading quotes...</p>
					</div>
				{:else if filteredQuotes().length === 0}
					<div class="flex flex-col items-center justify-center py-16">
						<div class="rounded-full bg-slate-100 p-4">
							<FileText class="h-8 w-8 text-slate-400" />
						</div>
						<h3 class="mt-4 text-lg font-medium text-slate-900">No quotes found</h3>
						<p class="mt-1 text-sm text-slate-500">
							{searchQuery || selectedStatus !== 'all'
								? 'Try adjusting your filters'
								: 'Create your first quote to get started'}
						</p>
						{#if !searchQuery && selectedStatus === 'all'}
							<Button class="mt-4" onclick={() => goto('/quotes/new')}>
								<Plus class="mr-2 h-4 w-4" />
								Create Quote
							</Button>
						{/if}
					</div>
				{:else}
					<div class="space-y-3">
						{#each filteredQuotes() as quote (quote.id)}
							{@const config = statusConfig[quote.status]}
							<div class="group relative rounded-xl border border-slate-100 bg-white p-4 transition-all hover:border-slate-200 hover:shadow-sm">
								<div class="flex items-center gap-4">
									<!-- Status Icon -->
									<div class="flex-shrink-0 rounded-lg {config.bg} p-2.5">
										<svelte:component this={config.icon} class="h-5 w-5 {config.color}" />
									</div>

									<!-- Main Content -->
									<div class="min-w-0 flex-1">
										<div class="flex items-center gap-3">
											<a href="/quotes/{quote.id}" class="font-semibold text-slate-900 hover:text-blue-600">
												{quote.quote_number}
											</a>
											<Badge class="{config.bg} {config.color} border-0 font-medium">
												{config.label}
											</Badge>
											{#if quote.status !== 'accepted' && quote.status !== 'rejected' && quote.status !== 'expired' && isExpiringSoon(quote.valid_until)}
												<Badge variant="outline" class="border-amber-200 bg-amber-50 text-amber-700">
													<Clock class="mr-1 h-3 w-3" />
													{formatRelativeDate(quote.valid_until)}
												</Badge>
											{/if}
										</div>
										<div class="mt-1 flex items-center gap-4 text-sm text-slate-500">
											{#if quote.title}
												<span class="truncate">{quote.title}</span>
												<span class="text-slate-300">•</span>
											{/if}
											<span class="flex items-center gap-1">
												<Calendar class="h-3.5 w-3.5" />
												{formatDate(quote.created_at)}
											</span>
											{#if quote.assignedTo}
												<span class="flex items-center gap-1">
													<User class="h-3.5 w-3.5" />
													{quote.assignedTo.name}
												</span>
											{/if}
										</div>
									</div>

									<!-- Amount -->
									<div class="flex-shrink-0 text-right">
										<p class="text-lg font-bold text-slate-900">
											{formatCurrencyFull(quote.total, quote.currency)}
										</p>
										{#if quote.valid_until && quote.status !== 'accepted' && quote.status !== 'rejected' && quote.status !== 'expired'}
											<p class="text-xs text-slate-400">
												Valid until {formatDate(quote.valid_until)}
											</p>
										{/if}
									</div>

									<!-- Actions -->
									<div class="flex-shrink-0">
										<DropdownMenu.Root>
											<DropdownMenu.Trigger>
												{#snippet child({ props })}
													<Button variant="ghost" size="icon" class="h-8 w-8 text-slate-400 hover:text-slate-600" {...props}>
														<MoreVertical class="h-4 w-4" />
													</Button>
												{/snippet}
											</DropdownMenu.Trigger>
											<DropdownMenu.Content align="end" class="w-48">
												<DropdownMenu.Item onclick={() => goto(`/quotes/${quote.id}`)}>
													<Eye class="mr-2 h-4 w-4" />
													View Details
												</DropdownMenu.Item>
												{#if quote.status === 'draft'}
													<DropdownMenu.Item onclick={() => goto(`/quotes/${quote.id}?edit=true`)}>
														<FileText class="mr-2 h-4 w-4" />
														Edit Quote
													</DropdownMenu.Item>
													<DropdownMenu.Item onclick={() => goto(`/quotes/${quote.id}?send=true`)}>
														<Send class="mr-2 h-4 w-4" />
														Send Quote
													</DropdownMenu.Item>
												{/if}
												{#if quote.view_token}
													<DropdownMenu.Item onclick={() => window.open(`/quote/${quote.view_token}`, '_blank')}>
														<ExternalLink class="mr-2 h-4 w-4" />
														Public Link
													</DropdownMenu.Item>
												{/if}
												<DropdownMenu.Separator />
												<DropdownMenu.Item onclick={() => handleDuplicate(quote)}>
													<Copy class="mr-2 h-4 w-4" />
													Duplicate
												</DropdownMenu.Item>
												{#if quote.status === 'accepted' && !quote.invoice}
													<DropdownMenu.Item onclick={() => goto(`/quotes/${quote.id}?convert=true`)}>
														<Receipt class="mr-2 h-4 w-4" />
														Convert to Invoice
													</DropdownMenu.Item>
												{/if}
												{#if quote.status === 'draft'}
													<DropdownMenu.Separator />
													<DropdownMenu.Item
														class="text-red-600 focus:bg-red-50 focus:text-red-600"
														onclick={() => {
															quoteToDelete = quote;
															deleteDialogOpen = true;
														}}
													>
														<Trash2 class="mr-2 h-4 w-4" />
														Delete
													</DropdownMenu.Item>
												{/if}
											</DropdownMenu.Content>
										</DropdownMenu.Root>
									</div>
								</div>
							</div>
						{/each}
					</div>

					<!-- Pagination -->
					{#if totalPages > 1}
						<div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
							<p class="text-sm text-slate-500">
								Page {currentPage} of {totalPages}
							</p>
							<div class="flex gap-2">
								<Button
									variant="outline"
									size="sm"
									disabled={currentPage === 1}
									onclick={() => {
										currentPage--;
										loadData();
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
										loadData();
									}}
								>
									Next
								</Button>
							</div>
						</div>
					{/if}
				{/if}
			</div>
		</Card.Root>
	</div>
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Quote</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete quote <span class="font-semibold">{quoteToDelete?.quote_number}</span>? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleDelete} class="bg-red-600 hover:bg-red-700">Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
