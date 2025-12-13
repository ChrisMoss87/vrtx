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
		FileText,
		Eye,
		CheckCircle,
		XCircle,
		Clock,
		CreditCard,
		AlertTriangle,
		Ban,
		Plus,
		Trash2,
		Download,
		Save,
		Receipt
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getInvoice,
		updateInvoice,
		sendInvoice,
		cancelInvoice,
		getInvoicePdf,
		recordPayment,
		deletePayment,
		getProducts,
		type Invoice,
		type InvoiceStatus,
		type InvoiceLineItemInput,
		type InvoicePaymentInput,
		type Product
	} from '$lib/api/billing';

	const invoiceId = $derived(Number($page.params.id));
	const editMode = $derived($page.url.searchParams.get('edit') === 'true');
	const paymentMode = $derived($page.url.searchParams.get('payment') === 'true');

	let invoice = $state<Invoice | null>(null);
	let products = $state<Product[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let sendDialogOpen = $state(false);
	let sendEmail = $state('');
	let sendMessage = $state('');
	let sending = $state(false);
	let paymentDialogOpen = $state(paymentMode);
	let paymentAmount = $state(0);
	let paymentDate = $state(new Date().toISOString().split('T')[0]);
	let paymentMethod = $state('');
	let paymentReference = $state('');
	let paymentNotes = $state('');
	let recordingPayment = $state(false);

	// Edit state
	let editTitle = $state('');
	let editDueDate = $state('');
	let editPaymentTerms = $state('');
	let editNotes = $state('');
	let editLineItems = $state<InvoiceLineItemInput[]>([]);

	const statusColors: Record<InvoiceStatus, string> = {
		draft: 'bg-gray-100 text-gray-800',
		sent: 'bg-blue-100 text-blue-800',
		viewed: 'bg-purple-100 text-purple-800',
		paid: 'bg-green-100 text-green-800',
		partial: 'bg-yellow-100 text-yellow-800',
		overdue: 'bg-red-100 text-red-800',
		cancelled: 'bg-gray-200 text-gray-600'
	};

	const statusIcons: Record<InvoiceStatus, typeof FileText> = {
		draft: FileText,
		sent: Send,
		viewed: Eye,
		paid: CheckCircle,
		partial: CreditCard,
		overdue: AlertTriangle,
		cancelled: Ban
	};

	onMount(async () => {
		await loadInvoice();
	});

	async function loadInvoice() {
		loading = true;
		try {
			const [invoiceData, productsData] = await Promise.all([
				getInvoice(invoiceId),
				getProducts({ active_only: true })
			]);
			invoice = invoiceData;
			products = productsData;

			// Populate edit state
			editTitle = invoice.title || '';
			editDueDate = invoice.due_date?.split('T')[0] || '';
			editPaymentTerms = invoice.payment_terms || '';
			editNotes = invoice.notes || '';
			editLineItems =
				invoice.lineItems?.map((item) => ({
					product_id: item.product_id,
					description: item.description,
					quantity: item.quantity,
					unit_price: item.unit_price,
					discount_percent: item.discount_percent,
					tax_rate: item.tax_rate
				})) || [];

			// Pre-fill payment amount with balance due
			paymentAmount = invoice.balance_due;
		} catch (error) {
			console.error('Failed to load invoice:', error);
			toast.error('Failed to load invoice');
		} finally {
			loading = false;
		}
	}

	async function handleSave() {
		if (!invoice) return;
		saving = true;
		try {
			const updated = await updateInvoice(invoice.id, {
				title: editTitle || undefined,
				due_date: editDueDate || undefined,
				payment_terms: editPaymentTerms || undefined,
				notes: editNotes || undefined,
				line_items: editLineItems
			});
			invoice = updated;
			toast.success('Invoice updated');
			goto(`/invoices/${invoice.id}`);
		} catch (error) {
			console.error('Failed to update invoice:', error);
			toast.error('Failed to update invoice');
		} finally {
			saving = false;
		}
	}

	async function handleSend() {
		if (!invoice || !sendEmail) return;
		sending = true;
		try {
			const updated = await sendInvoice(invoice.id, {
				to_email: sendEmail,
				message: sendMessage || undefined
			});
			invoice = updated;
			toast.success('Invoice sent successfully');
			sendDialogOpen = false;
			sendEmail = '';
			sendMessage = '';
		} catch (error) {
			console.error('Failed to send invoice:', error);
			toast.error('Failed to send invoice');
		} finally {
			sending = false;
		}
	}

	async function handleCancel() {
		if (!invoice) return;
		try {
			const updated = await cancelInvoice(invoice.id);
			invoice = updated;
			toast.success('Invoice cancelled');
		} catch (error) {
			console.error('Failed to cancel invoice:', error);
			toast.error('Failed to cancel invoice');
		}
	}

	async function handleDownloadPdf() {
		if (!invoice) return;
		try {
			const pdfData = await getInvoicePdf(invoice.id);
			console.log('PDF Data:', pdfData);
			toast.success('PDF generation ready (check console for data)');
		} catch (error) {
			console.error('Failed to generate PDF:', error);
			toast.error('Failed to generate PDF');
		}
	}

	async function handleRecordPayment() {
		if (!invoice || paymentAmount <= 0) return;
		recordingPayment = true;
		try {
			const paymentData: InvoicePaymentInput = {
				amount: paymentAmount,
				payment_date: paymentDate,
				payment_method: paymentMethod || undefined,
				reference: paymentReference || undefined,
				notes: paymentNotes || undefined
			};
			await recordPayment(invoice.id, paymentData);
			toast.success('Payment recorded');
			paymentDialogOpen = false;
			// Reset form
			paymentAmount = 0;
			paymentMethod = '';
			paymentReference = '';
			paymentNotes = '';
			// Reload invoice
			await loadInvoice();
		} catch (error) {
			console.error('Failed to record payment:', error);
			toast.error('Failed to record payment');
		} finally {
			recordingPayment = false;
		}
	}

	async function handleDeletePayment(paymentId: number) {
		if (!invoice) return;
		try {
			await deletePayment(invoice.id, paymentId);
			toast.success('Payment deleted');
			await loadInvoice();
		} catch (error) {
			console.error('Failed to delete payment:', error);
			toast.error('Failed to delete payment');
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
	<title>{invoice?.invoice_number || 'Invoice'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/invoices')}>
				<ArrowLeft class="h-5 w-5" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">{invoice?.invoice_number || 'Loading...'}</h1>
				{#if invoice?.title}
					<p class="text-muted-foreground">{invoice.title}</p>
				{/if}
			</div>
			{#if invoice}
				{@const StatusIcon = statusIcons[invoice.status]}
				<Badge class={statusColors[invoice.status]}>
					<StatusIcon class="mr-1 h-3 w-3" />
					{invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}
				</Badge>
			{/if}
		</div>
		{#if invoice && !editMode}
			<div class="flex items-center gap-2">
				{#if invoice.status === 'draft'}
					<Button variant="outline" onclick={() => goto(`/invoices/${invoice.id}?edit=true`)}>
						<FileText class="mr-2 h-4 w-4" />
						Edit
					</Button>
					<Button onclick={() => (sendDialogOpen = true)}>
						<Send class="mr-2 h-4 w-4" />
						Send Invoice
					</Button>
				{/if}
				{#if invoice.status !== 'paid' && invoice.status !== 'cancelled'}
					<Button variant="outline" onclick={() => (paymentDialogOpen = true)}>
						<CreditCard class="mr-2 h-4 w-4" />
						Record Payment
					</Button>
				{/if}
				<Button variant="outline" onclick={handleDownloadPdf}>
					<Download class="mr-2 h-4 w-4" />
					PDF
				</Button>
				{#if invoice.status !== 'cancelled' && invoice.status !== 'paid'}
					<Button variant="outline" class="text-orange-600" onclick={handleCancel}>
						<XCircle class="mr-2 h-4 w-4" />
						Cancel
					</Button>
				{/if}
			</div>
		{/if}
		{#if editMode}
			<div class="flex items-center gap-2">
				<Button variant="outline" onclick={() => goto(`/invoices/${invoiceId}`)}>Cancel</Button>
				<Button onclick={handleSave} disabled={saving}>
					<Save class="mr-2 h-4 w-4" />
					{saving ? 'Saving...' : 'Save Changes'}
				</Button>
			</div>
		{/if}
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading invoice...</div>
		</div>
	{:else if invoice}
		<div class="grid gap-6 lg:grid-cols-3">
			<!-- Main Content -->
			<div class="lg:col-span-2 space-y-6">
				<!-- Invoice Details (View Mode) -->
				{#if !editMode}
					<Card.Root>
						<Card.Header>
							<Card.Title>Invoice Details</Card.Title>
						</Card.Header>
						<Card.Content>
							<dl class="grid gap-4 sm:grid-cols-2">
								<div>
									<dt class="text-sm text-muted-foreground">Issue Date</dt>
									<dd class="font-medium">{formatDate(invoice.issue_date)}</dd>
								</div>
								<div>
									<dt class="text-sm text-muted-foreground">Due Date</dt>
									<dd class="font-medium">{formatDate(invoice.due_date)}</dd>
								</div>
								{#if invoice.payment_terms}
									<div>
										<dt class="text-sm text-muted-foreground">Payment Terms</dt>
										<dd class="font-medium">{invoice.payment_terms}</dd>
									</div>
								{/if}
								{#if invoice.sent_at}
									<div>
										<dt class="text-sm text-muted-foreground">Sent</dt>
										<dd class="font-medium">{formatDateTime(invoice.sent_at)}</dd>
									</div>
								{/if}
								{#if invoice.quote}
									<div>
										<dt class="text-sm text-muted-foreground">From Quote</dt>
										<dd>
											<a href="/quotes/{invoice.quote.id}" class="font-medium text-primary hover:underline">
												{invoice.quote.quote_number}
											</a>
										</dd>
									</div>
								{/if}
							</dl>
						</Card.Content>
					</Card.Root>
				{/if}

				<!-- Invoice Details (Edit Mode) -->
				{#if editMode}
					<Card.Root>
						<Card.Header>
							<Card.Title>Invoice Details</Card.Title>
						</Card.Header>
						<Card.Content class="space-y-4">
							<div class="grid gap-4 sm:grid-cols-2">
								<div class="space-y-2">
									<Label for="title">Title</Label>
									<Input id="title" bind:value={editTitle} placeholder="Invoice title" />
								</div>
								<div class="space-y-2">
									<Label for="dueDate">Due Date</Label>
									<Input id="dueDate" type="date" bind:value={editDueDate} />
								</div>
							</div>
							<div class="space-y-2">
								<Label for="paymentTerms">Payment Terms</Label>
								<Input
									id="paymentTerms"
									bind:value={editPaymentTerms}
									placeholder="e.g., Net 30"
								/>
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
									{#each invoice.lineItems || [] as item}
										<tr>
											<td class="py-3">{item.description}</td>
											<td class="py-3 text-right">{item.quantity}</td>
											<td class="py-3 text-right">{formatCurrency(item.unit_price, invoice.currency)}</td>
											<td class="py-3 text-right">
												{item.discount_percent > 0 ? `${item.discount_percent}%` : '-'}
											</td>
											<td class="py-3 text-right">{item.tax_rate > 0 ? `${item.tax_rate}%` : '-'}</td>
											<td class="py-3 text-right font-medium">
												{formatCurrency(item.line_total, invoice.currency)}
											</td>
										</tr>
									{/each}
								</tbody>
							</table>
						{/if}
					</Card.Content>
				</Card.Root>

				<!-- Payments -->
				{#if !editMode && invoice.payments && invoice.payments.length > 0}
					<Card.Root>
						<Card.Header>
							<Card.Title>Payment History</Card.Title>
						</Card.Header>
						<Card.Content>
							<table class="w-full">
								<thead class="border-b">
									<tr>
										<th class="py-2 text-left text-sm font-medium">Date</th>
										<th class="py-2 text-left text-sm font-medium">Method</th>
										<th class="py-2 text-left text-sm font-medium">Reference</th>
										<th class="py-2 text-right text-sm font-medium">Amount</th>
										<th class="py-2 text-right text-sm font-medium"></th>
									</tr>
								</thead>
								<tbody class="divide-y">
									{#each invoice.payments as payment}
										<tr>
											<td class="py-3">{formatDate(payment.payment_date)}</td>
											<td class="py-3">{payment.payment_method || '-'}</td>
											<td class="py-3">{payment.reference || '-'}</td>
											<td class="py-3 text-right font-medium text-green-600">
												+{formatCurrency(payment.amount, invoice.currency)}
											</td>
											<td class="py-3 text-right">
												<Button
													variant="ghost"
													size="sm"
													onclick={() => handleDeletePayment(payment.id)}
												>
													<Trash2 class="h-4 w-4 text-destructive" />
												</Button>
											</td>
										</tr>
									{/each}
								</tbody>
							</table>
						</Card.Content>
					</Card.Root>
				{/if}

				<!-- Notes (View Mode) -->
				{#if !editMode && invoice.notes}
					<Card.Root>
						<Card.Header>
							<Card.Title>Notes</Card.Title>
						</Card.Header>
						<Card.Content>
							<p class="whitespace-pre-wrap text-muted-foreground">{invoice.notes}</p>
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
									{formatCurrency(editMode ? calculatedSubtotal : invoice.subtotal, invoice.currency)}
								</dd>
							</div>
							{#if invoice.discount_amount > 0}
								<div class="flex justify-between text-green-600">
									<dt>Discount</dt>
									<dd>-{formatCurrency(invoice.discount_amount, invoice.currency)}</dd>
								</div>
							{/if}
							<div class="flex justify-between">
								<dt class="text-muted-foreground">Tax</dt>
								<dd>
									{formatCurrency(editMode ? calculatedTax : invoice.tax_amount, invoice.currency)}
								</dd>
							</div>
							<div class="flex justify-between border-t pt-3 text-lg font-bold">
								<dt>Total</dt>
								<dd>
									{formatCurrency(editMode ? calculatedTotal : invoice.total, invoice.currency)}
								</dd>
							</div>
							{#if !editMode}
								<div class="flex justify-between text-green-600">
									<dt>Amount Paid</dt>
									<dd>{formatCurrency(invoice.amount_paid, invoice.currency)}</dd>
								</div>
								<div
									class="flex justify-between text-lg font-bold"
									class:text-red-600={invoice.balance_due > 0}
								>
									<dt>Balance Due</dt>
									<dd>{formatCurrency(invoice.balance_due, invoice.currency)}</dd>
								</div>
							{/if}
						</dl>
					</Card.Content>
				</Card.Root>
			</div>
		</div>
	{/if}
</div>

<!-- Send Invoice Dialog -->
<Dialog.Root bind:open={sendDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Send Invoice</Dialog.Title>
			<Dialog.Description>Send this invoice to the recipient via email.</Dialog.Description>
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
				{sending ? 'Sending...' : 'Send Invoice'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Record Payment Dialog -->
<Dialog.Root bind:open={paymentDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Record Payment</Dialog.Title>
			<Dialog.Description>Record a payment received for this invoice.</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="paymentAmount">Amount</Label>
				<Input id="paymentAmount" type="number" bind:value={paymentAmount} min="0" step="0.01" />
			</div>
			<div class="space-y-2">
				<Label for="paymentDate">Payment Date</Label>
				<Input id="paymentDate" type="date" bind:value={paymentDate} />
			</div>
			<div class="space-y-2">
				<Label for="paymentMethod">Payment Method</Label>
				<Select.Root type="single" bind:value={paymentMethod}>
					<Select.Trigger>
						{paymentMethod || 'Select method'}
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="bank_transfer">Bank Transfer</Select.Item>
						<Select.Item value="credit_card">Credit Card</Select.Item>
						<Select.Item value="check">Check</Select.Item>
						<Select.Item value="cash">Cash</Select.Item>
						<Select.Item value="other">Other</Select.Item>
					</Select.Content>
				</Select.Root>
			</div>
			<div class="space-y-2">
				<Label for="paymentReference">Reference (optional)</Label>
				<Input id="paymentReference" bind:value={paymentReference} placeholder="Transaction ID, check number, etc." />
			</div>
			<div class="space-y-2">
				<Label for="paymentNotes">Notes (optional)</Label>
				<Textarea id="paymentNotes" bind:value={paymentNotes} rows={2} placeholder="Additional notes..." />
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (paymentDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleRecordPayment} disabled={paymentAmount <= 0 || recordingPayment}>
				<CreditCard class="mr-2 h-4 w-4" />
				{recordingPayment ? 'Recording...' : 'Record Payment'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
