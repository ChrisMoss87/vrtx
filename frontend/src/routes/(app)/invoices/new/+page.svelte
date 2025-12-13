<script lang="ts">
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { ArrowLeft, Plus, Trash2, Save } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		createInvoice,
		getProducts,
		type InvoiceInput,
		type InvoiceLineItemInput,
		type Product
	} from '$lib/api/billing';

	let products = $state<Product[]>([]);
	let loading = $state(true);
	let saving = $state(false);

	// Form state
	let title = $state('');
	let issueDate = $state(new Date().toISOString().split('T')[0]);
	let dueDate = $state('');
	let currency = $state('USD');
	let paymentTerms = $state('Net 30');
	let notes = $state('');
	let discountAmount = $state(0);
	let lineItems = $state<InvoiceLineItemInput[]>([
		{
			description: '',
			quantity: 1,
			unit_price: 0,
			discount_percent: 0,
			tax_rate: 0
		}
	]);

	onMount(async () => {
		try {
			const productsData = await getProducts({ active_only: true });
			products = productsData;

			// Set default due date (30 days from now)
			const defaultDueDate = new Date();
			defaultDueDate.setDate(defaultDueDate.getDate() + 30);
			dueDate = defaultDueDate.toISOString().split('T')[0];
		} catch (error) {
			console.error('Failed to load products:', error);
			toast.error('Failed to load products');
		} finally {
			loading = false;
		}
	});

	async function handleSubmit() {
		if (lineItems.length === 0 || !lineItems.some((item) => item.description)) {
			toast.error('Please add at least one line item');
			return;
		}

		saving = true;
		try {
			const data: InvoiceInput = {
				title: title || undefined,
				issue_date: issueDate,
				due_date: dueDate,
				currency,
				payment_terms: paymentTerms || undefined,
				notes: notes || undefined,
				discount_amount: discountAmount > 0 ? discountAmount : undefined,
				line_items: lineItems.filter((item) => item.description)
			};

			const invoice = await createInvoice(data);
			toast.success('Invoice created successfully');
			goto(`/invoices/${invoice.id}`);
		} catch (error) {
			console.error('Failed to create invoice:', error);
			toast.error('Failed to create invoice');
		} finally {
			saving = false;
		}
	}

	function addLineItem() {
		lineItems = [
			...lineItems,
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
		lineItems = lineItems.filter((_, i) => i !== index);
	}

	function selectProduct(index: number, productId: string) {
		const product = products.find((p) => String(p.id) === productId);
		if (product) {
			lineItems[index] = {
				...lineItems[index],
				product_id: product.id,
				description: product.description || product.name,
				unit_price: product.unit_price,
				tax_rate: product.tax_rate || 0
			};
		}
	}

	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency
		}).format(amount);
	}

	const calculatedSubtotal = $derived(
		lineItems.reduce((sum, item) => {
			const lineTotal = (item.quantity || 1) * (item.unit_price || 0);
			const discount = lineTotal * ((item.discount_percent || 0) / 100);
			return sum + (lineTotal - discount);
		}, 0)
	);

	const calculatedTax = $derived(
		lineItems.reduce((sum, item) => {
			const lineTotal = (item.quantity || 1) * (item.unit_price || 0);
			const discount = lineTotal * ((item.discount_percent || 0) / 100);
			const taxableAmount = lineTotal - discount;
			return sum + taxableAmount * ((item.tax_rate || 0) / 100);
		}, 0)
	);

	const calculatedTotal = $derived(calculatedSubtotal - discountAmount + calculatedTax);
</script>

<svelte:head>
	<title>New Invoice | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/invoices')}>
				<ArrowLeft class="h-5 w-5" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">New Invoice</h1>
				<p class="text-muted-foreground">Create a new invoice</p>
			</div>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" onclick={() => goto('/invoices')}>Cancel</Button>
			<Button onclick={handleSubmit} disabled={saving || loading}>
				<Save class="mr-2 h-4 w-4" />
				{saving ? 'Creating...' : 'Create Invoice'}
			</Button>
		</div>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading...</div>
		</div>
	{:else}
		<div class="grid gap-6 lg:grid-cols-3">
			<!-- Main Content -->
			<div class="lg:col-span-2 space-y-6">
				<!-- Invoice Details -->
				<Card.Root>
					<Card.Header>
						<Card.Title>Invoice Details</Card.Title>
					</Card.Header>
					<Card.Content class="space-y-4">
						<div class="grid gap-4 sm:grid-cols-2">
							<div class="space-y-2">
								<Label for="title">Title</Label>
								<Input id="title" bind:value={title} placeholder="Invoice title (optional)" />
							</div>
							<div class="space-y-2">
								<Label for="currency">Currency</Label>
								<Select.Root type="single" bind:value={currency}>
									<Select.Trigger>
										{currency}
									</Select.Trigger>
									<Select.Content>
										<Select.Item value="USD">USD - US Dollar</Select.Item>
										<Select.Item value="EUR">EUR - Euro</Select.Item>
										<Select.Item value="GBP">GBP - British Pound</Select.Item>
										<Select.Item value="AUD">AUD - Australian Dollar</Select.Item>
										<Select.Item value="CAD">CAD - Canadian Dollar</Select.Item>
									</Select.Content>
								</Select.Root>
							</div>
							<div class="space-y-2">
								<Label for="issueDate">Issue Date</Label>
								<Input id="issueDate" type="date" bind:value={issueDate} />
							</div>
							<div class="space-y-2">
								<Label for="dueDate">Due Date</Label>
								<Input id="dueDate" type="date" bind:value={dueDate} />
							</div>
							<div class="space-y-2">
								<Label for="paymentTerms">Payment Terms</Label>
								<Select.Root type="single" bind:value={paymentTerms}>
									<Select.Trigger>
										{paymentTerms || 'Select terms'}
									</Select.Trigger>
									<Select.Content>
										<Select.Item value="Due on receipt">Due on Receipt</Select.Item>
										<Select.Item value="Net 15">Net 15</Select.Item>
										<Select.Item value="Net 30">Net 30</Select.Item>
										<Select.Item value="Net 45">Net 45</Select.Item>
										<Select.Item value="Net 60">Net 60</Select.Item>
									</Select.Content>
								</Select.Root>
							</div>
						</div>
						<div class="space-y-2">
							<Label for="notes">Notes</Label>
							<Textarea id="notes" bind:value={notes} rows={3} placeholder="Additional notes..." />
						</div>
					</Card.Content>
				</Card.Root>

				<!-- Line Items -->
				<Card.Root>
					<Card.Header class="flex flex-row items-center justify-between">
						<Card.Title>Line Items</Card.Title>
						<Button variant="outline" size="sm" onclick={addLineItem}>
							<Plus class="mr-2 h-4 w-4" />
							Add Item
						</Button>
					</Card.Header>
					<Card.Content>
						<div class="space-y-4">
							{#each lineItems as item, index}
								<div class="rounded-lg border p-4">
									<div class="mb-4 flex items-center justify-between">
										<span class="font-medium">Item {index + 1}</span>
										{#if lineItems.length > 1}
											<Button variant="ghost" size="sm" onclick={() => removeLineItem(index)}>
												<Trash2 class="h-4 w-4 text-destructive" />
											</Button>
										{/if}
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
														? products.find((p) => p.id === item.product_id)?.name || 'Select product'
														: 'Select product (optional)'}
												</Select.Trigger>
												<Select.Content>
													{#each products as product}
														<Select.Item value={String(product.id)}>
															{product.name} - {formatCurrency(product.unit_price)}
														</Select.Item>
													{/each}
												</Select.Content>
											</Select.Root>
										</div>
										<div class="sm:col-span-2 space-y-2">
											<Label>Description</Label>
											<Input bind:value={item.description} placeholder="Item description" />
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
											<Input type="number" bind:value={item.tax_rate} min="0" max="100" step="0.01" />
										</div>
									</div>
								</div>
							{/each}
						</div>
					</Card.Content>
				</Card.Root>
			</div>

			<!-- Sidebar -->
			<div class="space-y-6">
				<!-- Invoice Level Discount -->
				<Card.Root>
					<Card.Header>
						<Card.Title>Invoice Discount</Card.Title>
					</Card.Header>
					<Card.Content>
						<div class="space-y-2">
							<Label>Discount Amount</Label>
							<Input type="number" bind:value={discountAmount} min="0" step="0.01" />
						</div>
					</Card.Content>
				</Card.Root>

				<!-- Totals -->
				<Card.Root>
					<Card.Header>
						<Card.Title>Summary</Card.Title>
					</Card.Header>
					<Card.Content>
						<dl class="space-y-3">
							<div class="flex justify-between">
								<dt class="text-muted-foreground">Subtotal</dt>
								<dd class="font-medium">{formatCurrency(calculatedSubtotal)}</dd>
							</div>
							{#if discountAmount > 0}
								<div class="flex justify-between text-green-600">
									<dt>Discount</dt>
									<dd>-{formatCurrency(discountAmount)}</dd>
								</div>
							{/if}
							<div class="flex justify-between">
								<dt class="text-muted-foreground">Tax</dt>
								<dd>{formatCurrency(calculatedTax)}</dd>
							</div>
							<div class="flex justify-between border-t pt-3 text-lg font-bold">
								<dt>Total</dt>
								<dd>{formatCurrency(calculatedTotal)}</dd>
							</div>
						</dl>
					</Card.Content>
				</Card.Root>
			</div>
		</div>
	{/if}
</div>
