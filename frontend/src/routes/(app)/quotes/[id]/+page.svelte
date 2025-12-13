<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import {
		ArrowLeft,
		Send,
		Copy,
		Trash2,
		FileText,
		Eye,
		CheckCircle,
		XCircle,
		Clock,
		Receipt,
		Plus,
		Download,
		ExternalLink,
		Save
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getQuote,
		updateQuote,
		sendQuote,
		duplicateQuote,
		getQuotePdf,
		convertQuoteToInvoice,
		getProducts,
		type Quote,
		type QuoteStatus,
		type QuoteLineItemInput,
		type Product
	} from '$lib/api/billing';

	const quoteId = $derived(Number($page.params.id));
	const editMode = $derived($page.url.searchParams.get('edit') === 'true');
	const convertMode = $derived($page.url.searchParams.get('convert') === 'true');

	let quote = $state<Quote | null>(null);
	let products = $state<Product[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let sendDialogOpen = $state(false);
	let sendEmail = $state('');
	let sendMessage = $state('');
	let sending = $state(false);

	// Edit state
	let editTitle = $state('');
	let editValidUntil = $state('');
	let editTerms = $state('');
	let editNotes = $state('');
	let editLineItems = $state<QuoteLineItemInput[]>([]);

	const statusColors: Record<QuoteStatus, string> = {
		draft: 'bg-gray-100 text-gray-800',
		sent: 'bg-blue-100 text-blue-800',
		viewed: 'bg-purple-100 text-purple-800',
		accepted: 'bg-green-100 text-green-800',
		rejected: 'bg-red-100 text-red-800',
		expired: 'bg-orange-100 text-orange-800'
	};

	const statusIcons: Record<QuoteStatus, typeof FileText> = {
		draft: FileText,
		sent: Send,
		viewed: Eye,
		accepted: CheckCircle,
		rejected: XCircle,
		expired: Clock
	};

	onMount(async () => {
		await loadQuote();
	});

	async function loadQuote() {
		loading = true;
		try {
			const [quoteData, productsData] = await Promise.all([
				getQuote(quoteId),
				getProducts({ active_only: true })
			]);
			quote = quoteData;
			products = productsData;

			// Populate edit state
			editTitle = quote.title || '';
			editValidUntil = quote.valid_until?.split('T')[0] || '';
			editTerms = quote.terms || '';
			editNotes = quote.notes || '';
			editLineItems =
				quote.lineItems?.map((item) => ({
					product_id: item.product_id,
					description: item.description,
					quantity: item.quantity,
					unit_price: item.unit_price,
					discount_percent: item.discount_percent,
					tax_rate: item.tax_rate
				})) || [];

			// Handle convert mode
			if (convertMode && quote.status === 'accepted' && !quote.invoice) {
				handleConvertToInvoice();
			}
		} catch (error) {
			console.error('Failed to load quote:', error);
			toast.error('Failed to load quote');
		} finally {
			loading = false;
		}
	}

	async function handleSave() {
		if (!quote) return;
		saving = true;
		try {
			const updated = await updateQuote(quote.id, {
				title: editTitle || undefined,
				valid_until: editValidUntil || undefined,
				terms: editTerms || undefined,
				notes: editNotes || undefined,
				line_items: editLineItems
			});
			quote = updated;
			toast.success('Quote updated');
			goto(`/quotes/${quote.id}`);
		} catch (error) {
			console.error('Failed to update quote:', error);
			toast.error('Failed to update quote');
		} finally {
			saving = false;
		}
	}

	async function handleSend() {
		if (!quote || !sendEmail) return;
		sending = true;
		try {
			const updated = await sendQuote(quote.id, {
				to_email: sendEmail,
				message: sendMessage || undefined
			});
			quote = updated;
			toast.success('Quote sent successfully');
			sendDialogOpen = false;
			sendEmail = '';
			sendMessage = '';
		} catch (error) {
			console.error('Failed to send quote:', error);
			toast.error('Failed to send quote');
		} finally {
			sending = false;
		}
	}

	async function handleDuplicate() {
		if (!quote) return;
		try {
			const duplicated = await duplicateQuote(quote.id);
			toast.success('Quote duplicated');
			goto(`/quotes/${duplicated.id}?edit=true`);
		} catch (error) {
			console.error('Failed to duplicate quote:', error);
			toast.error('Failed to duplicate quote');
		}
	}

	async function handleDownloadPdf() {
		if (!quote) return;
		try {
			const pdfData = await getQuotePdf(quote.id);
			// In a real app, this would trigger PDF download
			// For now, just show the data structure
			console.log('PDF Data:', pdfData);
			toast.success('PDF generation ready (check console for data)');
		} catch (error) {
			console.error('Failed to generate PDF:', error);
			toast.error('Failed to generate PDF');
		}
	}

	async function handleConvertToInvoice() {
		if (!quote) return;
		try {
			const invoice = await convertQuoteToInvoice(quote.id);
			toast.success('Quote converted to invoice');
			goto(`/invoices/${invoice.id}`);
		} catch (error) {
			console.error('Failed to convert to invoice:', error);
			toast.error('Failed to convert to invoice');
		}
	}

	function addLineItem() {
		editLineItems = [
			...editLineItems,
			{
				description: '',
				quantity: 1,
				unit_price: 0,
				discount_percent: 0,
				tax_rate: 0
			}
		];
	}

	function removeLineItem(index: number) {
		editLineItems = editLineItems.filter((_, i) => i !== index);
	}

	function selectProduct(index: number, productId: string) {
		const product = products.find((p) => String(p.id) === productId);
		if (product) {
			editLineItems[index] = {
				...editLineItems[index],
				product_id: product.id,
				description: product.description || product.name,
				unit_price: product.unit_price,
				tax_rate: product.tax_rate || 0
			};
		}
	}

	function formatCurrency(amount: number, currency: string = 'USD'): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency
		}).format(amount);
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return '-';
		return new Date(dateString).toLocaleDateString();
	}

	function formatDateTime(dateString: string | null): string {
		if (!dateString) return '-';
		return new Date(dateString).toLocaleString();
	}

	const calculatedSubtotal = $derived(
		editLineItems.reduce((sum, item) => {
			const lineTotal = (item.quantity || 1) * (item.unit_price || 0);
			const discount = lineTotal * ((item.discount_percent || 0) / 100);
			return sum + (lineTotal - discount);
		}, 0)
	);

	const calculatedTax = $derived(
		editLineItems.reduce((sum, item) => {
			const lineTotal = (item.quantity || 1) * (item.unit_price || 0);
			const discount = lineTotal * ((item.discount_percent || 0) / 100);
			const taxableAmount = lineTotal - discount;
			return sum + taxableAmount * ((item.tax_rate || 0) / 100);
		}, 0)
	);

	const calculatedTotal = $derived(calculatedSubtotal + calculatedTax);
</script>

<svelte:head>
	<title>{quote?.quote_number || 'Quote'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/quotes')}>
				<ArrowLeft class="h-5 w-5" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">{quote?.quote_number || 'Loading...'}</h1>
				{#if quote?.title}
					<p class="text-muted-foreground">{quote.title}</p>
				{/if}
			</div>
			{#if quote}
				{@const StatusIcon = statusIcons[quote.status]}
				<Badge class={statusColors[quote.status]}>
					<StatusIcon class="mr-1 h-3 w-3" />
					{quote.status.charAt(0).toUpperCase() + quote.status.slice(1)}
				</Badge>
			{/if}
		</div>
		{#if quote && !editMode}
			<div class="flex items-center gap-2">
				{#if quote.status === 'draft'}
					<Button variant="outline" onclick={() => goto(`/quotes/${quote.id}?edit=true`)}>
						<FileText class="mr-2 h-4 w-4" />
						Edit
					</Button>
					<Button onclick={() => (sendDialogOpen = true)}>
						<Send class="mr-2 h-4 w-4" />
						Send Quote
					</Button>
				{/if}
				{#if quote.status === 'accepted' && !quote.invoice}
					<Button onclick={handleConvertToInvoice}>
						<Receipt class="mr-2 h-4 w-4" />
						Convert to Invoice
					</Button>
				{/if}
				<Button variant="outline" onclick={handleDownloadPdf}>
					<Download class="mr-2 h-4 w-4" />
					PDF
				</Button>
				<Button variant="outline" onclick={handleDuplicate}>
					<Copy class="mr-2 h-4 w-4" />
					Duplicate
				</Button>
				{#if quote.view_token}
					<Button
						variant="outline"
						onclick={() => window.open(`/quote/${quote.view_token}`, '_blank')}
					>
						<ExternalLink class="mr-2 h-4 w-4" />
						Public Link
					</Button>
				{/if}
			</div>
		{/if}
		{#if editMode}
			<div class="flex items-center gap-2">
				<Button variant="outline" onclick={() => goto(`/quotes/${quoteId}`)}>Cancel</Button>
				<Button onclick={handleSave} disabled={saving}>
					<Save class="mr-2 h-4 w-4" />
					{saving ? 'Saving...' : 'Save Changes'}
				</Button>
			</div>
		{/if}
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading quote...</div>
		</div>
	{:else if quote}
		<div class="grid gap-6 lg:grid-cols-3">
			<!-- Main Content -->
			<div class="lg:col-span-2 space-y-6">
				<!-- Quote Details (View Mode) -->
				{#if !editMode}
					<Card.Root>
						<Card.Header>
							<Card.Title>Quote Details</Card.Title>
						</Card.Header>
						<Card.Content>
							<dl class="grid gap-4 sm:grid-cols-2">
								<div>
									<dt class="text-sm text-muted-foreground">Valid Until</dt>
									<dd class="font-medium">{formatDate(quote.valid_until)}</dd>
								</div>
								<div>
									<dt class="text-sm text-muted-foreground">Created</dt>
									<dd class="font-medium">{formatDateTime(quote.created_at)}</dd>
								</div>
								{#if quote.sent_at}
									<div>
										<dt class="text-sm text-muted-foreground">Sent</dt>
										<dd class="font-medium">{formatDateTime(quote.sent_at)}</dd>
									</div>
								{/if}
								{#if quote.viewed_at}
									<div>
										<dt class="text-sm text-muted-foreground">Viewed</dt>
										<dd class="font-medium">
											{formatDateTime(quote.viewed_at)} ({quote.view_count} times)
										</dd>
									</div>
								{/if}
								{#if quote.accepted_at}
									<div>
										<dt class="text-sm text-muted-foreground">Accepted</dt>
										<dd class="font-medium">
											{formatDateTime(quote.accepted_at)} by {quote.accepted_by}
										</dd>
									</div>
								{/if}
								{#if quote.rejected_at}
									<div>
										<dt class="text-sm text-muted-foreground">Rejected</dt>
										<dd class="font-medium">{formatDateTime(quote.rejected_at)}</dd>
									</div>
									{#if quote.rejection_reason}
										<div class="sm:col-span-2">
											<dt class="text-sm text-muted-foreground">Rejection Reason</dt>
											<dd class="font-medium">{quote.rejection_reason}</dd>
										</div>
									{/if}
								{/if}
							</dl>
						</Card.Content>
					</Card.Root>
				{/if}

				<!-- Quote Details (Edit Mode) -->
				{#if editMode}
					<Card.Root>
						<Card.Header>
							<Card.Title>Quote Details</Card.Title>
						</Card.Header>
						<Card.Content class="space-y-4">
							<div class="grid gap-4 sm:grid-cols-2">
								<div class="space-y-2">
									<Label for="title">Title</Label>
									<Input id="title" bind:value={editTitle} placeholder="Quote title" />
								</div>
								<div class="space-y-2">
									<Label for="validUntil">Valid Until</Label>
									<Input id="validUntil" type="date" bind:value={editValidUntil} />
								</div>
							</div>
							<div class="space-y-2">
								<Label for="terms">Terms & Conditions</Label>
								<Textarea id="terms" bind:value={editTerms} rows={3} placeholder="Enter terms..." />
							</div>
							<div class="space-y-2">
								<Label for="notes">Notes</Label>
								<Textarea id="notes" bind:value={editNotes} rows={3} placeholder="Additional notes..." />
							</div>
						</Card.Content>
					</Card.Root>
				{/if}

				<!-- Line Items -->
				<Card.Root>
					<Card.Header class="flex flex-row items-center justify-between">
						<Card.Title>Line Items</Card.Title>
						{#if editMode}
							<Button variant="outline" size="sm" onclick={addLineItem}>
								<Plus class="mr-2 h-4 w-4" />
								Add Item
							</Button>
						{/if}
					</Card.Header>
					<Card.Content>
						{#if editMode}
							<div class="space-y-4">
								{#each editLineItems as item, index}
									<div class="rounded-lg border p-4">
										<div class="mb-4 flex items-center justify-between">
											<span class="font-medium">Item {index + 1}</span>
											<Button variant="ghost" size="sm" onclick={() => removeLineItem(index)}>
												<Trash2 class="h-4 w-4 text-destructive" />
											</Button>
										</div>
										<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
											<div class="sm:col-span-2 space-y-2">
												<Label>Product</Label>
												<Select.Root
													type="single"
													value={item.product_id ? String(item.product_id) : ''}
													onValueChange={(v) => selectProduct(index, v)}
												>
													<Select.Trigger>
														{item.product_id
															? products.find((p) => p.id === item.product_id)?.name ||
																'Select product'
															: 'Select product'}
													</Select.Trigger>
													<Select.Content>
														{#each products as product}
															<Select.Item value={String(product.id)}>{product.name}</Select.Item>
														{/each}
													</Select.Content>
												</Select.Root>
											</div>
											<div class="sm:col-span-2 space-y-2">
												<Label>Description</Label>
												<Input bind:value={item.description} placeholder="Description" />
											</div>
											<div class="space-y-2">
												<Label>Quantity</Label>
												<Input type="number" bind:value={item.quantity} min="0" step="0.01" />
											</div>
											<div class="space-y-2">
												<Label>Unit Price</Label>
												<Input type="number" bind:value={item.unit_price} min="0" step="0.01" />
											</div>
											<div class="space-y-2">
												<Label>Discount %</Label>
												<Input
													type="number"
													bind:value={item.discount_percent}
													min="0"
													max="100"
													step="0.01"
												/>
											</div>
											<div class="space-y-2">
												<Label>Tax %</Label>
												<Input
													type="number"
													bind:value={item.tax_rate}
													min="0"
													max="100"
													step="0.01"
												/>
											</div>
										</div>
									</div>
								{/each}
								{#if editLineItems.length === 0}
									<div class="py-8 text-center text-muted-foreground">
										No line items. Click "Add Item" to add one.
									</div>
								{/if}
							</div>
						{:else}
							<table class="w-full">
								<thead class="border-b">
									<tr>
										<th class="py-2 text-left text-sm font-medium">Description</th>
										<th class="py-2 text-right text-sm font-medium">Qty</th>
										<th class="py-2 text-right text-sm font-medium">Unit Price</th>
										<th class="py-2 text-right text-sm font-medium">Discount</th>
										<th class="py-2 text-right text-sm font-medium">Tax</th>
										<th class="py-2 text-right text-sm font-medium">Total</th>
									</tr>
								</thead>
								<tbody class="divide-y">
									{#each quote.lineItems || [] as item}
										<tr>
											<td class="py-3">{item.description}</td>
											<td class="py-3 text-right">{item.quantity}</td>
											<td class="py-3 text-right">{formatCurrency(item.unit_price, quote.currency)}</td>
											<td class="py-3 text-right">
												{item.discount_percent > 0 ? `${item.discount_percent}%` : '-'}
											</td>
											<td class="py-3 text-right">{item.tax_rate > 0 ? `${item.tax_rate}%` : '-'}</td>
											<td class="py-3 text-right font-medium">
												{formatCurrency(item.line_total, quote.currency)}
											</td>
										</tr>
									{/each}
								</tbody>
							</table>
						{/if}
					</Card.Content>
				</Card.Root>

				<!-- Terms & Notes (View Mode) -->
				{#if !editMode && (quote.terms || quote.notes)}
					<Card.Root>
						<Card.Header>
							<Card.Title>Terms & Notes</Card.Title>
						</Card.Header>
						<Card.Content class="space-y-4">
							{#if quote.terms}
								<div>
									<h4 class="mb-2 font-medium">Terms & Conditions</h4>
									<p class="whitespace-pre-wrap text-muted-foreground">{quote.terms}</p>
								</div>
							{/if}
							{#if quote.notes}
								<div>
									<h4 class="mb-2 font-medium">Notes</h4>
									<p class="whitespace-pre-wrap text-muted-foreground">{quote.notes}</p>
								</div>
							{/if}
						</Card.Content>
					</Card.Root>
				{/if}
			</div>

			<!-- Sidebar -->
			<div class="space-y-6">
				<!-- Totals -->
				<Card.Root>
					<Card.Header>
						<Card.Title>Summary</Card.Title>
					</Card.Header>
					<Card.Content>
						<dl class="space-y-3">
							<div class="flex justify-between">
								<dt class="text-muted-foreground">Subtotal</dt>
								<dd class="font-medium">
									{formatCurrency(editMode ? calculatedSubtotal : quote.subtotal, quote.currency)}
								</dd>
							</div>
							{#if quote.discount_amount > 0}
								<div class="flex justify-between text-green-600">
									<dt>Discount</dt>
									<dd>-{formatCurrency(quote.discount_amount, quote.currency)}</dd>
								</div>
							{/if}
							<div class="flex justify-between">
								<dt class="text-muted-foreground">Tax</dt>
								<dd>
									{formatCurrency(editMode ? calculatedTax : quote.tax_amount, quote.currency)}
								</dd>
							</div>
							<div class="flex justify-between border-t pt-3 text-lg font-bold">
								<dt>Total</dt>
								<dd>
									{formatCurrency(editMode ? calculatedTotal : quote.total, quote.currency)}
								</dd>
							</div>
						</dl>
					</Card.Content>
				</Card.Root>

				<!-- Version History -->
				{#if quote.versions && quote.versions.length > 0}
					<Card.Root>
						<Card.Header>
							<Card.Title>Version History</Card.Title>
						</Card.Header>
						<Card.Content>
							<ul class="space-y-2">
								{#each quote.versions as version}
									<li class="flex items-center justify-between text-sm">
										<span>Version {version.version_number}</span>
										<span class="text-muted-foreground">{formatDate(version.created_at)}</span>
									</li>
								{/each}
							</ul>
						</Card.Content>
					</Card.Root>
				{/if}

				<!-- Invoice Link -->
				{#if quote.invoice}
					<Card.Root>
						<Card.Header>
							<Card.Title>Linked Invoice</Card.Title>
						</Card.Header>
						<Card.Content>
							<Button variant="outline" class="w-full" onclick={() => goto(`/invoices/${quote.invoice?.id}`)}>
								<Receipt class="mr-2 h-4 w-4" />
								{quote.invoice.invoice_number}
							</Button>
						</Card.Content>
					</Card.Root>
				{/if}
			</div>
		</div>
	{/if}
</div>

<!-- Send Quote Dialog -->
<Dialog.Root bind:open={sendDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Send Quote</Dialog.Title>
			<Dialog.Description>
				Send this quote to the recipient via email. They will receive a link to view and accept or
				reject the quote.
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="sendEmail">Recipient Email</Label>
				<Input
					id="sendEmail"
					type="email"
					bind:value={sendEmail}
					placeholder="recipient@example.com"
				/>
			</div>
			<div class="space-y-2">
				<Label for="sendMessage">Personal Message (optional)</Label>
				<Textarea
					id="sendMessage"
					bind:value={sendMessage}
					rows={3}
					placeholder="Add a personal message..."
				/>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (sendDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleSend} disabled={!sendEmail || sending}>
				<Send class="mr-2 h-4 w-4" />
				{sending ? 'Sending...' : 'Send Quote'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
