<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { goto } from '$app/navigation';
	import {
		Link,
		ExternalLink,
		Users,
		Building2,
		Target,
		FileText,
		Calendar,
		Mail,
		Phone,
		Settings,
		BarChart2,
		Briefcase,
		type Icon
	} from 'lucide-svelte';

	interface QuickLink {
		id: string | number;
		label: string;
		url: string;
		icon?: string;
		description?: string;
		external?: boolean;
		color?: string;
	}

	interface Props {
		title: string;
		data: {
			links: QuickLink[];
			columns?: 1 | 2 | 3;
		} | null;
		loading?: boolean;
	}

	let { title, data, loading = false }: Props = $props();

	const iconMap: Record<string, typeof Icon> = {
		users: Users,
		contacts: Users,
		companies: Building2,
		accounts: Building2,
		deals: Target,
		opportunities: Target,
		documents: FileText,
		reports: BarChart2,
		calendar: Calendar,
		email: Mail,
		phone: Phone,
		settings: Settings,
		projects: Briefcase,
		link: Link
	};

	function getIcon(iconName?: string) {
		if (!iconName) return Link;
		return iconMap[iconName.toLowerCase()] || Link;
	}

	function handleClick(link: QuickLink) {
		if (link.external) {
			window.open(link.url, '_blank', 'noopener,noreferrer');
		} else {
			goto(link.url);
		}
	}

	const gridCols = $derived(() => {
		const cols = data?.columns || 2;
		if (cols === 1) return 'grid-cols-1';
		if (cols === 3) return 'grid-cols-3';
		return 'grid-cols-2';
	});
</script>

<Card.Root class="flex h-full flex-col">
	<Card.Header class="pb-2">
		<div class="flex items-center gap-2">
			<Link class="h-4 w-4 text-muted-foreground" />
			<Card.Title class="text-sm font-medium">{title}</Card.Title>
		</div>
	</Card.Header>
	<Card.Content class="flex-1 overflow-auto">
		{#if loading}
			<div class="grid gap-2 {gridCols()}">
				{#each [1, 2, 3, 4] as _}
					<div class="h-16 animate-pulse rounded-lg bg-muted"></div>
				{/each}
			</div>
		{:else if data?.links && data.links.length > 0}
			<div class="grid gap-2 {gridCols()}">
				{#each data.links as link (link.id)}
					{@const IconComponent = getIcon(link.icon)}
					<button
						type="button"
						class="group flex items-center gap-3 rounded-lg border p-3 text-left transition-all hover:border-primary hover:bg-muted/50 hover:shadow-sm"
						onclick={() => handleClick(link)}
					>
						<div
							class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors {!link.color ? 'bg-primary/10 text-primary' : ''}"
							style={link.color ? `background-color: ${link.color}20; color: ${link.color}` : ''}
						>
							<IconComponent class="h-4 w-4" />
						</div>
						<div class="min-w-0 flex-1">
							<div class="flex items-center gap-1">
								<span class="truncate text-sm font-medium">{link.label}</span>
								{#if link.external}
									<ExternalLink class="h-3 w-3 shrink-0 text-muted-foreground" />
								{/if}
							</div>
							{#if link.description}
								<div class="truncate text-xs text-muted-foreground">
									{link.description}
								</div>
							{/if}
						</div>
					</button>
				{/each}
			</div>
		{:else}
			<div class="flex h-full items-center justify-center py-8 text-sm text-muted-foreground">
				No quick links configured
			</div>
		{/if}
	</Card.Content>
</Card.Root>
