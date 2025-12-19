<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Tabs from '$lib/components/ui/tabs';
	import {
		ArrowLeft,
		Loader2,
		Check,
		X,
		Plus,
		Pencil,
		Trash2,
		Clock,
		Video,
		Phone,
		MapPin,
		Settings2,
		GripVertical,
		Eye
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getSchedulingPage,
		updateSchedulingPage,
		checkSlugAvailability,
		getMeetingTypes,
		createMeetingType,
		updateMeetingType,
		deleteMeetingType,
		getDefaultMeetingTypeSettings,
		MEETING_COLORS,
		DURATION_OPTIONS,
		LOCATION_TYPE_OPTIONS,
		getLocationTypeLabel,
		type SchedulingPage,
		type MeetingType,
		type MeetingTypeSettings,
		type MeetingTypeQuestion,
		type LocationType
	} from '$lib/api/scheduling';

	const pageId = $derived(Number($page.params.id));

	let schedulingPage = $state<SchedulingPage | null>(null);
	let meetingTypes = $state<MeetingType[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let activeTab = $state('details');

	// Page form state
	let name = $state('');
	let slug = $state('');
	let description = $state('');
	let timezone = $state('');
	let isActive = $state(true);
	let primaryColor = $state('#3B82F6');

	// Slug validation
	let slugChecking = $state(false);
	let slugAvailable = $state<boolean | null>(null);
	let slugTimeout: ReturnType<typeof setTimeout> | null = null;
	let originalSlug = '';

	// Meeting type dialog
	let meetingTypeDialogOpen = $state(false);
	let editingMeetingType = $state<MeetingType | null>(null);
	let meetingTypeForm = $state({
		name: '',
		slug: '',
		description: '',
		duration_minutes: 30,
		location_type: 'zoom' as LocationType,
		location_details: '',
		color: '#3B82F6',
		is_active: true,
		settings: getDefaultMeetingTypeSettings()
	});
	let savingMeetingType = $state(false);

	// Delete meeting type dialog
	let deleteMeetingTypeDialogOpen = $state(false);
	let meetingTypeToDelete = $state<MeetingType | null>(null);
	let deletingMeetingType = $state(false);

	const timezones = [
		'America/New_York',
		'America/Chicago',
		'America/Denver',
		'America/Los_Angeles',
		'America/Phoenix',
		'America/Anchorage',
		'Pacific/Honolulu',
		'Europe/London',
		'Europe/Paris',
		'Europe/Berlin',
		'Asia/Tokyo',
		'Asia/Shanghai',
		'Asia/Singapore',
		'Australia/Sydney',
		'Pacific/Auckland'
	];

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [pageData, typesData] = await Promise.all([
				getSchedulingPage(pageId),
				getMeetingTypes(pageId)
			]);

			schedulingPage = pageData;
			meetingTypes = typesData;

			// Populate form
			name = pageData.name;
			slug = pageData.slug;
			originalSlug = pageData.slug;
			description = pageData.description || '';
			timezone = pageData.timezone;
			isActive = pageData.is_active;
			primaryColor = pageData.branding?.primary_color || '#3B82F6';
		} catch (error) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load scheduling page');
			goto('/settings/scheduling');
		} finally {
			loading = false;
		}
	}

	function generateSlug(text: string): string {
		return text
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-|-$/g, '');
	}

	function handleSlugChange(e: Event) {
		const target = e.target as HTMLInputElement;
		slug = generateSlug(target.value);
		if (slug !== originalSlug) {
			checkSlug();
		} else {
			slugAvailable = true;
		}
	}

	function checkSlug() {
		if (slugTimeout) clearTimeout(slugTimeout);
		if (!slug || slug === originalSlug) {
			slugAvailable = null;
			return;
		}

		slugChecking = true;
		slugTimeout = setTimeout(async () => {
			try {
				const result = await checkSlugAvailability(slug, pageId);
				slugAvailable = result.available;
			} catch (error) {
				console.error('Failed to check slug:', error);
			} finally {
				slugChecking = false;
			}
		}, 300);
	}

	async function handleSavePage() {
		if (!name.trim()) {
			toast.error('Please enter a name');
			return;
		}

		if (!slug.trim()) {
			toast.error('Please enter a URL slug');
			return;
		}

		if (slugAvailable === false) {
			toast.error('This URL is already taken');
			return;
		}

		saving = true;
		try {
			await updateSchedulingPage(pageId, {
				name: name.trim(),
				slug: slug.trim(),
				description: description.trim() || undefined,
				timezone,
				is_active: isActive,
				branding: {
					primary_color: primaryColor
				}
			});

			originalSlug = slug;
			toast.success('Page updated');
		} catch (error: any) {
			console.error('Failed to update page:', error);
			toast.error(error.response?.data?.message || 'Failed to update page');
		} finally {
			saving = false;
		}
	}

	function openMeetingTypeDialog(meetingType?: MeetingType) {
		if (meetingType) {
			editingMeetingType = meetingType;
			meetingTypeForm = {
				name: meetingType.name,
				slug: meetingType.slug,
				description: meetingType.description || '',
				duration_minutes: meetingType.duration_minutes,
				location_type: meetingType.location_type,
				location_details: meetingType.location_details || '',
				color: meetingType.color,
				is_active: meetingType.is_active,
				settings: { ...meetingType.settings }
			};
		} else {
			editingMeetingType = null;
			meetingTypeForm = {
				name: '',
				slug: '',
				description: '',
				duration_minutes: 30,
				location_type: 'zoom',
				location_details: '',
				color: '#3B82F6',
				is_active: true,
				settings: getDefaultMeetingTypeSettings()
			};
		}
		meetingTypeDialogOpen = true;
	}

	async function handleSaveMeetingType() {
		if (!meetingTypeForm.name.trim()) {
			toast.error('Please enter a name');
			return;
		}

		savingMeetingType = true;
		try {
			if (editingMeetingType) {
				await updateMeetingType(pageId, editingMeetingType.id, {
					name: meetingTypeForm.name.trim(),
					slug: meetingTypeForm.slug.trim() || undefined,
					description: meetingTypeForm.description.trim() || undefined,
					duration_minutes: meetingTypeForm.duration_minutes,
					location_type: meetingTypeForm.location_type,
					location_details: meetingTypeForm.location_details.trim() || undefined,
					color: meetingTypeForm.color,
					is_active: meetingTypeForm.is_active,
					settings: meetingTypeForm.settings
				});
				toast.success('Meeting type updated');
			} else {
				await createMeetingType(pageId, {
					name: meetingTypeForm.name.trim(),
					slug: meetingTypeForm.slug.trim() || undefined,
					description: meetingTypeForm.description.trim() || undefined,
					duration_minutes: meetingTypeForm.duration_minutes,
					location_type: meetingTypeForm.location_type,
					location_details: meetingTypeForm.location_details.trim() || undefined,
					color: meetingTypeForm.color,
					is_active: meetingTypeForm.is_active,
					settings: meetingTypeForm.settings
				});
				toast.success('Meeting type created');
			}

			meetingTypeDialogOpen = false;
			await loadData();
		} catch (error: any) {
			console.error('Failed to save meeting type:', error);
			toast.error(error.response?.data?.message || 'Failed to save meeting type');
		} finally {
			savingMeetingType = false;
		}
	}

	function confirmDeleteMeetingType(meetingType: MeetingType) {
		meetingTypeToDelete = meetingType;
		deleteMeetingTypeDialogOpen = true;
	}

	async function handleDeleteMeetingType() {
		if (!meetingTypeToDelete) return;

		deletingMeetingType = true;
		try {
			await deleteMeetingType(pageId, meetingTypeToDelete.id);
			toast.success('Meeting type deleted');
			deleteMeetingTypeDialogOpen = false;
			meetingTypeToDelete = null;
			await loadData();
		} catch (error) {
			console.error('Failed to delete meeting type:', error);
			toast.error('Failed to delete meeting type');
		} finally {
			deletingMeetingType = false;
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
</script>

<svelte:head>
	<title>{schedulingPage?.name || 'Edit Page'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto max-w-4xl p-6">
	<!-- Header -->
	<div class="mb-6">
		<Button variant="ghost" onclick={() => goto('/settings/scheduling')}>
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to Scheduling
		</Button>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if schedulingPage}
		<div class="mb-6 flex items-center justify-between">
			<div>
				<h1 class="text-2xl font-bold">{schedulingPage.name}</h1>
				<p class="text-muted-foreground">/schedule/{schedulingPage.slug}</p>
			</div>
			<Button
				variant="outline"
				onclick={() => window.open(`/schedule/${schedulingPage?.slug}`, '_blank')}
			>
				<Eye class="mr-2 h-4 w-4" />
				Preview
			</Button>
		</div>

		<Tabs.Root bind:value={activeTab} class="space-y-4">
			<Tabs.List>
				<Tabs.Trigger value="details">Page Details</Tabs.Trigger>
				<Tabs.Trigger value="meeting-types">Meeting Types</Tabs.Trigger>
				<Tabs.Trigger value="settings">Advanced Settings</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="details">
				<Card.Root>
					<Card.Header>
						<Card.Title>Page Details</Card.Title>
						<Card.Description>Basic information about your scheduling page</Card.Description>
					</Card.Header>
					<Card.Content class="space-y-4">
						<div class="space-y-2">
							<Label for="name">Page Name *</Label>
							<Input id="name" placeholder="My Scheduling Page" bind:value={name} />
						</div>

						<div class="space-y-2">
							<Label for="slug">URL Slug *</Label>
							<div class="flex items-center gap-2">
								<div class="flex-1">
									<div class="flex">
										<span
											class="inline-flex items-center rounded-l-md border border-r-0 border-input bg-muted px-3 text-sm text-muted-foreground"
										>
											/schedule/
										</span>
										<Input
											id="slug"
											class="rounded-l-none"
											placeholder="my-page"
											value={slug}
											oninput={handleSlugChange}
										/>
									</div>
								</div>
								<div class="w-6">
									{#if slugChecking}
										<Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
									{:else if slug === originalSlug}
										<!-- Original slug, no need to check -->
									{:else if slugAvailable === true}
										<Check class="h-4 w-4 text-green-500" />
									{:else if slugAvailable === false}
										<X class="h-4 w-4 text-red-500" />
									{/if}
								</div>
							</div>
							{#if slugAvailable === false}
								<p class="text-sm text-destructive">This URL is already taken</p>
							{/if}
						</div>

						<div class="space-y-2">
							<Label for="description">Description</Label>
							<Textarea
								id="description"
								placeholder="A brief description of what this page is for..."
								bind:value={description}
								rows={3}
							/>
						</div>

						<div class="space-y-2">
							<Label for="timezone">Timezone</Label>
							<Select.Root type="single" value={timezone} onValueChange={(v) => (timezone = v)}>
								<Select.Trigger id="timezone">
									{timezone}
								</Select.Trigger>
								<Select.Content>
									{#each timezones as tz}
										<Select.Item value={tz}>{tz}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>

						<div class="space-y-2">
							<Label for="color">Brand Color</Label>
							<div class="flex items-center gap-2">
								<input
									type="color"
									id="color"
									bind:value={primaryColor}
									class="h-10 w-20 cursor-pointer rounded border"
								/>
								<Input value={primaryColor} class="w-28" readonly />
							</div>
						</div>

						<div class="flex items-center justify-between rounded-lg border p-4">
							<div>
								<p class="font-medium">Active</p>
								<p class="text-sm text-muted-foreground">Make this page publicly accessible</p>
							</div>
							<Switch bind:checked={isActive} />
						</div>
					</Card.Content>
					<Card.Footer class="flex justify-end">
						<Button onclick={handleSavePage} disabled={saving}>
							{#if saving}
								<Loader2 class="mr-2 h-4 w-4 animate-spin" />
							{/if}
							Save Changes
						</Button>
					</Card.Footer>
				</Card.Root>
			</Tabs.Content>

			<Tabs.Content value="meeting-types">
				<Card.Root>
					<Card.Header>
						<div class="flex items-center justify-between">
							<div>
								<Card.Title>Meeting Types</Card.Title>
								<Card.Description>
									Define the types of meetings people can book with you
								</Card.Description>
							</div>
							<Button onclick={() => openMeetingTypeDialog()}>
								<Plus class="mr-2 h-4 w-4" />
								Add Meeting Type
							</Button>
						</div>
					</Card.Header>
					<Card.Content>
						{#if meetingTypes.length === 0}
							<div class="flex flex-col items-center justify-center py-8">
								<Clock class="mb-4 h-12 w-12 text-muted-foreground" />
								<h3 class="mb-2 text-lg font-medium">No meeting types yet</h3>
								<p class="mb-4 text-center text-muted-foreground">
									Add meeting types to let people book different kinds of meetings
								</p>
								<Button onclick={() => openMeetingTypeDialog()}>
									<Plus class="mr-2 h-4 w-4" />
									Add Meeting Type
								</Button>
							</div>
						{:else}
							<div class="space-y-3">
								{#each meetingTypes as meetingType}
									{@const LocationIcon = getLocationIcon(meetingType.location_type)}
									<div
										class="flex items-center gap-4 rounded-lg border p-4 hover:bg-muted/50 transition-colors"
									>
										<div
											class="h-3 w-3 rounded-full"
											style="background-color: {meetingType.color}"
										></div>
										<div class="flex-1 min-w-0">
											<div class="flex items-center gap-2">
												<p class="font-medium">{meetingType.name}</p>
												{#if !meetingType.is_active}
													<Badge variant="secondary">Inactive</Badge>
												{/if}
											</div>
											<div class="flex items-center gap-3 text-sm text-muted-foreground">
												<span class="flex items-center gap-1">
													<Clock class="h-3 w-3" />
													{meetingType.duration_minutes} min
												</span>
												<span class="flex items-center gap-1">
													<LocationIcon class="h-3 w-3" />
													{getLocationTypeLabel(meetingType.location_type)}
												</span>
											</div>
										</div>
										<div class="flex items-center gap-2">
											<Button
												variant="ghost"
												size="icon"
												onclick={() => openMeetingTypeDialog(meetingType)}
											>
												<Pencil class="h-4 w-4" />
											</Button>
											<Button
												variant="ghost"
												size="icon"
												onclick={() => confirmDeleteMeetingType(meetingType)}
											>
												<Trash2 class="h-4 w-4" />
											</Button>
										</div>
									</div>
								{/each}
							</div>
						{/if}
					</Card.Content>
				</Card.Root>
			</Tabs.Content>

			<Tabs.Content value="settings">
				<Card.Root>
					<Card.Header>
						<Card.Title>Advanced Settings</Card.Title>
						<Card.Description>Additional configuration options</Card.Description>
					</Card.Header>
					<Card.Content>
						<p class="text-muted-foreground">
							Advanced settings like custom branding, integrations, and notifications coming soon.
						</p>
					</Card.Content>
				</Card.Root>
			</Tabs.Content>
		</Tabs.Root>
	{/if}
</div>

<!-- Meeting Type Dialog -->
<Dialog.Root bind:open={meetingTypeDialogOpen}>
	<Dialog.Content class="max-w-2xl max-h-[90vh] overflow-y-auto">
		<Dialog.Header>
			<Dialog.Title>
				{editingMeetingType ? 'Edit Meeting Type' : 'New Meeting Type'}
			</Dialog.Title>
			<Dialog.Description>
				{editingMeetingType
					? 'Update the details for this meeting type'
					: 'Create a new type of meeting people can book'}
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="mt-name">Name *</Label>
					<Input
						id="mt-name"
						placeholder="30 Minute Meeting"
						bind:value={meetingTypeForm.name}
					/>
				</div>
				<div class="space-y-2">
					<Label for="mt-duration">Duration</Label>
					<Select.Root
						type="single"
						value={String(meetingTypeForm.duration_minutes)}
						onValueChange={(v) => (meetingTypeForm.duration_minutes = Number(v))}
					>
						<Select.Trigger id="mt-duration">
							{DURATION_OPTIONS.find((d) => d.value === meetingTypeForm.duration_minutes)?.label ||
								`${meetingTypeForm.duration_minutes} minutes`}
						</Select.Trigger>
						<Select.Content>
							{#each DURATION_OPTIONS as option}
								<Select.Item value={String(option.value)}>{option.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="mt-description">Description</Label>
				<Textarea
					id="mt-description"
					placeholder="What is this meeting for?"
					bind:value={meetingTypeForm.description}
					rows={2}
				/>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="mt-location">Location Type</Label>
					<Select.Root
						type="single"
						value={meetingTypeForm.location_type}
						onValueChange={(v) =>
							(meetingTypeForm.location_type = v as LocationType)}
					>
						<Select.Trigger id="mt-location">
							{getLocationTypeLabel(meetingTypeForm.location_type)}
						</Select.Trigger>
						<Select.Content>
							{#each LOCATION_TYPE_OPTIONS as option}
								<Select.Item value={option.value}>{option.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
				<div class="space-y-2">
					<Label for="mt-color">Color</Label>
					<div class="flex items-center gap-2">
						{#each MEETING_COLORS as colorOption}
							<button
								type="button"
								class="h-8 w-8 rounded-full border-2 transition-transform hover:scale-110"
								style="background-color: {colorOption.value}; border-color: {meetingTypeForm.color ===
								colorOption.value
									? 'black'
									: 'transparent'}"
								onclick={() => (meetingTypeForm.color = colorOption.value)}
							></button>
						{/each}
					</div>
				</div>
			</div>

			{#if meetingTypeForm.location_type === 'in_person' || meetingTypeForm.location_type === 'custom'}
				<div class="space-y-2">
					<Label for="mt-location-details">Location Details</Label>
					<Input
						id="mt-location-details"
						placeholder="Enter address or meeting instructions"
						bind:value={meetingTypeForm.location_details}
					/>
				</div>
			{/if}

			<div class="border-t pt-4">
				<h4 class="mb-3 font-medium">Booking Settings</h4>
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="mt-buffer-before">Buffer Before (minutes)</Label>
						<Input
							id="mt-buffer-before"
							type="number"
							min="0"
							bind:value={meetingTypeForm.settings.buffer_before}
						/>
					</div>
					<div class="space-y-2">
						<Label for="mt-buffer-after">Buffer After (minutes)</Label>
						<Input
							id="mt-buffer-after"
							type="number"
							min="0"
							bind:value={meetingTypeForm.settings.buffer_after}
						/>
					</div>
					<div class="space-y-2">
						<Label for="mt-min-notice">Minimum Notice (hours)</Label>
						<Input
							id="mt-min-notice"
							type="number"
							min="0"
							bind:value={meetingTypeForm.settings.min_notice_hours}
						/>
					</div>
					<div class="space-y-2">
						<Label for="mt-max-advance">Max Advance Booking (days)</Label>
						<Input
							id="mt-max-advance"
							type="number"
							min="1"
							bind:value={meetingTypeForm.settings.max_days_advance}
						/>
					</div>
				</div>
			</div>

			<div class="flex items-center justify-between rounded-lg border p-4">
				<div>
					<p class="font-medium">Active</p>
					<p class="text-sm text-muted-foreground">Allow people to book this meeting type</p>
				</div>
				<Switch bind:checked={meetingTypeForm.is_active} />
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (meetingTypeDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleSaveMeetingType} disabled={savingMeetingType}>
				{#if savingMeetingType}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				{editingMeetingType ? 'Save Changes' : 'Create'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Delete Meeting Type Dialog -->
<Dialog.Root bind:open={deleteMeetingTypeDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Delete Meeting Type</Dialog.Title>
			<Dialog.Description>
				Are you sure you want to delete "{meetingTypeToDelete?.name}"? This action cannot be undone.
			</Dialog.Description>
		</Dialog.Header>
		<Dialog.Footer>
			<Button
				variant="outline"
				onclick={() => (deleteMeetingTypeDialogOpen = false)}
				disabled={deletingMeetingType}
			>
				Cancel
			</Button>
			<Button variant="destructive" onclick={handleDeleteMeetingType} disabled={deletingMeetingType}>
				{#if deletingMeetingType}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Delete
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
