<script lang="ts">
	import * as Card from '$lib/components/ui/card';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { CheckSquare, Calendar, Clock } from 'lucide-svelte';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Badge } from '$lib/components/ui/badge';

	interface Task {
		id: number;
		subject: string;
		description?: string;
		due_date?: string;
		priority?: 'low' | 'normal' | 'high';
		is_completed?: boolean;
	}

	interface Props {
		title: string;
		data: Task[] | null;
		loading?: boolean;
		onToggle?: (taskId: number, completed: boolean) => void;
	}

	let { title, data, loading = false, onToggle }: Props = $props();

	function formatDueDate(dateStr?: string): string {
		if (!dateStr) return '';
		const date = new Date(dateStr);
		const today = new Date();
		const tomorrow = new Date(today);
		tomorrow.setDate(tomorrow.getDate() + 1);

		if (date.toDateString() === today.toDateString()) return 'Today';
		if (date.toDateString() === tomorrow.toDateString()) return 'Tomorrow';

		return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
	}

	function isOverdue(dateStr?: string): boolean {
		if (!dateStr) return false;
		const date = new Date(dateStr);
		const today = new Date();
		today.setHours(0, 0, 0, 0);
		return date < today;
	}

	function getPriorityColor(priority?: string): string {
		switch (priority) {
			case 'high':
				return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
			case 'low':
				return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
			default:
				return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
		}
	}
</script>

<Card.Root class="h-full">
	<Card.Header class="pb-2">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<CheckSquare class="h-4 w-4 text-muted-foreground" />
				<Card.Title class="text-sm font-medium">{title}</Card.Title>
			</div>
			{#if data && data.length > 0}
				<Badge variant="secondary" class="text-xs">
					{data.filter((t) => !t.is_completed).length} open
				</Badge>
			{/if}
		</div>
	</Card.Header>
	<Card.Content class="p-0">
		{#if loading}
			<div class="animate-pulse space-y-3 p-4">
				{#each [1, 2, 3, 4] as _}
					<div class="flex gap-3">
						<div class="h-5 w-5 rounded bg-muted"></div>
						<div class="flex-1 space-y-1">
							<div class="h-4 w-3/4 rounded bg-muted"></div>
							<div class="h-3 w-1/4 rounded bg-muted"></div>
						</div>
					</div>
				{/each}
			</div>
		{:else if !data || data.length === 0}
			<div class="flex flex-col items-center justify-center py-8 text-muted-foreground">
				<CheckSquare class="mb-2 h-8 w-8" />
				<p class="text-sm">No tasks</p>
			</div>
		{:else}
			<ScrollArea class="max-h-[300px]">
				<div class="space-y-1 p-4">
					{#each data as task}
						<div
							class="flex items-start gap-3 rounded-lg p-2 transition-colors hover:bg-muted/50"
						>
							<Checkbox
								checked={task.is_completed}
								onCheckedChange={(checked) => onToggle?.(task.id, !!checked)}
								class="mt-0.5"
							/>
							<div class="min-w-0 flex-1">
								<p
									class="text-sm {task.is_completed
										? 'text-muted-foreground line-through'
										: ''}"
								>
									{task.subject}
								</p>
								<div class="mt-1 flex flex-wrap items-center gap-2">
									{#if task.due_date}
										<span
											class="flex items-center gap-1 text-xs {isOverdue(task.due_date) &&
											!task.is_completed
												? 'text-red-600'
												: 'text-muted-foreground'}"
										>
											<Clock class="h-3 w-3" />
											{formatDueDate(task.due_date)}
										</span>
									{/if}
									{#if task.priority && task.priority !== 'normal'}
										<Badge variant="secondary" class="text-[10px] {getPriorityColor(task.priority)}">
											{task.priority}
										</Badge>
									{/if}
								</div>
							</div>
						</div>
					{/each}
				</div>
			</ScrollArea>
		{/if}
	</Card.Content>
</Card.Root>
