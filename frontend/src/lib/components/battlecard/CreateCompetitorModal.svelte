<script lang="ts">
	import { X } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { createCompetitor, type Competitor } from '$lib/api/competitors';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	let {
		onClose,
		onCreated
	}: {
		onClose: () => void;
		onCreated: (competitor: Competitor) => void;
	} = $props();

	let name = $state('');
	let website = $state('');
	let description = $state('');
	let loading = $state(false);

	async function handleSubmit() {
		if (!name.trim()) {
			toast.error('Please enter a competitor name');
			return;
		}

		loading = true;
		const { data, error } = await tryCatch(
			createCompetitor({
				name: name.trim(),
				website: website.trim() || undefined,
				description: description.trim() || undefined
			})
		);
		loading = false;

		if (error) {
			toast.error('Failed to create competitor');
			return;
		}

		toast.success('Competitor created');
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
	role="dialog"
	aria-modal="true"
>
	<!-- svelte-ignore a11y_click_events_have_key_events -->
	<div
		class="w-full max-w-md rounded-lg bg-background shadow-xl"
		onclick={(e) => e.stopPropagation()}
		role="document"
	>
		<!-- Header -->
		<div class="flex items-center justify-between border-b p-4">
			<h2 class="text-lg font-semibold">Add Competitor</h2>
			<Button variant="ghost" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<!-- Content -->
		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="p-4 space-y-4">
			<div class="space-y-2">
				<Label for="name">Competitor Name *</Label>
				<Input
					id="name"
					bind:value={name}
					placeholder="e.g., Salesforce"
					required
				/>
			</div>

			<div class="space-y-2">
				<Label for="website">Website</Label>
				<Input
					id="website"
					type="url"
					bind:value={website}
					placeholder="https://competitor.com"
				/>
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Textarea
					id="description"
					bind:value={description}
					placeholder="Brief description of the competitor..."
					rows={3}
				/>
			</div>

			<!-- Footer -->
			<div class="flex justify-end gap-2 pt-4">
				<Button variant="outline" onclick={onClose} disabled={loading}>
					Cancel
				</Button>
				<Button type="submit" disabled={loading || !name.trim()}>
					{loading ? 'Creating...' : 'Create Competitor'}
				</Button>
			</div>
		</form>
	</div>
</div>
