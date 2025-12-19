<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Clock, Video, Phone, MapPin, Settings2, Loader2, AlertCircle } from 'lucide-svelte';
	import {
		getPublicPage,
		type PublicSchedulingPage,
		type PublicMeetingType
	} from '$lib/api/public-scheduling';

	const slug = $derived($page.params.slug);

	let pageData = $state<PublicSchedulingPage | null>(null);
	let meetingTypes = $state<PublicMeetingType[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		await loadPage();
	});

	async function loadPage() {
		if (!slug) return;
		loading = true;
		error = null;
		try {
			const data = await getPublicPage(slug);
			pageData = data.page;
			meetingTypes = data.meeting_types;
		} catch (e: any) {
			error = e.message || 'Failed to load scheduling page';
		} finally {
			loading = false;
		}
	}

	function getLocationIcon(type: string) {
		switch (type) {
			case 'zoom':
			case 'google_meet':
				return Video;
			case 'phone':
				return Phone;
			case 'in_person':
				return MapPin;
			default:
				return Settings2;
		}
	}

	function getLocationLabel(type: string): string {
		const labels: Record<string, string> = {
			in_person: 'In Person',
			phone: 'Phone Call',
			zoom: 'Zoom',
			google_meet: 'Google Meet',
			custom: 'Custom'
		};
		return labels[type] || type;
	}
</script>

<svelte:head>
	<title>{pageData?.name || 'Schedule a Meeting'}</title>
</svelte:head>

<div class="min-h-screen py-12 px-4">
	<div class="mx-auto max-w-2xl">
		{#if loading}
			<div class="flex flex-col items-center justify-center py-24">
				<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
				<p class="mt-4 text-muted-foreground">Loading...</p>
			</div>
		{:else if error}
			<Card.Root>
				<Card.Content class="flex flex-col items-center justify-center py-12">
					<AlertCircle class="mb-4 h-12 w-12 text-destructive" />
					<h2 class="mb-2 text-xl font-semibold">Page Not Found</h2>
					<p class="text-muted-foreground">{error}</p>
				</Card.Content>
			</Card.Root>
		{:else if pageData}
			<!-- Header -->
			<div class="mb-8 text-center">
				{#if pageData.branding?.logo_url}
					<img
						src={pageData.branding.logo_url}
						alt={pageData.name}
						class="mx-auto mb-4 h-16 w-16 rounded-full"
					/>
				{:else}
					<div
						class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full text-2xl font-bold text-white"
						style="background-color: {pageData.branding?.primary_color || '#3B82F6'}"
					>
						{pageData.host.name.charAt(0).toUpperCase()}
					</div>
				{/if}
				<h1 class="text-2xl font-bold">{pageData.host.name}</h1>
				<p class="text-lg text-muted-foreground">{pageData.name}</p>
				{#if pageData.description}
					<p class="mt-2 text-muted-foreground">{pageData.description}</p>
				{/if}
			</div>

			<!-- Meeting Types -->
			{#if meetingTypes.length === 0}
				<Card.Root>
					<Card.Content class="py-12 text-center">
						<p class="text-muted-foreground">No meeting types available</p>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="space-y-4">
					{#each meetingTypes as meetingType}
						{@const LocationIcon = getLocationIcon(meetingType.location_type)}
						<Card.Root
							class="cursor-pointer transition-all hover:border-primary hover:shadow-md"
							onclick={() => goto(`/schedule/${slug}/${meetingType.slug}`)}
						>
							<Card.Content class="p-6">
								<div class="flex items-start gap-4">
									<div
										class="mt-1 h-3 w-3 rounded-full flex-shrink-0"
										style="background-color: {meetingType.color}"
									></div>
									<div class="flex-1 min-w-0">
										<h3 class="text-lg font-semibold">{meetingType.name}</h3>
										{#if meetingType.description}
											<p class="mt-1 text-muted-foreground line-clamp-2">
												{meetingType.description}
											</p>
										{/if}
										<div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
											<span class="flex items-center gap-1.5">
												<Clock class="h-4 w-4" />
												{meetingType.duration_minutes} minutes
											</span>
											<span class="flex items-center gap-1.5">
												<LocationIcon class="h-4 w-4" />
												{getLocationLabel(meetingType.location_type)}
											</span>
										</div>
									</div>
								</div>
							</Card.Content>
						</Card.Root>
					{/each}
				</div>
			{/if}

			<!-- Footer -->
			<div class="mt-8 text-center">
				<p class="text-xs text-muted-foreground">
					Powered by <span class="font-medium">VRTX</span>
				</p>
			</div>
		{/if}
	</div>
</div>
