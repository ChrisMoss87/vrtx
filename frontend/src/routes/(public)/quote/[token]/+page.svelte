<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import {
		FileText,
		CheckCircle,
		XCircle,
		Clock,
		AlertTriangle,
		Download
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getPublicQuote,
		acceptPublicQuote,
		rejectPublicQuote,
		getPublicQuotePdf,
		type PublicQuoteData,
		type QuoteStatus
	} from '$lib/api/billing';

	const token = $derived($page.params.token ?? '');

	let quote = $state<PublicQuoteData | null>(null);
	let loading = $state(true);
	let error = $state<string | null>(null);

	// Accept dialog state
	let acceptDialogOpen = $state(false);
	let acceptedBy = $state('');
	let accepting = $state(false);

	// Reject dialog state
	let rejectDialogOpen = $state(false);
	let rejectedBy = $state('');
	let rejectionReason = $state('');
	let rejecting = $state(false);

	const statusColors: Record<QuoteStatus, string> = {
		draft: 'bg-gray-100 text-gray-800',
		sent: 'bg-blue-100 text-blue-800',
		viewed: 'bg-purple-100 text-purple-800',
		accepted: 'bg-green-100 text-green-800',
		rejected: 'bg-red-100 text-red-800',
		expired: 'bg-orange-100 text-orange-800'
	};

	onMount(async () => {
		await loadQuote();
	});

	async function loadQuote() {
		loading = true;
		error = null;
		try {
			quote = await getPublicQuote(token);
		} catch (err) {
			console.error('Failed to load quote:', err);
			error = 'Quote not found or has expired.';
		} finally {
			loading = false;
		}
	}

	async function handleAccept() {
		if (!acceptedBy.trim()) {
			toast.error('Please enter your name');
			return;
		}

		accepting = true;
		try {
			await acceptPublicQuote(token, {
				accepted_by: acceptedBy
			});
			toast.success('Quote accepted successfully!');
			acceptDialogOpen = false;
			await loadQuote();
		} catch (err) {
			console.error('Failed to accept quote:', err);
			toast.error('Failed to accept quote');
		} finally {
			accepting = false;
		}
	}

	async function handleReject() {
		if (!rejectedBy.trim()) {
			toast.error('Please enter your name');
			return;
		}

		rejecting = true;
		try {
			await rejectPublicQuote(token, {
				rejected_by: rejectedBy,
				reason: rejectionReason || undefined
			});
			toast.success('Quote rejected');
			rejectDialogOpen = false;
			await loadQuote();
		} catch (err) {
			console.error('Failed to reject quote:', err);
			toast.error('Failed to reject quote');
		} finally {
			rejecting = false;
		}
	}

	async function handleDownloadPdf() {
		try {
			const pdfData = await getPublicQuotePdf(token);
			console.log('PDF Data:', pdfData);
			toast.success('PDF generation ready (check console for data)');
		} catch (err) {
			console.error('Failed to generate PDF:', err);
			toast.error('Failed to generate PDF');
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
</script>

<svelte:head>
	<title>{quote?.quote_number || 'Quote'} | View Quote</title>
</svelte:head>

<div class="min-h-screen bg-gray-50">
	<!-- Header -->
	<header class="border-b bg-white">
		<div class="container mx-auto flex items-center justify-between px-6 py-4">
			<div class="flex items-center gap-2">
				<FileText class="h-6 w-6 text-primary" />
				<span class="text-xl font-bold">Quote</span>
			</div>
			{#if quote && !quote.is_expired && quote.can_accept}
				<Button variant="outline" onclick={handleDownloadPdf}>
					<Download class="mr-2 h-4 w-4" />
					Download PDF
				</Button>
			{/if}
		</div>
	</header>

	<main class="container mx-auto max-w-4xl px-6 py-8">
		{#if loading}
			<div class="flex items-center justify-center py-20">
				<div class="text-muted-foreground">Loading quote...</div>
			</div>
		{:else if error}
			<Card.Root>
				<Card.Content class="flex flex-col items-center justify-center py-20">
					<AlertTriangle class="mb-4 h-12 w-12 text-orange-500" />
					<h2 class="mb-2 text-xl font-semibold">Quote Not Found</h2>
					<p class="text-muted-foreground">{error}</p>
				</Card.Content>
			</Card.Root>
		{:else if quote}
			<!-- Status Banner -->
			{#if quote.status === 'accepted'}
				<div class="mb-6 flex items-center gap-3 rounded-lg bg-green-50 p-4 text-green-800">
					<CheckCircle class="h-5 w-5" />
					<div>
						<p class="font-medium">Quote Accepted</p>
						<p class="text-sm">
							Accepted by {quote.accepted_by} on {formatDate(quote.accepted_at)}
						</p>
					</div>
				</div>
			{:else if quote.status === 'rejected'}
				<div class="mb-6 flex items-center gap-3 rounded-lg bg-red-50 p-4 text-red-800">
					<XCircle class="h-5 w-5" />
					<div>
						<p class="font-medium">Quote Rejected</p>
					</div>
				</div>
			{:else if quote.is_expired}
				<div class="mb-6 flex items-center gap-3 rounded-lg bg-orange-50 p-4 text-orange-800">
					<Clock class="h-5 w-5" />
					<div>
						<p class="font-medium">Quote Expired</p>
						<p class="text-sm">This quote expired on {formatDate(quote.valid_until)}</p>
					</div>
				</div>
			{/if}

			<!-- Quote Details -->
			<Card.Root class="mb-6">
				<Card.Header>
					<div class="flex items-start justify-between">
						<div>
							<Card.Title class="text-2xl">{quote.quote_number}</Card.Title>
							{#if quote.title}
								<Card.Description class="mt-1 text-base">{quote.title}</Card.Description>
							{/if}
						</div>
						<Badge class={statusColors[quote.status]}>
							{quote.status.charAt(0).toUpperCase() + quote.status.slice(1)}
						</Badge>
					</div>
				</Card.Header>
				<Card.Content>
					<div class="grid gap-4 sm:grid-cols-2">
						<div>
							<p class="text-sm text-muted-foreground">Date</p>
							<p class="font-medium">{formatDate(quote.created_at)}</p>
						</div>
						<div>
							<p class="text-sm text-muted-foreground">Valid Until</p>
							<p class="font-medium" class:text-red-600={quote.is_expired}>
								{formatDate(quote.valid_until)}
								{#if quote.is_expired}
									(Expired)
								{/if}
							</p>
						</div>
						{#if quote.created_by}
							<div>
								<p class="text-sm text-muted-foreground">From</p>
								<p class="font-medium">{quote.created_by.name}</p>
								<p class="text-sm text-muted-foreground">{quote.created_by.email}</p>
							</div>
						{/if}
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Line Items -->
			<Card.Root class="mb-6">
				<Card.Header>
					<Card.Title>Items</Card.Title>
				</Card.Header>
				<Card.Content>
					<div class="overflow-x-auto">
						<table class="w-full">
							<thead class="border-b">
								<tr>
									<th class="py-3 text-left text-sm font-medium">Description</th>
									<th class="py-3 text-right text-sm font-medium">Qty</th>
									<th class="py-3 text-right text-sm font-medium">Unit Price</th>
									<th class="py-3 text-right text-sm font-medium">Total</th>
								</tr>
							</thead>
							<tbody class="divide-y">
								{#each quote.line_items as item}
									<tr>
										<td class="py-4">{item.description}</td>
										<td class="py-4 text-right">{item.quantity}</td>
										<td class="py-4 text-right">{formatCurrency(item.unit_price, quote.currency)}</td>
										<td class="py-4 text-right font-medium">{formatCurrency(item.line_total, quote.currency)}</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>

					<!-- Totals -->
					<div class="mt-6 border-t pt-4">
						<dl class="space-y-2">
							<div class="flex justify-between">
								<dt class="text-muted-foreground">Subtotal</dt>
								<dd>{formatCurrency(quote.subtotal, quote.currency)}</dd>
							</div>
							{#if quote.discount_amount > 0}
								<div class="flex justify-between text-green-600">
									<dt>Discount</dt>
									<dd>-{formatCurrency(quote.discount_amount, quote.currency)}</dd>
								</div>
							{/if}
							{#if quote.tax_amount > 0}
								<div class="flex justify-between">
									<dt class="text-muted-foreground">Tax</dt>
									<dd>{formatCurrency(quote.tax_amount, quote.currency)}</dd>
								</div>
							{/if}
							<div class="flex justify-between border-t pt-2 text-xl font-bold">
								<dt>Total</dt>
								<dd>{formatCurrency(quote.total, quote.currency)}</dd>
							</div>
						</dl>
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Terms & Notes -->
			{#if quote.terms || quote.notes}
				<Card.Root class="mb-6">
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

			<!-- Actions -->
			{#if quote.can_accept}
				<Card.Root>
					<Card.Content class="flex flex-col items-center gap-4 py-8 sm:flex-row sm:justify-center">
						<Button
							size="lg"
							class="w-full sm:w-auto"
							onclick={() => (acceptDialogOpen = true)}
						>
							<CheckCircle class="mr-2 h-5 w-5" />
							Accept Quote
						</Button>
						<Button
							variant="outline"
							size="lg"
							class="w-full sm:w-auto"
							onclick={() => (rejectDialogOpen = true)}
						>
							<XCircle class="mr-2 h-5 w-5" />
							Decline Quote
						</Button>
					</Card.Content>
				</Card.Root>
			{/if}
		{/if}
	</main>
</div>

<!-- Accept Dialog -->
<Dialog.Root bind:open={acceptDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Accept Quote</Dialog.Title>
			<Dialog.Description>
				By accepting this quote, you agree to the terms and conditions outlined above.
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="acceptedBy">Your Name</Label>
				<Input
					id="acceptedBy"
					bind:value={acceptedBy}
					placeholder="Enter your full name"
				/>
			</div>
			<p class="text-sm text-muted-foreground">
				Your name will be recorded as the person who accepted this quote.
			</p>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (acceptDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleAccept} disabled={!acceptedBy.trim() || accepting}>
				<CheckCircle class="mr-2 h-4 w-4" />
				{accepting ? 'Accepting...' : 'Accept Quote'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Reject Dialog -->
<Dialog.Root bind:open={rejectDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Decline Quote</Dialog.Title>
			<Dialog.Description>
				Please let us know why you're declining this quote. This feedback helps us improve our
				offerings.
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="rejectedBy">Your Name</Label>
				<Input
					id="rejectedBy"
					bind:value={rejectedBy}
					placeholder="Enter your full name"
				/>
			</div>
			<div class="space-y-2">
				<Label for="rejectionReason">Reason (optional)</Label>
				<Textarea
					id="rejectionReason"
					bind:value={rejectionReason}
					rows={3}
					placeholder="Please share why you're declining..."
				/>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (rejectDialogOpen = false)}>Cancel</Button>
			<Button variant="destructive" onclick={handleReject} disabled={!rejectedBy.trim() || rejecting}>
				<XCircle class="mr-2 h-4 w-4" />
				{rejecting ? 'Declining...' : 'Decline Quote'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
