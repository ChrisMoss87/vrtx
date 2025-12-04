<script lang="ts">
	import { FileText, Play, Trash2, X } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';

	interface DraftInfo {
		id?: number;
		name?: string;
		currentStepIndex: number;
		totalSteps: number;
		completionPercentage: number;
		lastSaved: Date;
	}

	interface Props {
		draft: DraftInfo;
		onResume?: () => void;
		onDiscard?: () => void;
		onDismiss?: () => void;
	}

	let { draft, onResume = () => {}, onDiscard = () => {}, onDismiss = () => {} }: Props = $props();

	function formatDate(date: Date): string {
		const now = new Date();
		const diff = now.getTime() - date.getTime();
		const minutes = Math.floor(diff / (1000 * 60));
		const hours = Math.floor(minutes / 60);
		const days = Math.floor(hours / 24);

		if (minutes < 1) return 'just now';
		if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
		if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
		if (days === 1) return 'yesterday';
		if (days < 7) return `${days} days ago`;
		return date.toLocaleDateString();
	}
</script>

<Card.Root class="border-primary/20 bg-primary/5">
	<Card.Content class="p-4">
		<div class="flex items-start justify-between gap-4">
			<div class="flex items-start gap-3">
				<div class="rounded-lg bg-primary/10 p-2">
					<FileText class="h-5 w-5 text-primary" />
				</div>
				<div>
					<h4 class="font-medium">
						{draft.name ?? 'Resume your draft'}
					</h4>
					<p class="mt-1 text-sm text-muted-foreground">
						You have an unsaved draft from {formatDate(draft.lastSaved)}
					</p>
					<div class="mt-2 flex items-center gap-4 text-sm">
						<span class="text-muted-foreground">
							Step {draft.currentStepIndex + 1} of {draft.totalSteps}
						</span>
						<div class="flex items-center gap-2">
							<div class="h-1.5 w-16 rounded-full bg-muted">
								<div
									class="h-full rounded-full bg-primary"
									style="width: {draft.completionPercentage}%"
								></div>
							</div>
							<span class="text-muted-foreground">{draft.completionPercentage}%</span>
						</div>
					</div>
				</div>
			</div>

			<Button variant="ghost" size="icon" class="h-8 w-8" onclick={onDismiss}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<div class="mt-4 flex items-center gap-2">
			<Button onclick={onResume} size="sm">
				<Play class="mr-2 h-4 w-4" />
				Continue
			</Button>
			<Button variant="outline" size="sm" onclick={onDiscard}>
				<Trash2 class="mr-2 h-4 w-4" />
				Start Over
			</Button>
		</div>
	</Card.Content>
</Card.Root>
