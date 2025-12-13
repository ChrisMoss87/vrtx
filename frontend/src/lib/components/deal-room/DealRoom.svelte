<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Users, FileText, MessageSquare, CheckSquare, BarChart3, Clock, UserPlus, Settings } from 'lucide-svelte';
	import { getDealRoom, type DealRoom as DealRoomType } from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import ActionPlan from './ActionPlan.svelte';
	import DocumentLibrary from './DocumentLibrary.svelte';
	import RoomChat from './RoomChat.svelte';
	import RoomTimeline from './RoomTimeline.svelte';
	import InviteMemberModal from './InviteMemberModal.svelte';
	import EngagementAnalytics from './EngagementAnalytics.svelte';

	export let roomId: number;

	let room: DealRoomType | null = null;
	let loading = true;
	let activeTab: 'overview' | 'actions' | 'documents' | 'messages' | 'analytics' = 'overview';
	let showInviteModal = false;

	onMount(async () => {
		await loadRoom();
	});

	async function loadRoom() {
		loading = true;
		const { data, error } = await tryCatch(getDealRoom(roomId));
		loading = false;

		if (error) {
			toast.error('Failed to load room');
			return;
		}

		room = data;
	}

	function getPublicUrl(): string {
		if (!room) return '';
		return `${window.location.origin}/rooms/${room.slug}`;
	}

	function copyPublicLink(memberToken: string) {
		const url = `${getPublicUrl()}?token=${memberToken}`;
		navigator.clipboard.writeText(url);
		toast.success('Link copied to clipboard');
	}
</script>

{#if loading}
	<div class="flex items-center justify-center h-96">
		<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
	</div>
{:else if room}
	<div class="flex flex-col h-full">
		<!-- Header -->
		<div class="border-b bg-background px-6 py-4">
			<div class="flex items-center justify-between">
				<div>
					<h1 class="text-xl font-bold">{room.name}</h1>
					{#if room.description}
						<p class="text-sm text-muted-foreground mt-1">{room.description}</p>
					{/if}
				</div>
				<div class="flex items-center gap-2">
					<Button variant="outline" size="sm" onclick={() => (showInviteModal = true)}>
						<UserPlus class="mr-2 h-4 w-4" />
						Invite
					</Button>
					<Button variant="outline" size="sm">
						<Settings class="h-4 w-4" />
					</Button>
				</div>
			</div>

			<!-- Progress Bar -->
			{#if room.progress}
				<div class="mt-4 max-w-md">
					<div class="flex justify-between text-sm mb-1">
						<span class="text-muted-foreground">Action Plan Progress</span>
						<span class="font-medium">{room.progress.completed}/{room.progress.total} ({room.progress.percentage}%)</span>
					</div>
					<div class="h-2 bg-muted rounded-full">
						<div
							class="h-2 bg-primary rounded-full transition-all"
							style="width: {room.progress.percentage}%"
						></div>
					</div>
				</div>
			{/if}

			<!-- Tabs -->
			<div class="flex gap-1 mt-4 -mb-4">
				{#each [
					{ key: 'overview', label: 'Overview', icon: Users },
					{ key: 'actions', label: 'Action Plan', icon: CheckSquare },
					{ key: 'documents', label: 'Documents', icon: FileText },
					{ key: 'messages', label: 'Messages', icon: MessageSquare },
					{ key: 'analytics', label: 'Analytics', icon: BarChart3 }
				] as tab}
					<button
						class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-t-lg transition-colors {activeTab === tab.key
							? 'bg-background border border-b-background -mb-px'
							: 'text-muted-foreground hover:text-foreground'}"
						onclick={() => (activeTab = tab.key as typeof activeTab)}
					>
						<svelte:component this={tab.icon} class="h-4 w-4" />
						{tab.label}
					</button>
				{/each}
			</div>
		</div>

		<!-- Content -->
		<div class="flex-1 overflow-auto">
			{#if activeTab === 'overview'}
				<div class="p-6 grid gap-6 md:grid-cols-2">
					<!-- Stakeholders -->
					<div class="rounded-lg border p-4">
						<h3 class="font-semibold flex items-center gap-2 mb-4">
							<Users class="h-4 w-4" />
							Stakeholders
						</h3>
						<div class="space-y-3">
							{#each room.members ?? [] as member}
								<div class="flex items-center justify-between">
									<div class="flex items-center gap-3">
										<div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium">
											{member.name.charAt(0).toUpperCase()}
										</div>
										<div>
											<div class="text-sm font-medium">{member.name}</div>
											<div class="text-xs text-muted-foreground capitalize">
												{member.is_internal ? 'Team' : 'External'} • {member.role}
											</div>
										</div>
									</div>
									{#if member.access_token}
										<Button
											variant="ghost"
											size="sm"
											onclick={() => copyPublicLink(member.access_token!)}
										>
											Copy Link
										</Button>
									{/if}
								</div>
							{/each}
						</div>
						<Button variant="outline" class="w-full mt-4" onclick={() => (showInviteModal = true)}>
							<UserPlus class="mr-2 h-4 w-4" />
							Add Stakeholder
						</Button>
					</div>

					<!-- Recent Activity -->
					<div class="rounded-lg border p-4">
						<h3 class="font-semibold flex items-center gap-2 mb-4">
							<Clock class="h-4 w-4" />
							Recent Activity
						</h3>
						<RoomTimeline {roomId} limit={5} compact />
					</div>

					<!-- Quick Action Plan -->
					<div class="rounded-lg border p-4 md:col-span-2">
						<div class="flex items-center justify-between mb-4">
							<h3 class="font-semibold flex items-center gap-2">
								<CheckSquare class="h-4 w-4" />
								Action Plan
							</h3>
							<Button variant="ghost" size="sm" onclick={() => (activeTab = 'actions')}>
								View All
							</Button>
						</div>
						<ActionPlan {roomId} items={room.action_items?.slice(0, 5) ?? []} compact onUpdate={loadRoom} />
					</div>
				</div>
			{:else if activeTab === 'actions'}
				<div class="p-6">
					<ActionPlan {roomId} items={room.action_items ?? []} onUpdate={loadRoom} />
				</div>
			{:else if activeTab === 'documents'}
				<div class="p-6">
					<DocumentLibrary {roomId} documents={room.documents ?? []} onUpdate={loadRoom} />
				</div>
			{:else if activeTab === 'messages'}
				<RoomChat {roomId} />
			{:else if activeTab === 'analytics'}
				<div class="p-6">
					<EngagementAnalytics {roomId} />
				</div>
			{/if}
		</div>
	</div>

	{#if showInviteModal}
		<InviteMemberModal
			{roomId}
			onClose={() => (showInviteModal = false)}
			onInvited={loadRoom}
		/>
	{/if}
{/if}
