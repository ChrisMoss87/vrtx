<script lang="ts">
	import { flip } from 'svelte/animate';
	import { dndzone } from 'svelte-dnd-action';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import * as Popover from '$lib/components/ui/popover';
	import * as Command from '$lib/components/ui/command';
	import {
		GripVertical,
		Plus,
		Trash2,
		Search,
		Percent,
		DollarSign,
		ChevronDown,
		Package,
		Type as TypeIcon
	} from 'lucide-svelte';
	import type { Product, TaxRate } from '$lib/api/billing';

	// Types - exported for use by parent components
	export interface LineItem {
		id: string;
		item_type: 'product' | 'service' | 'text';
		product_id: number | null;
		sku: string | null;
		description: string;
		detailed_description: string | null;
		quantity: number;
		unit: string | null;
		unit_price: number;
		discount_type: 'none' | 'percent' | 'fixed';
		discount_value: number;
		tax_rate_id: number | null;
		tax_rate: number;
	}

	// Props
	let {
		items = $bindable<LineItem[]>([]),
		products = [],
		taxRates = [],
		currency = 'USD',
		readonly = false,
		onchange
	}: {
		items: LineItem[];
		products: Product[];
		taxRates: TaxRate[];
		currency: string;
		readonly?: boolean;
		onchange?: () => void;
	} = $props();

	// State
	let dragDisabled = $state(true);
	let productSearchOpen = $state<Record<string, boolean>>({});
	let discountPopoverOpen = $state<Record<string, boolean>>({});
	let productSearchQuery = $state('');

	// Generate unique ID
	function generateId(): string {
		return Math.random().toString(36).substring(2, 11);
	}

	// Create empty line item
	function createEmptyItem(): LineItem {
		const defaultTax = taxRates.find((t) => t.is_default);
		return {
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
		};
	}

	// Add new line item
	function addLineItem(type: 'product' | 'text' = 'product') {
		const newItem = createEmptyItem();
		newItem.item_type = type;
		if (type === 'text') {
			newItem.quantity = 0;
			newItem.unit_price = 0;
		}
		items = [...items, newItem];
		onchange?.();
	}

	// Remove line item
	function removeLineItem(id: string) {
		items = items.filter((item) => item.id !== id);
		onchange?.();
	}

	// Select product for line item
	function selectProduct(itemId: string, product: Product) {
		items = items.map((item) => {
			if (item.id === itemId) {
				return {
					...item,
					product_id: product.id,
					sku: product.sku,
					description: product.name,
					detailed_description: product.description,
					unit_price: product.unit_price,
					unit: product.unit,
					tax_rate: product.tax_rate
				};
			}
			return item;
		});
		productSearchOpen[itemId] = false;
		productSearchQuery = '';
		onchange?.();
	}

	// Update line item field
	function updateItem(id: string, field: keyof LineItem, value: unknown) {
		items = items.map((item) => {
			if (item.id === id) {
				return { ...item, [field]: value };
			}
			return item;
		});
		onchange?.();
	}

	// Set tax rate
	function setTaxRate(itemId: string, taxRateId: number | null) {
		const taxRate = taxRates.find((t) => t.id === taxRateId);
		items = items.map((item) => {
			if (item.id === itemId) {
				return {
					...item,
					tax_rate_id: taxRateId,
					tax_rate: taxRate?.rate ?? 0
				};
			}
			return item;
		});
		onchange?.();
	}

	// Toggle discount type
	function toggleDiscount(itemId: string) {
		items = items.map((item) => {
			if (item.id === itemId) {
				const nextType =
					item.discount_type === 'none'
						? 'percent'
						: item.discount_type === 'percent'
							? 'fixed'
							: 'none';
				return {
					...item,
					discount_type: nextType,
					discount_value: nextType === 'none' ? 0 : item.discount_value
				};
			}
			return item;
		});
		discountPopoverOpen[itemId] = true;
		onchange?.();
	}

	// Calculate line total
	function calculateLineTotal(item: LineItem): number {
		if (item.item_type === 'text') return 0;

		const subtotal = item.quantity * item.unit_price;
		let discount = 0;

		if (item.discount_type === 'percent') {
			discount = subtotal * (item.discount_value / 100);
		} else if (item.discount_type === 'fixed') {
			discount = item.discount_value;
		}

		return subtotal - discount;
	}

	// Calculate line tax
	function calculateLineTax(item: LineItem): number {
		const lineTotal = calculateLineTotal(item);
		return lineTotal * (item.tax_rate / 100);
	}

	// Format currency
	function formatCurrency(amount: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency
		}).format(amount);
	}

	// Drag and drop handlers
	function handleDndConsider(e: CustomEvent) {
		items = e.detail.items;
	}

	function handleDndFinalize(e: CustomEvent) {
		items = e.detail.items;
		onchange?.();
	}

	function startDrag() {
		dragDisabled = false;
	}

	function endDrag() {
		dragDisabled = true;
	}

	// Filter products by search
	const filteredProducts = $derived(
		products.filter(
			(p) =>
				p.name.toLowerCase().includes(productSearchQuery.toLowerCase()) ||
				p.sku?.toLowerCase().includes(productSearchQuery.toLowerCase())
		)
	);

	// Totals
	const subtotal = $derived(items.reduce((sum, item) => sum + calculateLineTotal(item), 0));
	const totalTax = $derived(items.reduce((sum, item) => sum + calculateLineTax(item), 0));
	const total = $derived(subtotal + totalTax);
</script>

<div class="space-y-4">
	<!-- Header Row -->
	<div
		class="grid gap-2 border-b pb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground"
		style="grid-template-columns: 40px 1fr 80px 80px 100px 80px 100px 40px"
	>
		<div></div>
		<div>Item / Description</div>
		<div class="text-right">Qty</div>
		<div>Unit</div>
		<div class="text-right">Price</div>
		<div class="text-center">Tax</div>
		<div class="text-right">Amount</div>
		<div></div>
	</div>

	<!-- Line Items -->
	<div
		use:dndzone={{
			items,
			flipDurationMs: 200,
			dragDisabled,
			dropTargetStyle: { outline: 'none' }
		}}
		onconsider={handleDndConsider}
		onfinalize={handleDndFinalize}
		class="space-y-2"
	>
		{#each items as item (item.id)}
			<div
				animate:flip={{ duration: 200 }}
				class="group grid items-center gap-2 rounded-lg border bg-card p-2 transition-shadow hover:shadow-sm"
				style="grid-template-columns: 40px 1fr 80px 80px 100px 80px 100px 40px"
			>
				<!-- Drag Handle -->
				<div class="flex justify-center">
					{#if !readonly}
						<button
							type="button"
							class="cursor-grab rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-muted group-hover:opacity-100"
							onmousedown={startDrag}
							onmouseup={endDrag}
							ontouchstart={startDrag}
							ontouchend={endDrag}
						>
							<GripVertical class="h-4 w-4" />
						</button>
					{/if}
				</div>

				<!-- Description -->
				<div class="min-w-0">
					{#if item.item_type === 'text'}
						<Input
							value={item.description}
							oninput={(e) => updateItem(item.id, 'description', e.currentTarget.value)}
							placeholder="Section heading or note..."
							class="border-0 bg-transparent font-medium italic focus-visible:ring-1"
							disabled={readonly}
						/>
					{:else}
						<Popover.Root bind:open={productSearchOpen[item.id]}>
							<Popover.Trigger>
								{#snippet child({ props })}
									<button
										{...props}
										type="button"
										class="flex w-full items-center gap-2 rounded-md border-0 bg-transparent px-2 py-1.5 text-left text-sm hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring"
										disabled={readonly}
									>
										{#if item.product_id}
											<Package class="h-4 w-4 text-muted-foreground" />
											<span class="flex-1 truncate">{item.description}</span>
											{#if item.sku}
												<span class="text-xs text-muted-foreground">{item.sku}</span>
											{/if}
										{:else}
											<Search class="h-4 w-4 text-muted-foreground" />
											<span class="flex-1 text-muted-foreground">Search products...</span>
										{/if}
										<ChevronDown class="h-4 w-4 text-muted-foreground" />
									</button>
								{/snippet}
							</Popover.Trigger>
							<Popover.Content class="w-80 p-0" align="start">
								<Command.Root>
									<Command.Input
										placeholder="Search products..."
										value={productSearchQuery}
										oninput={(e) => (productSearchQuery = e.currentTarget.value)}
									/>
									<Command.List>
										<Command.Empty>No products found.</Command.Empty>
										<Command.Group heading="Products">
											{#each filteredProducts.slice(0, 10) as product}
												<Command.Item
													value={product.name}
													onSelect={() => selectProduct(item.id, product)}
													class="flex items-center justify-between"
												>
													<div class="flex flex-col">
														<span>{product.name}</span>
														{#if product.sku}
															<span class="text-xs text-muted-foreground">{product.sku}</span>
														{/if}
													</div>
													<span class="text-sm font-medium"
														>{formatCurrency(product.unit_price)}</span
													>
												</Command.Item>
											{/each}
										</Command.Group>
										<Command.Separator />
										<Command.Group>
											<Command.Item
												onSelect={() => {
													updateItem(item.id, 'product_id', null);
													productSearchOpen[item.id] = false;
												}}
											>
												<TypeIcon class="mr-2 h-4 w-4" />
												Custom line item
											</Command.Item>
										</Command.Group>
									</Command.List>
								</Command.Root>
							</Popover.Content>
						</Popover.Root>

						{#if !item.product_id}
							<Input
								value={item.description}
								oninput={(e) => updateItem(item.id, 'description', e.currentTarget.value)}
								placeholder="Item description"
								class="mt-1 border-0 bg-transparent text-sm focus-visible:ring-1"
								disabled={readonly}
							/>
						{/if}
					{/if}
				</div>

				<!-- Quantity -->
				<div>
					{#if item.item_type !== 'text'}
						<Input
							type="number"
							value={item.quantity}
							oninput={(e) => updateItem(item.id, 'quantity', parseFloat(e.currentTarget.value) || 0)}
							min="0"
							step="0.01"
							class="h-8 border-0 bg-transparent text-right text-sm focus-visible:ring-1"
							disabled={readonly}
						/>
					{/if}
				</div>

				<!-- Unit -->
				<div>
					{#if item.item_type !== 'text'}
						<Input
							value={item.unit || ''}
							oninput={(e) => updateItem(item.id, 'unit', e.currentTarget.value || null)}
							placeholder="units"
							class="h-8 border-0 bg-transparent text-sm focus-visible:ring-1"
							disabled={readonly}
						/>
					{/if}
				</div>

				<!-- Price -->
				<div class="relative">
					{#if item.item_type !== 'text'}
						<Input
							type="number"
							value={item.unit_price}
							oninput={(e) => updateItem(item.id, 'unit_price', parseFloat(e.currentTarget.value) || 0)}
							min="0"
							step="0.01"
							class="h-8 border-0 bg-transparent pr-6 text-right text-sm focus-visible:ring-1"
							disabled={readonly}
						/>
						<!-- Discount indicator -->
						{#if item.discount_type !== 'none'}
							<Popover.Root bind:open={discountPopoverOpen[item.id]}>
								<Popover.Trigger>
									{#snippet child({ props })}
										<button
											{...props}
											type="button"
											class="absolute right-0 top-0 flex h-8 w-6 items-center justify-center text-green-600 hover:text-green-700"
										>
											{#if item.discount_type === 'percent'}
												<Percent class="h-3 w-3" />
											{:else}
												<DollarSign class="h-3 w-3" />
											{/if}
										</button>
									{/snippet}
								</Popover.Trigger>
								<Popover.Content class="w-48 p-2">
									<div class="space-y-2">
										<div class="flex items-center gap-2">
											<Select.Root
												type="single"
												value={item.discount_type}
												onValueChange={(v) => updateItem(item.id, 'discount_type', v)}
											>
												<Select.Trigger class="h-8 flex-1">
													{item.discount_type === 'percent' ? '%' : '$'}
												</Select.Trigger>
												<Select.Content>
													<Select.Item value="percent">Percent</Select.Item>
													<Select.Item value="fixed">Fixed</Select.Item>
													<Select.Item value="none">None</Select.Item>
												</Select.Content>
											</Select.Root>
											<Input
												type="number"
												value={item.discount_value}
												oninput={(e) =>
													updateItem(
														item.id,
														'discount_value',
														parseFloat(e.currentTarget.value) || 0
													)}
												class="h-8 w-20"
												min="0"
												step="0.01"
											/>
										</div>
									</div>
								</Popover.Content>
							</Popover.Root>
						{:else if !readonly}
							<button
								type="button"
								class="absolute right-0 top-0 flex h-8 w-6 items-center justify-center text-muted-foreground opacity-0 hover:text-foreground group-hover:opacity-100"
								onclick={() => toggleDiscount(item.id)}
								title="Add discount"
							>
								<Percent class="h-3 w-3" />
							</button>
						{/if}
					{/if}
				</div>

				<!-- Tax -->
				<div>
					{#if item.item_type !== 'text'}
						<Select.Root
							type="single"
							value={item.tax_rate_id?.toString() ?? ''}
							onValueChange={(v) => setTaxRate(item.id, v ? parseInt(v) : null)}
							disabled={readonly}
						>
							<Select.Trigger class="h-8 w-full border-0 bg-transparent text-xs">
								{taxRates.find((t) => t.id === item.tax_rate_id)?.name || 'No tax'}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="">No tax</Select.Item>
								{#each taxRates as rate}
									<Select.Item value={rate.id.toString()}>
										{rate.name} ({rate.rate}%)
									</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					{/if}
				</div>

				<!-- Amount -->
				<div class="text-right">
					{#if item.item_type !== 'text'}
						<span class="text-sm font-medium">
							{formatCurrency(calculateLineTotal(item))}
						</span>
						{#if item.discount_type !== 'none' && item.discount_value > 0}
							<div class="text-xs text-green-600">
								-{item.discount_type === 'percent'
									? `${item.discount_value}%`
									: formatCurrency(item.discount_value)}
							</div>
						{/if}
					{/if}
				</div>

				<!-- Actions -->
				<div class="flex justify-center">
					{#if !readonly}
						<button
							type="button"
							class="rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-destructive/10 hover:text-destructive group-hover:opacity-100"
							onclick={() => removeLineItem(item.id)}
						>
							<Trash2 class="h-4 w-4" />
						</button>
					{/if}
				</div>
			</div>
		{/each}
	</div>

	<!-- Add Line Buttons -->
	{#if !readonly}
		<div class="flex gap-2 pt-2">
			<Button variant="outline" size="sm" onclick={() => addLineItem('product')}>
				<Plus class="mr-2 h-4 w-4" />
				Add Line Item
			</Button>
			<Button variant="ghost" size="sm" onclick={() => addLineItem('text')}>
				<TypeIcon class="mr-2 h-4 w-4" />
				Add Text Line
			</Button>
		</div>
	{/if}

	<!-- Totals -->
	<div class="mt-6 flex justify-end">
		<div class="w-64 space-y-2 rounded-lg bg-muted/50 p-4">
			<div class="flex justify-between text-sm">
				<span class="text-muted-foreground">Subtotal</span>
				<span class="font-medium">{formatCurrency(subtotal)}</span>
			</div>
			{#if totalTax > 0}
				<div class="flex justify-between text-sm">
					<span class="text-muted-foreground">Tax</span>
					<span>{formatCurrency(totalTax)}</span>
				</div>
			{/if}
			<div class="flex justify-between border-t pt-2 text-lg font-bold">
				<span>Total</span>
				<span>{formatCurrency(total)}</span>
			</div>
		</div>
	</div>
</div>
