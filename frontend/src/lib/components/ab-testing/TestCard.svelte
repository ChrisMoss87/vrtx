<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import {
		Play,
		Pause,
		Square,
		MoreVertical,
		Eye,
		Edit,
		Trash2,
		FlaskConical,
		Trophy
	} from 'lucide-svelte';
	import { getStatusColor, getTestTypeLabel, getGoalLabel } from '$lib/api/ab-tests';
	import type { AbTest } from '$lib/api/ab-tests';
	import { goto } from '$app/navigation';

	interface Props {
		test: AbTest;
		onStart?: () => void;
		onPause?: () => void;
		onResume?: () => void;
		onComplete?: () => void;
		onDelete?: () => void;
	}

	let { test, onStart, onPause, onResume, onComplete, onDelete }: Props = $props();

	const statusConfig = $derived.by(() => {
		switch (test.status) {
			case 'draft':
				return { label: 'Draft', class: 'bg-gray-100 text-gray-700' };
			case 'running':
				return { label: 'Running', class: 'bg-green-100 text-green-700' };
			case 'paused':
				return { label: 'Paused', class: 'bg-yellow-100 text-yellow-700' };
			case 'completed':
				return { label: 'Completed', class: 'bg-blue-100 text-blue-700' };
			default:
				return { label: test.status, class: 'bg-gray-100 text-gray-700' };
		}
	});

	function formatDate(date: string | null): string {
		if (!date) return '-';
		return new Date(date).toLocaleDateString();
	}
</script>

<Card.Root class="group relative">
	<Card.Header class="pb-2">
		<div class="flex items-start justify-between">
			<div class="flex items-center gap-2">
				<FlaskConical class="h-5 w-5 text-muted-foreground" />
				<div>
					<Card.Title class="text-base">{test.name}</Card.Title>
					<Card.Description class="line-clamp-1">
						{test.description || 'No description'}
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
						<DropdownMenu.Item onSelect={() => goto(`/ab-tests/${test.id}`)}>
							<Eye class="mr-2 h-4 w-4" />
							View Details
						</DropdownMenu.Item>
						{#if test.status === 'draft' || test.status === 'paused'}
							<DropdownMenu.Item onSelect={() => goto(`/ab-tests/${test.id}/edit`)}>
								<Edit class="mr-2 h-4 w-4" />
								Edit Test
							</DropdownMenu.Item>
						{/if}
						<DropdownMenu.Separator />
						{#if test.status === 'draft' && onStart}
							<DropdownMenu.Item onSelect={onStart}>
								<Play class="mr-2 h-4 w-4" />
								Start Test
							</DropdownMenu.Item>
						{/if}
						{#if test.status === 'running' && onPause}
							<DropdownMenu.Item onSelect={onPause}>
								<Pause class="mr-2 h-4 w-4" />
								Pause Test
							</DropdownMenu.Item>
						{/if}
						{#if test.status === 'paused' && onResume}
							<DropdownMenu.Item onSelect={onResume}>
								<Play class="mr-2 h-4 w-4" />
								Resume Test
							</DropdownMenu.Item>
						{/if}
						{#if (test.status === 'running' || test.status === 'paused') && onComplete}
							<DropdownMenu.Item onSelect={onComplete}>
								<Square class="mr-2 h-4 w-4" />
								End Test
							</DropdownMenu.Item>
						{/if}
						{#if test.status !== 'running' && onDelete}
							<DropdownMenu.Separator />
							<DropdownMenu.Item onSelect={onDelete} class="text-destructive">
								<Trash2 class="mr-2 h-4 w-4" />
								Delete Test
							</DropdownMenu.Item>
						{/if}
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			</div>
		</div>
	</Card.Header>
	<Card.Content class="pb-4">
		<div class="grid grid-cols-2 gap-4 text-sm">
			<div>
				<span class="text-muted-foreground">Type:</span>
				<span class="ml-1 font-medium">{getTestTypeLabel(test.type)}</span>
			</div>
			<div>
				<span class="text-muted-foreground">Goal:</span>
				<span class="ml-1 font-medium">{getGoalLabel(test.goal)}</span>
			</div>
			<div>
				<span class="text-muted-foreground">Variants:</span>
				<span class="ml-1 font-medium">{test.variants?.length || 0}</span>
			</div>
			<div>
				<span class="text-muted-foreground">Confidence:</span>
				<span class="ml-1 font-medium">{test.confidence_level}%</span>
			</div>
		</div>

		{#if test.winner_variant}
			<div class="mt-3 flex items-center gap-2 rounded-md bg-green-50 p-2 text-sm text-green-800">
				<Trophy class="h-4 w-4" />
				<span>Winner: <strong>{test.winner_variant.name}</strong></span>
			</div>
		{/if}

		<div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
			<span>Created {formatDate(test.created_at)}</span>
			{#if test.started_at}
				<span>Started {formatDate(test.started_at)}</span>
			{/if}
			{#if test.ended_at}
				<span>Ended {formatDate(test.ended_at)}</span>
			{/if}
		</div>
	</Card.Content>
</Card.Root>
