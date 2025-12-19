<script lang="ts">
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { ArrowLeft, Save } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		createQuote,
		getProducts,
		getQuoteTemplates,
		getTaxRates,
		type QuoteInput,
		type Product,
		type QuoteTemplate,
		type TaxRate
	} from '$lib/api/billing';
	import LineItemsEditor, { type LineItem } from '$lib/components/billing/LineItemsEditor.svelte';

	let products = $state<Product[]>([]);
	let templates = $state<QuoteTemplate[]>([]);
	let taxRates = $state<TaxRate[]>([]);
	let loading = $state(true);
	let saving = $state(false);

	// Form state
	let title = $state('');
	let validUntil = $state('');
	let currency = $state('USD');
	let terms = $state('');
	let notes = $state('');
	let templateId = $state<string>('');
	let discountType = $state<'fixed' | 'percent'>('percent');
	let discountAmount = $state(0);
	let discountPercent = $state(0);
	let lineItems = $state<LineItem[]>([]);

	onMount(async () => {
		try {
			const [productsData, templatesData, taxRatesData] = await Promise.all([
				getProducts({ active_only: true }),
				getQuoteTemplates(),
				getTaxRates({ active_only: true }).catch(() => [])
			]);
			products = productsData;
			templates = templatesData;
			taxRates = taxRatesData;

			// Set default valid until (30 days from now)
			const defaultDate = new Date();
			defaultDate.setDate(defaultDate.getDate() + 30);
			validUntil = defaultDate.toISOString().split('T')[0];

			// Set default template if available
			const defaultTemplate = templates.find((t) => t.is_default);
			if (defaultTemplate) {
				templateId = String(defaultTemplate.id);
			}

			// Initialize with one empty line item
			const defaultTax = taxRates.find((t) => t.is_default);
			lineItems = [
				{
					id: generateId(),
					item_type: 'product',
					product_id: null,
					sku: null,
					description: '',
					detailed_description: null,
					quantity: 1,
					unit: null,
					unit_price: 0,
					discount_type: 'none',
					discount_value: 0,
					tax_rate_id: defaultTax?.id ?? null,
					tax_rate: defaultTax?.rate ?? 0
				}
			];
		} catch (error) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load data');
		} finally {
			loading = false;
		}
	});

	function generateId(): string {
		return Math.random().toString(36).substring(2, 11);
	}

	async function handleSubmit() {
		const validItems = lineItems.filter(
			(item) => item.item_type === 'text' || (item.description && item.unit_price > 0)
		);

		if (validItems.length === 0) {
			toast.error('Please add at least one line item');
			return;
		}

		saving = true;
		try {
			const data: QuoteInput = {
				title: title || undefined,
				valid_until: validUntil || undefined,
				currency,
				terms: terms || undefined,
				notes: notes || undefined,
				template_id: templateId ? Number(templateId) : undefined,
				discount_type: discountType,
				discount_amount: discountType === 'fixed' ? discountAmount : undefined,
				discount_percent: discountType === 'percent' ? discountPercent : undefined,
				line_items: validItems.map((item) => ({
					product_id: item.product_id,
					sku: item.sku,
					description: item.description,
					detailed_description: item.detailed_description,
					item_type: item.item_type,
					quantity: item.quantity,
					unit: item.unit,
					unit_price: item.unit_price,
					discount_type: item.discount_type,
					discount_value: item.discount_value,
					tax_rate_id: item.tax_rate_id,
					tax_rate: item.tax_rate
				}))
			};

			const quote = await createQuote(data);
			toast.success('Quote created successfully');
			goto(`/quotes/${quote.id}`);
		} catch (error) {
			console.error('Failed to create quote:', error);
			toast.error('Failed to create quote');
		} finally {
			saving = false;
		}
	}

	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency
		}).format(amount);
	}

	// Calculate totals from line items
	const calculatedSubtotal = $derived(
		lineItems.reduce((sum, item) => {
			if (item.item_type === 'text') return sum;
			const lineTotal = (item.quantity || 1) * (item.unit_price || 0);
			let discount = 0;
			if (item.discount_type === 'percent') {
				discount = lineTotal * ((item.discount_value || 0) / 100);
			} else if (item.discount_type === 'fixed') {
				discount = item.discount_value || 0;
			}
			return sum + (lineTotal - discount);
		}, 0)
	);

	const calculatedDiscount = $derived(
		discountType === 'fixed' ? discountAmount : calculatedSubtotal * (discountPercent / 100)
	);

	const calculatedTax = $derived(
		lineItems.reduce((sum, item) => {
			if (item.item_type === 'text') return sum;
			const lineTotal = (item.quantity || 1) * (item.unit_price || 0);
			let discount = 0;
			if (item.discount_type === 'percent') {
				discount = lineTotal * ((item.discount_value || 0) / 100);
			} else if (item.discount_type === 'fixed') {
				discount = item.discount_value || 0;
			}
			const taxableAmount = lineTotal - discount;
			return sum + taxableAmount * ((item.tax_rate || 0) / 100);
		}, 0)
	);

	const calculatedTotal = $derived(calculatedSubtotal - calculatedDiscount + calculatedTax);
</script>

<svelte:head>
	<title>New Quote | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/quotes')}>
				<ArrowLeft class="h-5 w-5" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">New Quote</h1>
				<p class="text-muted-foreground">Create a new sales quote</p>
			</div>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" onclick={() => goto('/quotes')}>Cancel</Button>
			<Button onclick={handleSubmit} disabled={saving || loading}>
				<Save class="mr-2 h-4 w-4" />
				{saving ? 'Creating...' : 'Create Quote'}
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
			<div class="space-y-6 lg:col-span-2">
				<!-- Quote Details -->
				<Card.Root>
					<Card.Header>
						<Card.Title>Quote Details</Card.Title>
					</Card.Header>
					<Card.Content class="space-y-4">
						<div class="grid gap-4 sm:grid-cols-2">
							<div class="space-y-2">
								<Label for="title">Title</Label>
								<Input id="title" bind:value={title} placeholder="Quote title (optional)" />
							</div>
							<div class="space-y-2">
								<Label for="validUntil">Valid Until</Label>
								<Input id="validUntil" type="date" bind:value={validUntil} />
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
								<Label for="template">Template</Label>
								<Select.Root type="single" bind:value={templateId}>
									<Select.Trigger>
										{templateId
											? templates.find((t) => String(t.id) === templateId)?.name || 'Select template'
											: 'No template'}
									</Select.Trigger>
									<Select.Content>
										<Select.Item value="">No template</Select.Item>
										{#each templates as template}
											<Select.Item value={String(template.id)}>
												{template.name}
												{template.is_default ? '(Default)' : ''}
											</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>
						</div>
						<div class="space-y-2">
							<Label for="terms">Terms & Conditions</Label>
							<Textarea id="terms" bind:value={terms} rows={3} placeholder="Enter terms..." />
						</div>
						<div class="space-y-2">
							<Label for="notes">Notes</Label>
							<Textarea id="notes" bind:value={notes} rows={3} placeholder="Additional notes..." />
						</div>
					</Card.Content>
				</Card.Root>

				<!-- Line Items -->
				<Card.Root>
					<Card.Header>
						<Card.Title>Line Items</Card.Title>
						<Card.Description>
							Add products and services to your quote. Drag items to reorder.
						</Card.Description>
					</Card.Header>
					<Card.Content>
						<LineItemsEditor
							bind:items={lineItems}
							{products}
							{taxRates}
							{currency}
						/>
					</Card.Content>
				</Card.Root>
			</div>

			<!-- Sidebar -->
			<div class="space-y-6">
				<!-- Quote Level Discount -->
				<Card.Root>
					<Card.Header>
						<Card.Title>Quote Discount</Card.Title>
					</Card.Header>
					<Card.Content class="space-y-4">
						<div class="space-y-2">
							<Label>Discount Type</Label>
							<Select.Root type="single" bind:value={discountType}>
								<Select.Trigger>
									{discountType === 'fixed' ? 'Fixed Amount' : 'Percentage'}
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="percent">Percentage</Select.Item>
									<Select.Item value="fixed">Fixed Amount</Select.Item>
								</Select.Content>
							</Select.Root>
						</div>
						{#if discountType === 'percent'}
							<div class="space-y-2">
								<Label>Discount Percent</Label>
								<Input type="number" bind:value={discountPercent} min="0" max="100" step="0.01" />
							</div>
						{:else}
							<div class="space-y-2">
								<Label>Discount Amount</Label>
								<Input type="number" bind:value={discountAmount} min="0" step="0.01" />
							</div>
						{/if}
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
							{#if calculatedDiscount > 0}
								<div class="flex justify-between text-green-600">
									<dt>Discount</dt>
									<dd>-{formatCurrency(calculatedDiscount)}</dd>
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
