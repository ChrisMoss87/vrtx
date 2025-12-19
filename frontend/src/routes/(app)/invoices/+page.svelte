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
	import * as Progress from '$lib/components/ui/progress';
	import {
		Plus,
		Search,
		MoreVertical,
		Trash2,
		Send,
		FileText,
		Eye,
		CheckCircle,
		XCircle,
		Clock,
		DollarSign,
		CreditCard,
		AlertTriangle,
		Ban,
		Calendar,
		User,
		TrendingUp,
		ArrowUpRight,
		Wallet,
		Receipt
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getInvoices,
		deleteInvoice,
		cancelInvoice,
		getInvoiceStats,
		type Invoice,
		type InvoiceStatus,
		type InvoiceStats
	} from '$lib/api/billing';

	let invoices = $state<Invoice[]>([]);
	let stats = $state<InvoiceStats | null>(null);
	let loading = $state(true);
	let searchQuery = $state('');
	let selectedStatus = $state<string>('all');
	let activeTab = $state('all');
	let deleteDialogOpen = $state(false);
	let invoiceToDelete = $state<Invoice | null>(null);
	let cancelDialogOpen = $state(false);
	let invoiceToCancel = $state<Invoice | null>(null);
	let currentPage = $state(1);
	let totalPages = $state(1);

	const statusConfig: Record<InvoiceStatus, { color: string; bg: string; icon: typeof FileText; label: string }> = {
		draft: { color: 'text-slate-600', bg: 'bg-slate-100', icon: FileText, label: 'Draft' },
		sent: { color: 'text-blue-600', bg: 'bg-blue-50', icon: Send, label: 'Sent' },
		viewed: { color: 'text-violet-600', bg: 'bg-violet-50', icon: Eye, label: 'Viewed' },
		paid: { color: 'text-emerald-600', bg: 'bg-emerald-50', icon: CheckCircle, label: 'Paid' },
		partial: { color: 'text-amber-600', bg: 'bg-amber-50', icon: CreditCard, label: 'Partial' },
		overdue: { color: 'text-red-600', bg: 'bg-red-50', icon: AlertTriangle, label: 'Overdue' },
		cancelled: { color: 'text-slate-500', bg: 'bg-slate-100', icon: Ban, label: 'Cancelled' }
	};

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [invoicesResponse, statsData] = await Promise.all([
				getInvoices({
					status: selectedStatus !== 'all' ? (selectedStatus as InvoiceStatus) : undefined,
					search: searchQuery || undefined,
					page: currentPage,
					per_page: 20
				}),
				getInvoiceStats()
			]);
			invoices = invoicesResponse.data;
			totalPages = invoicesResponse.last_page;
			stats = statsData;
		} catch (error) {
			console.error('Failed to load invoices:', error);
			toast.error('Failed to load invoices');
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

	async function handleCancel() {
		if (!invoiceToCancel) return;

		try {
			await cancelInvoice(invoiceToCancel.id);
			toast.success('Invoice cancelled');
			cancelDialogOpen = false;
			invoiceToCancel = null;
			await loadData();
		} catch (error) {
			console.error('Failed to cancel invoice:', error);
			toast.error('Failed to cancel invoice');
		}
	}

	async function handleDelete() {
		if (!invoiceToDelete) return;

		try {
			await deleteInvoice(invoiceToDelete.id);
			invoices = invoices.filter((i) => i.id !== invoiceToDelete!.id);
			toast.success('Invoice deleted');
			deleteDialogOpen = false;
			invoiceToDelete = null;
			await loadData();
		} catch (error) {
			console.error('Failed to delete invoice:', error);
			toast.error('Failed to delete invoice');
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

	function getDaysOverdue(dueDate: string | null): number {
		if (!dueDate) return 0;
		const due = new Date(dueDate);
		const now = new Date();
		const diffDays = Math.floor((now.getTime() - due.getTime()) / (1000 * 60 * 60 * 24));
		return Math.max(0, diffDays);
	}

	function getPaymentProgress(invoice: Invoice): number {
		if (invoice.total === 0) return 0;
		return Math.round(((invoice.total - invoice.balance_due) / invoice.total) * 100);
	}

	const filteredInvoices = $derived(() => {
		let result = invoices;

		if (activeTab === 'draft') {
			result = result.filter((i) => i.status === 'draft');
		} else if (activeTab === 'sent') {
			result = result.filter((i) => i.status === 'sent' || i.status === 'viewed');
		} else if (activeTab === 'paid') {
			result = result.filter((i) => i.status === 'paid');
		} else if (activeTab === 'overdue') {
			result = result.filter((i) => i.status === 'overdue' || i.status === 'partial');
		}

		return result;
	});

	const collectionRate = $derived(() => {
		if (!stats || stats.total_revenue === 0) return 0;
		const collected = stats.total_revenue - stats.total_outstanding;
		return Math.round((collected / stats.total_revenue) * 100);
	});
</script>

<svelte:head>
	<title>Invoices | VRTX CRM</title>
</svelte:head>

<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white">
	<div class="container mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
		<!-- Header -->
		<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
			<div>
				<h1 class="text-3xl font-bold tracking-tight text-slate-900">Invoices</h1>
				<p class="mt-1 text-slate-500">Track payments and manage customer invoices</p>
			</div>
			<Button size="lg" onclick={() => goto('/invoices/new')} class="shadow-sm">
				<Plus class="mr-2 h-5 w-5" />
				New Invoice
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
								<p class="text-sm font-medium text-slate-500">Total Revenue</p>
								<p class="mt-2 text-3xl font-bold text-slate-900">{formatCurrency(stats.total_revenue)}</p>
								<p class="mt-1 text-xs text-slate-400">{stats.total} invoices total</p>
							</div>
							<div class="rounded-xl bg-blue-100 p-3">
								<Receipt class="h-6 w-6 text-blue-600" />
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				<Card.Root class="relative overflow-hidden border-0 bg-white shadow-sm">
					<div class="absolute right-0 top-0 h-24 w-24 -translate-y-4 translate-x-4 rounded-full bg-emerald-500/10"></div>
					<Card.Content class="p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-sm font-medium text-slate-500">Collection Rate</p>
								<p class="mt-2 text-3xl font-bold text-slate-900">{collectionRate()}%</p>
								<p class="mt-1 flex items-center gap-1 text-xs text-emerald-600">
									<ArrowUpRight class="h-3 w-3" />
									{stats.paid} fully paid
								</p>
							</div>
							<div class="rounded-xl bg-emerald-100 p-3">
								<TrendingUp class="h-6 w-6 text-emerald-600" />
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				<Card.Root class="relative overflow-hidden border-0 bg-white shadow-sm">
					<div class="absolute right-0 top-0 h-24 w-24 -translate-y-4 translate-x-4 rounded-full bg-amber-500/10"></div>
					<Card.Content class="p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-sm font-medium text-slate-500">Outstanding</p>
								<p class="mt-2 text-3xl font-bold text-slate-900">{formatCurrency(stats.total_outstanding)}</p>
								<p class="mt-1 text-xs text-slate-400">{stats.sent + (stats.partial || 0)} awaiting payment</p>
							</div>
							<div class="rounded-xl bg-amber-100 p-3">
								<Wallet class="h-6 w-6 text-amber-600" />
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				<Card.Root class="relative overflow-hidden border-0 bg-white shadow-sm">
					<div class="absolute right-0 top-0 h-24 w-24 -translate-y-4 translate-x-4 rounded-full bg-red-500/10"></div>
					<Card.Content class="p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-sm font-medium text-slate-500">Overdue</p>
								<p class="mt-2 text-3xl font-bold text-red-600">{formatCurrency(stats.overdue_amount)}</p>
								<p class="mt-1 text-xs text-red-500">{stats.overdue} invoices past due</p>
							</div>
							<div class="rounded-xl bg-red-100 p-3">
								<AlertTriangle class="h-6 w-6 text-red-600" />
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
							<Tabs.Trigger value="paid" class="px-4">
								Paid
								{#if stats?.paid}<span class="ml-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">{stats.paid}</span>{/if}
							</Tabs.Trigger>
							<Tabs.Trigger value="overdue" class="px-4">
								Overdue
								{#if stats?.overdue}<span class="ml-1.5 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">{stats.overdue}</span>{/if}
							</Tabs.Trigger>
						</Tabs.List>
					</Tabs.Root>

					<div class="flex flex-wrap items-center gap-3">
						<div class="relative">
							<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
							<Input
								type="search"
								placeholder="Search invoices..."
								class="w-64 border-slate-200 bg-slate-50 pl-9 focus:bg-white"
								bind:value={searchQuery}
								onkeydown={(e) => e.key === 'Enter' && handleSearch()}
							/>
						</div>

						<Select.Root type="single" bind:value={selectedStatus} onValueChange={() => handleStatusChange()}>
							<Select.Trigger class="w-36 border-slate-200 bg-slate-50">
								{selectedStatus === 'all' ? 'All Status' : statusConfig[selectedStatus as InvoiceStatus]?.label || selectedStatus}
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

			<!-- Invoices List -->
			<div class="p-6">
				{#if loading}
					<div class="flex flex-col items-center justify-center py-16">
						<div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-blue-600"></div>
						<p class="mt-4 text-sm text-slate-500">Loading invoices...</p>
					</div>
				{:else if filteredInvoices().length === 0}
					<div class="flex flex-col items-center justify-center py-16">
						<div class="rounded-full bg-slate-100 p-4">
							<FileText class="h-8 w-8 text-slate-400" />
						</div>
						<h3 class="mt-4 text-lg font-medium text-slate-900">No invoices found</h3>
						<p class="mt-1 text-sm text-slate-500">
							{searchQuery || selectedStatus !== 'all'
								? 'Try adjusting your filters'
								: 'Create your first invoice to get started'}
						</p>
						{#if !searchQuery && selectedStatus === 'all'}
							<Button class="mt-4" onclick={() => goto('/invoices/new')}>
								<Plus class="mr-2 h-4 w-4" />
								Create Invoice
							</Button>
						{/if}
					</div>
				{:else}
					<div class="space-y-3">
						{#each filteredInvoices() as invoice (invoice.id)}
							{@const config = statusConfig[invoice.status]}
							{@const progress = getPaymentProgress(invoice)}
							{@const daysOverdue = getDaysOverdue(invoice.due_date)}
							<div class="group relative rounded-xl border border-slate-100 bg-white p-4 transition-all hover:border-slate-200 hover:shadow-sm">
								<div class="flex items-center gap-4">
									<!-- Status Icon -->
									<div class="flex-shrink-0 rounded-lg {config.bg} p-2.5">
										<svelte:component this={config.icon} class="h-5 w-5 {config.color}" />
									</div>

									<!-- Main Content -->
									<div class="min-w-0 flex-1">
										<div class="flex items-center gap-3">
											<a href="/invoices/{invoice.id}" class="font-semibold text-slate-900 hover:text-blue-600">
												{invoice.invoice_number}
											</a>
											<Badge class="{config.bg} {config.color} border-0 font-medium">
												{config.label}
											</Badge>
											{#if invoice.status === 'overdue' && daysOverdue > 0}
												<Badge variant="outline" class="border-red-200 bg-red-50 text-red-700">
													<AlertTriangle class="mr-1 h-3 w-3" />
													{daysOverdue}d overdue
												</Badge>
											{/if}
										</div>
										<div class="mt-1 flex items-center gap-4 text-sm text-slate-500">
											{#if invoice.title}
												<span class="truncate">{invoice.title}</span>
												<span class="text-slate-300">•</span>
											{/if}
											<span class="flex items-center gap-1">
												<Calendar class="h-3.5 w-3.5" />
												{formatDate(invoice.issue_date)}
											</span>
											{#if invoice.due_date}
												<span class="flex items-center gap-1" class:text-red-500={invoice.status === 'overdue'}>
													<Clock class="h-3.5 w-3.5" />
													Due {formatDate(invoice.due_date)}
												</span>
											{/if}
										</div>
									</div>

									<!-- Payment Progress (for partial payments) -->
									{#if invoice.status === 'partial' || (invoice.balance_due > 0 && invoice.balance_due < invoice.total)}
										<div class="flex-shrink-0 w-32">
											<div class="flex items-center justify-between text-xs mb-1">
												<span class="text-slate-500">Paid</span>
												<span class="font-medium text-emerald-600">{progress}%</span>
											</div>
											<div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
												<div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {progress}%"></div>
											</div>
										</div>
									{/if}

									<!-- Amount -->
									<div class="flex-shrink-0 text-right">
										<p class="text-lg font-bold text-slate-900">
											{formatCurrencyFull(invoice.total, invoice.currency)}
										</p>
										{#if invoice.balance_due > 0 && invoice.balance_due < invoice.total}
											<p class="text-xs text-amber-600">
												{formatCurrencyFull(invoice.balance_due, invoice.currency)} due
											</p>
										{:else if invoice.balance_due > 0}
											<p class="text-xs text-slate-400">Balance due</p>
										{:else}
											<p class="text-xs text-emerald-600">Paid in full</p>
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
												<DropdownMenu.Item onclick={() => goto(`/invoices/${invoice.id}`)}>
													<Eye class="mr-2 h-4 w-4" />
													View Details
												</DropdownMenu.Item>
												{#if invoice.status === 'draft'}
													<DropdownMenu.Item onclick={() => goto(`/invoices/${invoice.id}?edit=true`)}>
														<FileText class="mr-2 h-4 w-4" />
														Edit Invoice
													</DropdownMenu.Item>
													<DropdownMenu.Item onclick={() => goto(`/invoices/${invoice.id}?send=true`)}>
														<Send class="mr-2 h-4 w-4" />
														Send Invoice
													</DropdownMenu.Item>
												{/if}
												{#if invoice.status !== 'paid' && invoice.status !== 'cancelled'}
													<DropdownMenu.Item onclick={() => goto(`/invoices/${invoice.id}?payment=true`)}>
														<CreditCard class="mr-2 h-4 w-4" />
														Record Payment
													</DropdownMenu.Item>
												{/if}
												<DropdownMenu.Separator />
												<DropdownMenu.Item onclick={() => goto(`/invoices/${invoice.id}?download=true`)}>
													<DollarSign class="mr-2 h-4 w-4" />
													Download PDF
												</DropdownMenu.Item>
												{#if invoice.status !== 'cancelled' && invoice.status !== 'paid'}
													<DropdownMenu.Separator />
													<DropdownMenu.Item
														class="text-orange-600 focus:bg-orange-50 focus:text-orange-600"
														onclick={() => {
															invoiceToCancel = invoice;
															cancelDialogOpen = true;
														}}
													>
														<XCircle class="mr-2 h-4 w-4" />
														Cancel Invoice
													</DropdownMenu.Item>
												{/if}
												{#if invoice.status === 'draft'}
													<DropdownMenu.Item
														class="text-red-600 focus:bg-red-50 focus:text-red-600"
														onclick={() => {
															invoiceToDelete = invoice;
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
			<AlertDialog.Title>Delete Invoice</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete invoice <span class="font-semibold">{invoiceToDelete?.invoice_number}</span>? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleDelete} class="bg-red-600 hover:bg-red-700">Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>

<!-- Cancel Confirmation Dialog -->
<AlertDialog.Root bind:open={cancelDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Cancel Invoice</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to cancel invoice <span class="font-semibold">{invoiceToCancel?.invoice_number}</span>? This will void the invoice and it cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Keep Invoice</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleCancel} class="bg-orange-600 hover:bg-orange-700">Cancel Invoice</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
