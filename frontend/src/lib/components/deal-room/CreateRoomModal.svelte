<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { X } from 'lucide-svelte';
	import { createDealRoom, type DealRoom } from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	interface Props {
		onClose: () => void;
		onCreated: (room: DealRoom) => void;
		dealRecordId?: number | null;
	}

	let {
		onClose,
		onCreated,
		dealRecordId = null,
	}: Props = $props();

	let name = $state('');
	let description = $state('');
	let loading = $state(false);

	async function handleSubmit() {
		if (!name.trim()) {
			toast.error('Please enter a room name');
			return;
		}

		if (!dealRecordId) {
			toast.error('Please select a deal');
			return;
		}

		loading = true;
		const { data, error } = await tryCatch(
			createDealRoom({
				deal_record_id: dealRecordId,
				name: name.trim(),
				description: description.trim() || undefined
			})
		);
		loading = false;

		if (error) {
			toast.error('Failed to create room');
			return;
		}

		toast.success('Deal room created');
		if (data) {
			onCreated(data);
		}
	}
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
	class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
	onclick={onClose}
	onkeydown={(e) => e.key === 'Escape' && onClose()}
>
	<!-- svelte-ignore a11y_click_events_have_key_events -->
	<div
		class="w-full max-w-md rounded-lg bg-background shadow-xl"
		onclick={(e) => e.stopPropagation()}
	>
		<!-- Header -->
		<div class="flex items-center justify-between border-b p-4">
			<h2 class="text-lg font-semibold">Create Deal Room</h2>
			<Button variant="ghost" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<!-- Content -->
		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="p-4 space-y-4">
			<div class="space-y-2">
				<Label for="name">Room Name</Label>
				<Input
					id="name"
					bind:value={name}
					placeholder="e.g., Acme Corp Enterprise Deal"
					required
				/>
			</div>

			<div class="space-y-2">
				<Label for="description">Description (optional)</Label>
				<Textarea
					id="description"
					bind:value={description}
					placeholder="Brief description of the deal..."
					rows={3}
				/>
			</div>

			{#if !dealRecordId}
				<div class="text-sm text-amber-600 bg-amber-50 dark:bg-amber-950/30 rounded p-3">
					Note: You'll need to select a deal to associate with this room. Create the room from a deal record page for auto-association.
				</div>
			{/if}

			<!-- Footer -->
			<div class="flex justify-end gap-2 pt-4">
				<Button variant="outline" onclick={onClose} disabled={loading}>
					Cancel
				</Button>
				<Button type="submit" disabled={loading || !name.trim()}>
					{loading ? 'Creating...' : 'Create Room'}
				</Button>
			</div>
		</form>
	</div>
</div>
