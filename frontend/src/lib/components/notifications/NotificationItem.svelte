<script lang="ts">
	import {
		Bell,
		CheckCircle,
		XCircle,
		UserPlus,
		AtSign,
		Edit,
		Clock,
		Trophy,
		ThumbsDown,
		ArrowRight,
		Clipboard,
		CheckSquare,
		AlertTriangle,
		Settings,
		Archive,
		MoreHorizontal
	} from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { cn } from '$lib/utils';
	import type { Notification } from '$lib/api/notifications';

	interface Props {
		notification: Notification;
		onClick?: () => void;
		onArchive?: () => void;
	}

	let { notification, onClick, onArchive }: Props = $props();

	const iconMap: Record<string, typeof Bell> = {
		bell: Bell,
		'check-circle': CheckCircle,
		'x-circle': XCircle,
		'user-plus': UserPlus,
		users: UserPlus,
		'at-sign': AtSign,
		edit: Edit,
		clock: Clock,
		trophy: Trophy,
		'thumbs-down': ThumbsDown,
		'arrow-right': ArrowRight,
		clipboard: Clipboard,
		'check-square': CheckSquare,
		'alert-triangle': AlertTriangle,
		settings: Settings,
		'arrow-up-circle': ArrowRight,
		trash: XCircle,
		'dollar-sign': Trophy
	};

	const colorMap: Record<string, string> = {
		green: 'text-green-500 bg-green-500/10',
		red: 'text-red-500 bg-red-500/10',
		yellow: 'text-yellow-500 bg-yellow-500/10',
		blue: 'text-blue-500 bg-blue-500/10',
		purple: 'text-purple-500 bg-purple-500/10',
		orange: 'text-orange-500 bg-orange-500/10',
		gray: 'text-gray-500 bg-gray-500/10'
	};

	let Icon = $derived(iconMap[notification.icon || 'bell'] || Bell);
	let iconColorClass = $derived(colorMap[notification.icon_color || 'gray'] || colorMap.gray);
	let isUnread = $derived(!notification.read_at);

	function formatTime(dateString: string): string {
		const date = new Date(dateString);
		const now = new Date();
		const diffMs = now.getTime() - date.getTime();
		const diffMins = Math.floor(diffMs / 60000);
		const diffHours = Math.floor(diffMs / 3600000);
		const diffDays = Math.floor(diffMs / 86400000);

		if (diffMins < 1) return 'Just now';
		if (diffMins < 60) return `${diffMins}m ago`;
		if (diffHours < 24) return `${diffHours}h ago`;
		if (diffDays < 7) return `${diffDays}d ago`;

		return date.toLocaleDateString();
	}
</script>

<div
	class={cn(
		'group relative flex gap-3 px-4 py-3 transition-colors hover:bg-muted/50',
		isUnread && 'bg-blue-50/50 dark:bg-blue-950/20'
	)}
	role="button"
	tabindex="0"
	onclick={onClick}
	onkeydown={(e) => e.key === 'Enter' && onClick?.()}
>
	<!-- Unread indicator -->
	{#if isUnread}
		<div class="absolute left-1 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-blue-500"></div>
	{/if}

	<!-- Icon -->
	<div class={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full', iconColorClass)}>
		<Icon class="h-4 w-4" />
	</div>

	<!-- Content -->
	<div class="min-w-0 flex-1">
		<p class={cn('text-sm leading-tight', isUnread && 'font-medium')}>
			{notification.title}
		</p>
		{#if notification.body}
			<p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
				{notification.body}
			</p>
		{/if}
		<p class="mt-1 text-xs text-muted-foreground">
			{formatTime(notification.created_at)}
		</p>
	</div>

	<!-- Actions -->
	<div class="shrink-0 opacity-0 transition-opacity group-hover:opacity-100">
		<DropdownMenu.Root>
			<DropdownMenu.Trigger>
				{#snippet child({ props })}
					<Button
						{...props}
						variant="ghost"
						size="icon"
						class="h-8 w-8"
						onclick={(e: MouseEvent) => e.stopPropagation()}
					>
						<MoreHorizontal class="h-4 w-4" />
					</Button>
				{/snippet}
			</DropdownMenu.Trigger>
			<DropdownMenu.Content align="end">
				<DropdownMenu.Item
					onclick={(e: MouseEvent) => {
						e.stopPropagation();
						onArchive?.();
					}}
				>
					<Archive class="mr-2 h-4 w-4" />
					Archive
				</DropdownMenu.Item>
			</DropdownMenu.Content>
		</DropdownMenu.Root>
	</div>
</div>
