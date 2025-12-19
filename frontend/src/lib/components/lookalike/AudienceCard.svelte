<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Progress } from '$lib/components/ui/progress';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import {
		Users,
		MoreVertical,
		Eye,
		Edit,
		Trash2,
		Play,
		Download,
		RefreshCcw
	} from 'lucide-svelte';
	import { goto } from '$app/navigation';
	import type { LookalikeAudience } from '$lib/api/lookalike';
	import { getStatusColor, getSourceTypeLabel } from '$lib/api/lookalike';

	interface Props {
		audience: LookalikeAudience;
		onBuild?: () => void;
		onDelete?: () => void;
	}

	let { audience, onBuild, onDelete }: Props = $props();

	const statusConfig = $derived.by(() => {
		switch (audience.status) {
			case 'draft':
				return { label: 'Draft', class: 'bg-gray-100 text-gray-700' };
			case 'building':
				return { label: 'Building', class: 'bg-blue-100 text-blue-700' };
			case 'ready':
				return { label: 'Ready', class: 'bg-green-100 text-green-700' };
			case 'expired':
				return { label: 'Expired', class: 'bg-yellow-100 text-yellow-700' };
			default:
				return { label: audience.status, class: 'bg-gray-100 text-gray-700' };
		}
	});

	function formatDate(date: string | null): string {
		if (!date) return '-';
		return new Date(date).toLocaleDateString();
	}

	function formatNumber(num: number): string {
		return num.toLocaleString();
	}

	const latestJob = $derived(audience.build_jobs?.[0]);
</script>

<Card.Root class="group relative">
	<Card.Header class="pb-2">
		<div class="flex items-start justify-between">
			<div class="flex items-center gap-2">
				<Users class="h-5 w-5 text-muted-foreground" />
				<div>
					<Card.Title class="text-base">{audience.name}</Card.Title>
					<Card.Description class="line-clamp-1">
						{audience.description || 'No description'}
					</Card.Description>
				</div>
			</div>
			<div class="flex items-center gap-2">
				<Badge class={statusConfig.class}>{statusConfig.label}</Badge>
				<DropdownMenu.Root>
					<DropdownMenu.Trigger>
						<Button variant="ghost" size="icon" class="h-8 w-8">
							<MoreVertical class="h-4 w-4" />
						</Button>
					</DropdownMenu.Trigger>
					<DropdownMenu.Content align="end">
						<DropdownMenu.Item onSelect={() => goto(`/lookalike-audiences/${audience.id}`)}>
							<Eye class="mr-2 h-4 w-4" />
							View Details
						</DropdownMenu.Item>
						{#if audience.status !== 'building'}
							<DropdownMenu.Item onSelect={() => goto(`/lookalike-audiences/${audience.id}/edit`)}>
								<Edit class="mr-2 h-4 w-4" />
								Edit
							</DropdownMenu.Item>
						{/if}
						<DropdownMenu.Separator />
						{#if (audience.status === 'draft' || audience.status === 'ready' || audience.status === 'expired') && onBuild}
							<DropdownMenu.Item onSelect={onBuild}>
								<Play class="mr-2 h-4 w-4" />
								{audience.status === 'draft' ? 'Build Audience' : 'Rebuild'}
							</DropdownMenu.Item>
						{/if}
						{#if audience.status === 'ready'}
							<DropdownMenu.Item onSelect={() => goto(`/lookalike-audiences/${audience.id}?export=true`)}>
								<Download class="mr-2 h-4 w-4" />
								Export
							</DropdownMenu.Item>
						{/if}
						{#if audience.status !== 'building' && onDelete}
							<DropdownMenu.Separator />
							<DropdownMenu.Item onSelect={onDelete} class="text-destructive">
								<Trash2 class="mr-2 h-4 w-4" />
								Delete
							</DropdownMenu.Item>
						{/if}
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			</div>
		</div>
	</Card.Header>
	<Card.Content class="pb-4">
		{#if audience.status === 'building' && latestJob}
			<div class="space-y-2 mb-4">
				<div class="flex justify-between text-sm">
					<span>Building...</span>
					<span>{latestJob.progress}%</span>
				</div>
				<Progress value={latestJob.progress} />
				<div class="text-xs text-muted-foreground">
					{formatNumber(latestJob.records_processed)} records processed,
					{formatNumber(latestJob.matches_found)} matches found
				</div>
			</div>
		{/if}

		<div class="grid grid-cols-2 gap-4 text-sm">
			<div>
				<span class="text-muted-foreground">Source:</span>
				<span class="ml-1 font-medium">{getSourceTypeLabel(audience.source_type)}</span>
			</div>
			<div>
				<span class="text-muted-foreground">Matches:</span>
				<span class="ml-1 font-medium">{formatNumber(audience.matches_count || audience.match_count)}</span>
			</div>
			<div>
				<span class="text-muted-foreground">Min Score:</span>
				<span class="ml-1 font-medium">{audience.min_similarity_score}%</span>
			</div>
			<div>
				<span class="text-muted-foreground">Auto Refresh:</span>
				<span class="ml-1 font-medium">
					{audience.auto_refresh ? audience.refresh_frequency || 'On' : 'Off'}
				</span>
			</div>
		</div>

		<div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
			<span>Created {formatDate(audience.created_at)}</span>
			{#if audience.last_built_at}
				<span>Last built {formatDate(audience.last_built_at)}</span>
			{/if}
		</div>
	</Card.Content>
</Card.Root>
