<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { CreditCard, Lock, ShieldCheck } from 'lucide-svelte';

	interface PaymentInfo {
		cardNumber: string;
		cardHolder: string;
		expiryMonth: string;
		expiryYear: string;
		cvv: string;
		saveCard?: boolean;
	}

	interface Props {
		amount: number;
		currency?: string;
		description?: string;
		paymentInfo?: PaymentInfo;
		onUpdate?: (info: PaymentInfo) => void;
		onValidate?: (info: PaymentInfo) => boolean;
		showSaveCard?: boolean;
		processingFee?: number;
		taxRate?: number;
	}

	let {
		amount,
		currency = 'USD',
		description,
		paymentInfo = $bindable({
			cardNumber: '',
			cardHolder: '',
			expiryMonth: '',
			expiryYear: '',
			cvv: '',
			saveCard: false
		}),
		onUpdate,
		onValidate,
		showSaveCard = true,
		processingFee = 0,
		taxRate = 0
	}: Props = $props();

	let cardNumberFormatted = $state('');

	const subtotal = $derived(amount);
	const fee = $derived(processingFee > 0 ? amount * processingFee : 0);
	const tax = $derived(taxRate > 0 ? amount * taxRate : 0);
	const total = $derived(subtotal + fee + tax);

	function formatCardNumber(value: string) {
		// Remove all non-digits
		const digits = value.replace(/\D/g, '');
		// Add spaces every 4 digits
		const formatted = digits.match(/.{1,4}/g)?.join(' ') || '';
		cardNumberFormatted = formatted;
		paymentInfo.cardNumber = digits;
		if (onUpdate) onUpdate(paymentInfo);
	}

	function formatCurrency(value: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: currency
		}).format(value);
	}

	function handleInputChange() {
		if (onUpdate) {
			onUpdate(paymentInfo);
		}
	}

	const isValid = $derived(() => {
		if (onValidate) {
			return onValidate(paymentInfo);
		}
		// Basic validation
		return (
			paymentInfo.cardNumber.length >= 13 &&
			paymentInfo.cardHolder.length > 0 &&
			paymentInfo.expiryMonth.length === 2 &&
			paymentInfo.expiryYear.length === 2 &&
			paymentInfo.cvv.length >= 3
		);
	});

	// Generate year options (current year + 10 years)
	const currentYear = new Date().getFullYear();
	const yearOptions = Array.from({ length: 11 }, (_, i) => currentYear + i);
</script>

<div class="payment-step space-y-6">
	<!-- Security Badge -->
	<div class="flex items-center justify-center gap-2 text-sm text-muted-foreground">
		<Lock class="h-4 w-4" />
		<span>Secure payment powered by SSL encryption</span>
		<ShieldCheck class="h-4 w-4 text-green-600" />
	</div>

	<!-- Payment Summary -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Payment Summary</Card.Title>
			{#if description}
				<Card.Description>{description}</Card.Description>
			{/if}
		</Card.Header>
		<Card.Content>
			<dl class="space-y-2">
				<div class="flex justify-between">
					<dt class="text-muted-foreground">Subtotal</dt>
					<dd class="font-medium">{formatCurrency(subtotal)}</dd>
				</div>
				{#if fee > 0}
					<div class="flex justify-between text-sm">
						<dt class="text-muted-foreground">Processing Fee ({processingFee * 100}%)</dt>
						<dd>{formatCurrency(fee)}</dd>
					</div>
				{/if}
				{#if tax > 0}
					<div class="flex justify-between text-sm">
						<dt class="text-muted-foreground">Tax ({taxRate * 100}%)</dt>
						<dd>{formatCurrency(tax)}</dd>
					</div>
				{/if}
				<div class="flex justify-between border-t pt-2 text-lg font-bold">
					<dt>Total</dt>
					<dd>{formatCurrency(total)}</dd>
				</div>
			</dl>
		</Card.Content>
	</Card.Root>

	<!-- Payment Form -->
	<Card.Root>
		<Card.Header>
			<div class="flex items-center gap-2">
				<CreditCard class="h-5 w-5" />
				<Card.Title>Payment Information</Card.Title>
			</div>
		</Card.Header>
		<Card.Content class="space-y-4">
			<!-- Card Number -->
			<div class="space-y-2">
				<Label for="cardNumber">Card Number *</Label>
				<Input
					id="cardNumber"
					type="text"
					placeholder="1234 5678 9012 3456"
					value={cardNumberFormatted}
					oninput={(e) => formatCardNumber(e.currentTarget.value)}
					maxlength={19}
					required
				/>
			</div>

			<!-- Card Holder -->
			<div class="space-y-2">
				<Label for="cardHolder">Cardholder Name *</Label>
				<Input
					id="cardHolder"
					type="text"
					placeholder="JOHN DOE"
					bind:value={paymentInfo.cardHolder}
					oninput={handleInputChange}
					class="uppercase"
					required
				/>
			</div>

			<!-- Expiry & CVV -->
			<div class="grid grid-cols-3 gap-4">
				<div class="space-y-2">
					<Label for="expiryMonth">Month *</Label>
					<select
						id="expiryMonth"
						bind:value={paymentInfo.expiryMonth}
						onchange={handleInputChange}
						class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
						required
					>
						<option value="">MM</option>
						{#each Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0')) as month}
							<option value={month}>{month}</option>
						{/each}
					</select>
				</div>

				<div class="space-y-2">
					<Label for="expiryYear">Year *</Label>
					<select
						id="expiryYear"
						bind:value={paymentInfo.expiryYear}
						onchange={handleInputChange}
						class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
						required
					>
						<option value="">YY</option>
						{#each yearOptions as year}
							<option value={String(year).slice(-2)}>{String(year).slice(-2)}</option>
						{/each}
					</select>
				</div>

				<div class="space-y-2">
					<Label for="cvv">CVV *</Label>
					<Input
						id="cvv"
						type="text"
						placeholder="123"
						bind:value={paymentInfo.cvv}
						oninput={handleInputChange}
						maxlength={4}
						pattern="[0-9]*"
						required
					/>
				</div>
			</div>

			<!-- Save Card Option -->
			{#if showSaveCard}
				<div class="flex items-center gap-2 pt-2">
					<input
						type="checkbox"
						id="saveCard"
						bind:checked={paymentInfo.saveCard}
						onchange={handleInputChange}
						class="h-4 w-4"
					/>
					<Label for="saveCard" class="cursor-pointer font-normal">
						Save card for future payments
					</Label>
				</div>
			{/if}
		</Card.Content>
	</Card.Root>

	<!-- Security Notice -->
	<div class="text-center text-xs text-muted-foreground">
		<p>
			Your payment information is encrypted and secure. We never store your full card details on our
			servers.
		</p>
	</div>
</div>

<style>
	.payment-step {
		max-width: 600px;
		margin: 0 auto;
	}
</style>
