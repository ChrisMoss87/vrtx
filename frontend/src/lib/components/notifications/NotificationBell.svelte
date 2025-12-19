<script lang="ts">
	import { Bell } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Popover from '$lib/components/ui/popover';
	import { notifications, unreadCount } from '$lib/stores/notifications';
	import NotificationPanel from './NotificationPanel.svelte';

	interface Props {
		userId?: number;
	}

	let { userId }: Props = $props();

	let open = $state(false);

	function handleOpenChange(isOpen: boolean) {
		open = isOpen;
		if (isOpen) {
			// Refresh notifications when panel opens
			notifications.load(true);
		}
	}
</script>

<Popover.Root bind:open onOpenChange={handleOpenChange}>
	<Popover.Trigger>
		{#snippet child({ props })}
			<Button
				{...props}
				variant="ghost"
				size="icon"
				class="relative"
				aria-label="Notifications"
			>
				<Bell class="h-5 w-5" />
				{#if $unreadCount > 0}
					<span
						class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-medium text-white"
					>
						{$unreadCount > 99 ? '99+' : $unreadCount}
					</span>
				{/if}
			</Button>
		{/snippet}
	</Popover.Trigger>
	<Popover.Content class="w-96 p-0" align="end">
		<NotificationPanel onClose={() => (open = false)} />
	</Popover.Content>
</Popover.Root>
