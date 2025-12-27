<script lang="ts">
	import * as Tooltip from '$lib/components/ui/tooltip';

	interface Collaborator {
		id: number;
		name: string;
		color: string;
		permission?: string;
	}

	interface Props {
		collaborators: Collaborator[];
		maxVisible?: number;
		size?: 'sm' | 'md' | 'lg';
	}

	let { collaborators, maxVisible = 5, size = 'md' }: Props = $props();

	const sizeClasses = {
		sm: 'w-6 h-6 text-[10px]',
		md: 'w-8 h-8 text-xs',
		lg: 'w-10 h-10 text-sm'
	};

	const visibleCollaborators = $derived(collaborators.slice(0, maxVisible));
	const remainingCount = $derived(collaborators.length - maxVisible);

	function getInitials(name: string) {
		return name
			.split(' ')
			.map((n) => n[0])
			.join('')
			.toUpperCase()
			.slice(0, 2);
	}
</script>

{#if collaborators.length > 0}
	<div class="flex items-center -space-x-2">
		{#each visibleCollaborators as collaborator}
			<Tooltip.Root>
				<Tooltip.Trigger>
					<div
						class="{sizeClasses[size]} rounded-full flex items-center justify-center font-medium text-white border-2 border-background cursor-default"
						style="background-color: {collaborator.color}"
					>
						{getInitials(collaborator.name)}
					</div>
				</Tooltip.Trigger>
				<Tooltip.Content>
					<p>{collaborator.name}</p>
					{#if collaborator.permission}
						<p class="text-xs text-muted-foreground capitalize">{collaborator.permission}</p>
					{/if}
				</Tooltip.Content>
			</Tooltip.Root>
		{/each}

		{#if remainingCount > 0}
			<Tooltip.Root>
				<Tooltip.Trigger>
					<div
						class="{sizeClasses[size]} rounded-full flex items-center justify-center font-medium bg-muted border-2 border-background cursor-default"
					>
						+{remainingCount}
					</div>
				</Tooltip.Trigger>
				<Tooltip.Content>
					<p>{remainingCount} more {remainingCount === 1 ? 'person' : 'people'}</p>
				</Tooltip.Content>
			</Tooltip.Root>
		{/if}
	</div>
{/if}
