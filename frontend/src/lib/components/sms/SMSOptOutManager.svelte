<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { smsOptOutsApi, type SmsOptOut } from '$lib/api/sms';
	import { Plus, Trash2, Search, UserX, Loader2, Check } from 'lucide-svelte';
	import { formatDistanceToNow } from 'date-fns';

	let loading = $state(true);
	let optOuts = $state<SmsOptOut[]>([]);
	let showAddDialog = $state(false);
	let showCheckDialog = $state(false);
	let saving = $state(false);
	let searchQuery = $state('');

	// Form state
	let phoneNumber = $state('');
	let optOutType = $state<string>('all');
	let reason = $state('');

	// Check state
	let checkPhone = $state('');
	let checkResult = $state<{ phone_number: string; is_opted_out: boolean } | null>(null);
	let checking = $state(false);

	const typeOptions = [
		{ value: 'all', label: 'All Messages' },
		{ value: 'marketing', label: 'Marketing Only' },
		{ value: 'transactional', label: 'Transactional Only' }
	];

	async function loadOptOuts() {
		loading = true;
		try {
			const result = await smsOptOutsApi.list({
				active_only: true,
				search: searchQuery || undefined
			});
			optOuts = result.data;
		} catch (err) {
			console.error('Failed to load opt-outs:', err);
		}
		loading = false;
	}

	function openAddDialog() {
		phoneNumber = '';
		optOutType = 'all';
		reason = '';
		showAddDialog = true;
	}

	async function handleAdd() {
		if (!phoneNumber.trim()) return;

		saving = true;
		try {
			await smsOptOutsApi.optOut({
				phone_number: phoneNumber.trim(),
				type: optOutType as 'all' | 'marketing' | 'transactional',
				reason: reason.trim() || undefined
			});

			showAddDialog = false;
			loadOptOuts();
		} catch (err) {
			console.error('Failed to add opt-out:', err);
		}
		saving = false;
	}

	async function handleOptIn(optOut: SmsOptOut) {
		if (!confirm(`Re-subscribe ${optOut.phone_number}?`)) return;

		try {
			await smsOptOutsApi.optIn(optOut.phone_number, optOut.type);
			loadOptOuts();
		} catch (err) {
			console.error('Failed to opt-in:', err);
		}
	}

	async function handleDelete(optOut: SmsOptOut) {
		if (!confirm(`Remove opt-out record for ${optOut.phone_number}?`)) return;

		try {
			await smsOptOutsApi.delete(optOut.id);
			loadOptOuts();
		} catch (err) {
			console.error('Failed to delete opt-out:', err);
		}
	}

	async function handleCheck() {
		if (!checkPhone.trim()) return;

		checking = true;
		try {
			checkResult = await smsOptOutsApi.check(checkPhone.trim());
		} catch (err) {
			console.error('Failed to check opt-out:', err);
		}
		checking = false;
	}

	function handleSearch() {
		loadOptOuts();
	}

	onMount(() => {
		loadOptOuts();
	});
</script>

<Card>
	<CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<CardTitle>Opt-Out Management</CardTitle>
				<CardDescription>
					Manage SMS opt-outs and unsubscribes
				</CardDescription>
			</div>
			<div class="flex gap-2">
				<Button variant="outline" onclick={() => (showCheckDialog = true)}>
					<Search class="h-4 w-4 mr-2" />
					Check Number
				</Button>
				<Button onclick={openAddDialog}>
					<Plus class="h-4 w-4 mr-2" />
					Add Opt-Out
				</Button>
			</div>
		</div>
	</CardHeader>
	<CardContent>
		<!-- Search -->
		<div class="flex gap-2 mb-4">
			<Input
				bind:value={searchQuery}
				placeholder="Search by phone number..."
				class="max-w-sm"
				onkeydown={(e) => e.key === 'Enter' && handleSearch()}
			/>
			<Button variant="outline" onclick={handleSearch}>
				<Search class="h-4 w-4" />
			</Button>
		</div>

		{#if loading}
			<div class="flex items-center justify-center h-32">
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if optOuts.length === 0}
			<div class="flex flex-col items-center justify-center h-32 text-muted-foreground">
				<UserX class="h-8 w-8 mb-2 opacity-50" />
				<p class="text-sm">No opt-outs found</p>
			</div>
		{:else}
			<div class="space-y-2">
				{#each optOuts as optOut}
					<div class="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50">
						<div>
							<div class="flex items-center gap-2">
								<span class="font-mono">{optOut.phone_number}</span>
								<Badge variant="outline">{typeOptions.find(t => t.value === optOut.type)?.label || optOut.type}</Badge>
							</div>
							<div class="text-xs text-muted-foreground mt-1">
								{#if optOut.reason}
									<span>{optOut.reason} &bull; </span>
								{/if}
								<span>Opted out {formatDistanceToNow(new Date(optOut.opted_out_at), { addSuffix: true })}</span>
							</div>
						</div>
						<div class="flex items-center gap-1">
							<Button variant="ghost" size="icon" class="h-8 w-8 text-green-600" onclick={() => handleOptIn(optOut)}>
								<Check class="h-4 w-4" />
							</Button>
							<Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" onclick={() => handleDelete(optOut)}>
								<Trash2 class="h-4 w-4" />
							</Button>
						</div>
					</div>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>

<!-- Add Opt-Out Dialog -->
<Dialog.Root bind:open={showAddDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Add Opt-Out</Dialog.Title>
			<Dialog.Description>
				Manually opt-out a phone number from SMS messages.
			</Dialog.Description>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleAdd(); }} class="space-y-4">
			<div class="space-y-2">
				<Label for="phoneNumber">Phone Number *</Label>
				<Input id="phoneNumber" bind:value={phoneNumber} placeholder="+1234567890" />
			</div>

			<div class="space-y-2">
				<Label>Opt-Out Type</Label>
				<Select.Root type="single" bind:value={optOutType}>
					<Select.Trigger>
						{typeOptions.find(t => t.value === optOutType)?.label || 'Select...'}
					</Select.Trigger>
					<Select.Content>
						{#each typeOptions as opt}
							<Select.Item value={opt.value} label={opt.label}>{opt.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<div class="space-y-2">
				<Label for="reason">Reason (Optional)</Label>
				<Input id="reason" bind:value={reason} placeholder="Customer requested via email" />
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showAddDialog = false)}>Cancel</Button>
			<Button onclick={handleAdd} disabled={saving || !phoneNumber.trim()}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-2 animate-spin" />
				{/if}
				Add Opt-Out
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Check Number Dialog -->
<Dialog.Root bind:open={showCheckDialog}>
	<Dialog.Content class="max-w-md">
		<Dialog.Header>
			<Dialog.Title>Check Opt-Out Status</Dialog.Title>
			<Dialog.Description>
				Check if a phone number is opted out from SMS messages.
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4">
			<div class="flex gap-2">
				<Input
					bind:value={checkPhone}
					placeholder="+1234567890"
					class="flex-1"
					onkeydown={(e) => e.key === 'Enter' && handleCheck()}
				/>
				<Button onclick={handleCheck} disabled={checking || !checkPhone.trim()}>
					{#if checking}
						<Loader2 class="h-4 w-4 animate-spin" />
					{:else}
						<Search class="h-4 w-4" />
					{/if}
				</Button>
			</div>

			{#if checkResult}
				<div class="p-4 rounded-lg {checkResult.is_opted_out ? 'bg-red-50' : 'bg-green-50'}">
					<div class="flex items-center gap-2">
						{#if checkResult.is_opted_out}
							<UserX class="h-5 w-5 text-red-600" />
							<span class="font-medium text-red-600">Opted Out</span>
						{:else}
							<Check class="h-5 w-5 text-green-600" />
							<span class="font-medium text-green-600">Not Opted Out</span>
						{/if}
					</div>
					<p class="text-sm text-muted-foreground mt-1">
						{checkResult.phone_number}
						{checkResult.is_opted_out ? ' has opted out of SMS messages.' : ' can receive SMS messages.'}
					</p>
				</div>
			{/if}
		</div>

		<Dialog.Footer>
			<Button onclick={() => { showCheckDialog = false; checkResult = null; checkPhone = ''; }}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
