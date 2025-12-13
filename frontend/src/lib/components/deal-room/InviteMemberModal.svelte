<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { X } from 'lucide-svelte';
	import { addMember } from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	export let roomId: number;
	export let onClose: () => void;
	export let onInvited: () => void;

	let email = '';
	let name = '';
	let role = 'stakeholder';
	let loading = false;

	async function handleSubmit() {
		if (!email.trim()) {
			toast.error('Please enter an email');
			return;
		}

		loading = true;
		const { data, error } = await tryCatch(
			addMember(roomId, {
				external_email: email.trim(),
				external_name: name.trim() || undefined,
				role
			})
		);
		loading = false;

		if (error) {
			toast.error('Failed to invite member');
			return;
		}

		toast.success('Invitation sent');
		onInvited();
		onClose();
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
			<h2 class="text-lg font-semibold">Invite Stakeholder</h2>
			<Button variant="ghost" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<!-- Content -->
		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="p-4 space-y-4">
			<div class="space-y-2">
				<Label for="email">Email Address</Label>
				<Input
					id="email"
					type="email"
					bind:value={email}
					placeholder="stakeholder@company.com"
					required
				/>
			</div>

			<div class="space-y-2">
				<Label for="name">Name (optional)</Label>
				<Input
					id="name"
					bind:value={name}
					placeholder="John Smith"
				/>
			</div>

			<div class="space-y-2">
				<Label>Role</Label>
				<Select.Root
					type="single"
					value={role}
					onValueChange={(val) => { if (val) role = val; }}
				>
					<Select.Trigger>
						<span>{role.charAt(0).toUpperCase() + role.slice(1)}</span>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="stakeholder">Stakeholder (Can complete tasks, message)</Select.Item>
						<Select.Item value="viewer">Viewer (View only)</Select.Item>
					</Select.Content>
				</Select.Root>
			</div>

			<div class="text-sm text-muted-foreground bg-muted/50 rounded p-3">
				The invited person will receive a unique link to access this deal room. The link expires in 30 days.
			</div>

			<!-- Footer -->
			<div class="flex justify-end gap-2 pt-4">
				<Button variant="outline" onclick={onClose} disabled={loading}>
					Cancel
				</Button>
				<Button type="submit" disabled={loading || !email.trim()}>
					{loading ? 'Sending...' : 'Send Invitation'}
				</Button>
			</div>
		</form>
	</div>
</div>
