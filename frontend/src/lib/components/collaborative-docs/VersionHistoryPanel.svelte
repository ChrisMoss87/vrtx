<script lang="ts">
	import type { DocumentVersion } from '$lib/api/collaborative-documents';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { History, RotateCcw, Clock, User, Tag } from 'lucide-svelte';

	interface Props {
		versions: DocumentVersion[];
		loading?: boolean;
		onCreateVersion?: (label: string) => void;
		onRestoreVersion?: (versionNumber: number) => void;
		onPreviewVersion?: (versionNumber: number) => void;
	}

	let { versions, loading = false, onCreateVersion, onRestoreVersion, onPreviewVersion }: Props = $props();

	let newVersionLabel = $state('');

	function handleCreateVersion() {
		if (!newVersionLabel.trim()) return;
		onCreateVersion?.(newVersionLabel);
		newVersionLabel = '';
	}

	function formatDate(dateStr: string | null) {
		if (!dateStr) return '';
		const date = new Date(dateStr);
		const now = new Date();
		const diff = now.getTime() - date.getTime();
		const minutes = Math.floor(diff / (1000 * 60));
		const hours = Math.floor(diff / (1000 * 60 * 60));
		const days = Math.floor(diff / (1000 * 60 * 60 * 24));

		if (minutes < 1) return 'Just now';
		if (minutes < 60) return `${minutes} minutes ago`;
		if (hours < 24) return `${hours} hours ago`;
		if (days < 7) return `${days} days ago`;
		return date.toLocaleString();
	}

	function getVersionLabel(version: DocumentVersion) {
		if (version.label) return version.label;
		if (version.is_auto_save) return `Auto-save #${version.version_number}`;
		return `Version ${version.version_number}`;
	}
</script>

<div class="space-y-4">
	<!-- Create new version -->
	{#if onCreateVersion}
		<div class="flex gap-2">
			<Input
				placeholder="Version label (e.g., 'Final draft')"
				bind:value={newVersionLabel}
				onkeydown={(e) => e.key === 'Enter' && handleCreateVersion()}
			/>
			<Button onclick={handleCreateVersion} disabled={!newVersionLabel.trim()}>
				<Tag class="h-4 w-4 mr-1" />
				Save
			</Button>
		</div>
	{/if}

	<!-- Loading state -->
	{#if loading}
		<div class="flex justify-center py-8">
			<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
		</div>
	{:else if versions.length === 0}
		<!-- Empty state -->
		<div class="text-center py-8 text-muted-foreground">
			<History class="mx-auto h-10 w-10 opacity-50 mb-3" />
			<p class="text-sm font-medium">No version history</p>
			<p class="text-xs mt-1">Save a named version to track changes</p>
		</div>
	{:else}
		<!-- Version list -->
		<div class="space-y-2">
			{#each versions as version, i}
				{@const isLatest = i === 0}
				<div
					class="relative flex items-start gap-3 p-3 rounded-lg border hover:bg-accent/50 transition-colors {isLatest ? 'border-primary/50 bg-primary/5' : ''}"
				>
					<!-- Timeline connector -->
					{#if i < versions.length - 1}
						<div class="absolute left-5 top-12 bottom-0 w-px bg-border -translate-x-1/2"></div>
					{/if}

					<!-- Version indicator -->
					<div class="relative z-10 mt-0.5">
						<div class="w-4 h-4 rounded-full {version.is_auto_save ? 'bg-muted border-2 border-muted-foreground/30' : 'bg-primary'}"></div>
					</div>

					<!-- Version details -->
					<div class="flex-1 min-w-0">
						<div class="flex items-start justify-between gap-2">
							<div>
								<p class="text-sm font-medium flex items-center gap-2">
									{getVersionLabel(version)}
									{#if isLatest}
										<span class="px-1.5 py-0.5 text-xs bg-primary/10 text-primary rounded">
											Current
										</span>
									{/if}
								</p>
								<div class="flex items-center gap-3 mt-1 text-xs text-muted-foreground">
									<span class="flex items-center gap-1">
										<Clock class="h-3 w-3" />
										{formatDate(version.created_at)}
									</span>
									{#if version.created_by_name}
										<span class="flex items-center gap-1">
											<User class="h-3 w-3" />
											{version.created_by_name}
										</span>
									{/if}
								</div>
							</div>

							<div class="flex items-center gap-1">
								{#if onPreviewVersion}
									<Button
										size="sm"
										variant="ghost"
										class="h-7 text-xs"
										onclick={() => onPreviewVersion(version.version_number)}
									>
										Preview
									</Button>
								{/if}
								{#if onRestoreVersion && !isLatest}
									<Button
										size="sm"
										variant="outline"
										class="h-7 text-xs"
										onclick={() => onRestoreVersion(version.version_number)}
									>
										<RotateCcw class="h-3 w-3 mr-1" />
										Restore
									</Button>
								{/if}
							</div>
						</div>
					</div>
				</div>
			{/each}
		</div>
	{/if}
</div>
