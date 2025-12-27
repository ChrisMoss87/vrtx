<script lang="ts">
	import type { DocumentCollaborator, ShareSettings, CollaborativeDocument } from '$lib/api/collaborative-documents';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Copy, Link, UserPlus, X, Check, Globe, Lock } from 'lucide-svelte';

	interface Props {
		open: boolean;
		document: CollaborativeDocument | null;
		collaborators: DocumentCollaborator[];
		shareSettings: ShareSettings | null;
		onClose: () => void;
		onAddCollaborator?: (email: string, permission: 'view' | 'comment' | 'edit') => void;
		onRemoveCollaborator?: (userId: number) => void;
		onUpdatePermission?: (userId: number, permission: 'view' | 'comment' | 'edit') => void;
		onEnableSharing?: (permission: 'view' | 'comment' | 'edit') => void;
		onDisableSharing?: () => void;
	}

	let {
		open = $bindable(),
		document: doc,
		collaborators,
		shareSettings,
		onClose,
		onAddCollaborator,
		onRemoveCollaborator,
		onUpdatePermission,
		onEnableSharing,
		onDisableSharing
	}: Props = $props();

	let newEmail = $state('');
	let newPermission = $state<'view' | 'comment' | 'edit'>('view');
	let linkCopied = $state(false);

	function getInitials(name: string) {
		return name
			.split(' ')
			.map((n) => n[0])
			.join('')
			.toUpperCase()
			.slice(0, 2);
	}

	async function copyLink() {
		if (shareSettings?.share_url) {
			await navigator.clipboard.writeText(shareSettings.share_url);
			linkCopied = true;
			setTimeout(() => linkCopied = false, 2000);
		}
	}

	function handleAddCollaborator() {
		if (!newEmail.trim()) return;
		onAddCollaborator?.(newEmail, newPermission);
		newEmail = '';
	}

	const permissionOptions = [
		{ value: 'view', label: 'Can view' },
		{ value: 'comment', label: 'Can comment' },
		{ value: 'edit', label: 'Can edit' }
	];
</script>

<Dialog.Root bind:open>
	<Dialog.Content class="sm:max-w-lg">
		<Dialog.Header>
			<Dialog.Title>Share "{doc?.title}"</Dialog.Title>
			<Dialog.Description>
				Add people or create a link to share this document.
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-6 py-4">
			<!-- Add People -->
			{#if onAddCollaborator}
				<div class="space-y-3">
					<Label>Add people</Label>
					<div class="flex gap-2">
						<Input
							type="email"
							placeholder="Enter email address"
							bind:value={newEmail}
							class="flex-1"
							onkeydown={(e) => e.key === 'Enter' && handleAddCollaborator()}
						/>
						<Select.Root type="single" name="permission" value={newPermission} onValueChange={(v) => newPermission = v as 'view' | 'comment' | 'edit'}>
							<Select.Trigger class="w-32">
								{permissionOptions.find(p => p.value === newPermission)?.label}
							</Select.Trigger>
							<Select.Content>
								{#each permissionOptions as option}
									<Select.Item value={option.value}>{option.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
						<Button onclick={handleAddCollaborator}>
							<UserPlus class="h-4 w-4" />
						</Button>
					</div>
				</div>
			{/if}

			<!-- People with Access -->
			<div class="space-y-3">
				<Label>People with access</Label>
				<div class="space-y-2 max-h-48 overflow-y-auto">
					<!-- Owner -->
					{#if doc?.owner}
						<div class="flex items-center justify-between py-2 px-3 rounded-lg bg-muted/50">
							<div class="flex items-center gap-3">
								<div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-xs font-medium text-primary-foreground">
									{getInitials(doc.owner.name || 'O')}
								</div>
								<div>
									<p class="text-sm font-medium">{doc.owner.name}</p>
									<p class="text-xs text-muted-foreground">{doc.owner.email}</p>
								</div>
							</div>
							<span class="text-xs text-muted-foreground px-2 py-1 bg-muted rounded">Owner</span>
						</div>
					{/if}

					<!-- Collaborators -->
					{#each collaborators as collab}
						<div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-muted/30">
							<div class="flex items-center gap-3">
								<div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center text-xs font-medium">
									{getInitials(collab.user_name || 'U')}
								</div>
								<div>
									<p class="text-sm font-medium">{collab.user_name}</p>
									<p class="text-xs text-muted-foreground">{collab.user_email}</p>
								</div>
							</div>
							<div class="flex items-center gap-2">
								{#if onUpdatePermission}
									<Select.Root type="single" name="collab-permission-{collab.user_id}" value={collab.permission} onValueChange={(v) => onUpdatePermission(collab.user_id, v as 'view' | 'comment' | 'edit')}>
										<Select.Trigger class="h-8 text-xs w-28">
											{permissionOptions.find(p => p.value === collab.permission)?.label}
										</Select.Trigger>
										<Select.Content>
											{#each permissionOptions as option}
												<Select.Item value={option.value}>{option.label}</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>
								{:else}
									<span class="text-xs text-muted-foreground capitalize">{collab.permission}</span>
								{/if}
								{#if onRemoveCollaborator}
									<Button
										variant="ghost"
										size="sm"
										class="h-7 w-7 p-0"
										onclick={() => onRemoveCollaborator(collab.user_id)}
									>
										<X class="h-4 w-4" />
									</Button>
								{/if}
							</div>
						</div>
					{/each}

					{#if collaborators.length === 0 && !doc?.owner}
						<p class="text-sm text-muted-foreground text-center py-4">
							No collaborators yet
						</p>
					{/if}
				</div>
			</div>

			<!-- Link Sharing -->
			<div class="space-y-3">
				<div class="flex items-center justify-between">
					<Label>Get link</Label>
					{#if shareSettings?.enabled}
						<span class="text-xs text-green-600 flex items-center gap-1">
							<Globe class="h-3 w-3" />
							Public link active
						</span>
					{:else}
						<span class="text-xs text-muted-foreground flex items-center gap-1">
							<Lock class="h-3 w-3" />
							Private
						</span>
					{/if}
				</div>

				{#if shareSettings?.enabled}
					<div class="space-y-3">
						<div class="flex gap-2">
							<Input
								value={shareSettings.share_url || ''}
								readonly
								class="text-sm bg-muted"
							/>
							<Button variant="outline" onclick={copyLink}>
								{#if linkCopied}
									<Check class="h-4 w-4 text-green-500" />
								{:else}
									<Copy class="h-4 w-4" />
								{/if}
							</Button>
						</div>
						<div class="flex items-center justify-between text-sm">
							<span class="text-muted-foreground">
								Anyone with the link can <span class="font-medium">{shareSettings.permission}</span>
							</span>
							{#if onDisableSharing}
								<Button
									size="sm"
									variant="ghost"
									class="text-destructive h-7"
									onclick={onDisableSharing}
								>
									Remove link
								</Button>
							{/if}
						</div>
						{#if shareSettings.expires_at}
							<p class="text-xs text-muted-foreground">
								Expires: {new Date(shareSettings.expires_at).toLocaleDateString()}
							</p>
						{/if}
					</div>
				{:else if onEnableSharing}
					<Button variant="outline" class="w-full" onclick={() => onEnableSharing('view')}>
						<Link class="mr-2 h-4 w-4" />
						Create shareable link
					</Button>
				{/if}
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={onClose}>Done</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
