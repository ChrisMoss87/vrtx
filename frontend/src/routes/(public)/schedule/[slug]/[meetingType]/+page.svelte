<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import {
		ArrowLeft,
		Clock,
		Video,
		Phone,
		MapPin,
		Settings2,
		Loader2,
		AlertCircle,
		ChevronLeft,
		ChevronRight,
		Calendar,
		CheckCircle,
		Globe
	} from 'lucide-svelte';
	import {
		getPublicMeetingType,
		getAvailableDates,
		getAvailableSlots,
		bookMeeting,
		formatTimeSlot,
		getBrowserTimezone,
		type PublicSchedulingPage,
		type PublicMeetingTypeDetail,
		type TimeSlot,
		type BookedMeeting
	} from '$lib/api/public-scheduling';

	const slug = $derived($page.params.slug);
	const meetingTypeSlug = $derived($page.params.meetingType);

	let pageData = $state<PublicSchedulingPage | null>(null);
	let meetingType = $state<PublicMeetingTypeDetail | null>(null);
	let loading = $state(true);
	let error = $state<string | null>(null);

	// Calendar state
	let currentMonth = $state(new Date());
	let availableDates = $state<string[]>([]);
	let loadingDates = $state(false);
	let selectedDate = $state<string | null>(null);

	// Time slots state
	let timeSlots = $state<TimeSlot[]>([]);
	let loadingSlots = $state(false);
	let selectedTime = $state<string | null>(null);

	// Timezone
	let timezone = $state(getBrowserTimezone());

	// Booking form state
	let step = $state<'calendar' | 'form' | 'confirmation'>('calendar');
	let formData = $state({
		name: '',
		email: '',
		phone: '',
		notes: '',
		answers: {} as Record<string, string>
	});
	let booking = $state(false);
	let bookedMeeting = $state<BookedMeeting | null>(null);
	let bookingError = $state<string | null>(null);

	// Common timezones for selection
	const commonTimezones = [
		'America/New_York',
		'America/Chicago',
		'America/Denver',
		'America/Los_Angeles',
		'America/Phoenix',
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
		await loadMeetingType();
	});

	async function loadMeetingType() {
		if (!slug || !meetingTypeSlug) return;
		loading = true;
		error = null;
		try {
			const data = await getPublicMeetingType(slug, meetingTypeSlug);
			pageData = data.page;
			meetingType = data.meeting_type;
			await loadAvailableDates();
		} catch (e: any) {
			error = e.message || 'Failed to load meeting type';
		} finally {
			loading = false;
		}
	}

	async function loadAvailableDates() {
		if (!meetingType || !slug || !meetingTypeSlug) return;

		loadingDates = true;
		try {
			const monthStr = `${currentMonth.getFullYear()}-${String(currentMonth.getMonth() + 1).padStart(2, '0')}`;
			const data = await getAvailableDates(slug, meetingTypeSlug, monthStr, timezone);
			availableDates = data.available_dates;
		} catch (e) {
			console.error('Failed to load dates:', e);
			availableDates = [];
		} finally {
			loadingDates = false;
		}
	}

	async function handleDateSelect(date: string) {
		if (!slug || !meetingTypeSlug) return;
		selectedDate = date;
		selectedTime = null;
		loadingSlots = true;

		try {
			const data = await getAvailableSlots(slug, meetingTypeSlug, date, timezone);
			timeSlots = data.slots;
		} catch (e) {
			console.error('Failed to load slots:', e);
			timeSlots = [];
		} finally {
			loadingSlots = false;
		}
	}

	function handleTimeSelect(time: string) {
		selectedTime = time;
		step = 'form';
	}

	function prevMonth() {
		currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
		selectedDate = null;
		timeSlots = [];
		loadAvailableDates();
	}

	function nextMonth() {
		currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
		selectedDate = null;
		timeSlots = [];
		loadAvailableDates();
	}

	async function handleTimezoneChange(tz: string) {
		timezone = tz;
		selectedDate = null;
		selectedTime = null;
		timeSlots = [];
		await loadAvailableDates();
	}

	async function handleSubmit() {
		if (!selectedDate || !selectedTime || !formData.name || !formData.email || !slug || !meetingTypeSlug) return;

		booking = true;
		bookingError = null;

		try {
			// Construct the full datetime
			const startTime = `${selectedDate}T${selectedTime}:00`;

			const result = await bookMeeting(slug, meetingTypeSlug, {
				name: formData.name,
				email: formData.email,
				phone: formData.phone || undefined,
				start_time: startTime,
				timezone,
				notes: formData.notes || undefined,
				answers: Object.keys(formData.answers).length > 0 ? formData.answers : undefined
			});

			bookedMeeting = result.meeting;
			step = 'confirmation';
		} catch (e: any) {
			bookingError = e.message || 'Failed to book meeting';
		} finally {
			booking = false;
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

	// Calendar helpers
	function getCalendarDays() {
		const year = currentMonth.getFullYear();
		const month = currentMonth.getMonth();
		const firstDay = new Date(year, month, 1);
		const lastDay = new Date(year, month + 1, 0);
		const days: Array<{ date: string; day: number; isCurrentMonth: boolean; isAvailable: boolean }> =
			[];

		// Previous month days
		const startDay = firstDay.getDay();
		for (let i = startDay - 1; i >= 0; i--) {
			const d = new Date(year, month, -i);
			days.push({
				date: d.toISOString().split('T')[0],
				day: d.getDate(),
				isCurrentMonth: false,
				isAvailable: false
			});
		}

		// Current month days
		for (let i = 1; i <= lastDay.getDate(); i++) {
			const d = new Date(year, month, i);
			const dateStr = d.toISOString().split('T')[0];
			days.push({
				date: dateStr,
				day: i,
				isCurrentMonth: true,
				isAvailable: availableDates.includes(dateStr)
			});
		}

		// Next month days
		const remaining = 42 - days.length;
		for (let i = 1; i <= remaining; i++) {
			const d = new Date(year, month + 1, i);
			days.push({
				date: d.toISOString().split('T')[0],
				day: i,
				isCurrentMonth: false,
				isAvailable: false
			});
		}

		return days;
	}

	function formatSelectedDateTime(): string {
		if (!selectedDate || !selectedTime) return '';
		const date = new Date(`${selectedDate}T${selectedTime}:00`);
		return date.toLocaleDateString('en-US', {
			weekday: 'long',
			month: 'long',
			day: 'numeric',
			year: 'numeric'
		});
	}
</script>

<svelte:head>
	<title>{meetingType?.name || 'Book Meeting'} | {pageData?.host.name || 'Schedule'}</title>
</svelte:head>

<div class="min-h-screen py-8 px-4">
	<div class="mx-auto max-w-4xl">
		{#if loading}
			<div class="flex flex-col items-center justify-center py-24">
				<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
				<p class="mt-4 text-muted-foreground">Loading...</p>
			</div>
		{:else if error}
			<Card.Root>
				<Card.Content class="flex flex-col items-center justify-center py-12">
					<AlertCircle class="mb-4 h-12 w-12 text-destructive" />
					<h2 class="mb-2 text-xl font-semibold">Not Found</h2>
					<p class="text-muted-foreground">{error}</p>
					<Button class="mt-4" onclick={() => goto(`/schedule/${slug}`)}>
						<ArrowLeft class="mr-2 h-4 w-4" />
						Back
					</Button>
				</Card.Content>
			</Card.Root>
		{:else if pageData && meetingType}
			{#if step === 'confirmation' && bookedMeeting}
				<!-- Confirmation -->
				<Card.Root>
					<Card.Content class="py-12">
						<div class="text-center">
							<div
								class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-green-100"
							>
								<CheckCircle class="h-8 w-8 text-green-600" />
							</div>
							<h1 class="mb-2 text-2xl font-bold">Meeting Confirmed!</h1>
							<p class="text-muted-foreground">
								A confirmation email has been sent to {formData.email}
							</p>
						</div>

						<div class="mx-auto mt-8 max-w-md space-y-4 rounded-lg border bg-muted/30 p-6">
							<div class="flex items-center gap-3">
								<div
									class="h-3 w-3 rounded-full"
									style="background-color: {meetingType.color}"
								></div>
								<span class="font-semibold">{meetingType.name}</span>
							</div>
							<div class="flex items-center gap-3 text-muted-foreground">
								<Calendar class="h-5 w-5" />
								<span>
									{new Date(bookedMeeting.start_time).toLocaleDateString('en-US', {
										weekday: 'long',
										month: 'long',
										day: 'numeric',
										year: 'numeric'
									})}
								</span>
							</div>
							<div class="flex items-center gap-3 text-muted-foreground">
								<Clock class="h-5 w-5" />
								<span>
									{new Date(bookedMeeting.start_time).toLocaleTimeString('en-US', {
										hour: 'numeric',
										minute: '2-digit'
									})} - {new Date(bookedMeeting.end_time).toLocaleTimeString('en-US', {
										hour: 'numeric',
										minute: '2-digit'
									})}
								</span>
							</div>
							<div class="flex items-center gap-3 text-muted-foreground">
								<Globe class="h-5 w-5" />
								<span>{bookedMeeting.timezone}</span>
							</div>
							{#if bookedMeeting.location}
								{@const LocationIcon = getLocationIcon(meetingType.location_type)}
								<div class="flex items-center gap-3 text-muted-foreground">
									<LocationIcon class="h-5 w-5" />
									<span>{bookedMeeting.location}</span>
								</div>
							{/if}
						</div>

						<div class="mt-8 text-center">
							<p class="text-sm text-muted-foreground">
								Need to make changes?{' '}
								<a href={bookedMeeting.manage_url} class="text-primary hover:underline">
									Manage your booking
								</a>
							</p>
						</div>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="grid gap-6 lg:grid-cols-3">
					<!-- Sidebar -->
					<div class="lg:col-span-1">
						<Card.Root>
							<Card.Content class="p-6">
								<Button variant="ghost" size="sm" onclick={() => goto(`/schedule/${slug}`)}>
									<ArrowLeft class="mr-2 h-4 w-4" />
									Back
								</Button>

								<div class="mt-4">
									<p class="text-sm text-muted-foreground">{pageData.host.name}</p>
									<h1 class="mt-1 text-xl font-bold">{meetingType.name}</h1>

									<div class="mt-4 space-y-2 text-sm text-muted-foreground">
										<div class="flex items-center gap-2">
											<Clock class="h-4 w-4" />
											<span>{meetingType.duration_minutes} minutes</span>
										</div>
										{#if meetingType.location_type === 'zoom'}
											<div class="flex items-center gap-2">
												<Video class="h-4 w-4" />
												<span>Zoom</span>
											</div>
										{:else if meetingType.location_type === 'google_meet'}
											<div class="flex items-center gap-2">
												<Video class="h-4 w-4" />
												<span>Google Meet</span>
											</div>
										{:else if meetingType.location_type === 'phone'}
											<div class="flex items-center gap-2">
												<Phone class="h-4 w-4" />
												<span>Phone Call</span>
											</div>
										{:else if meetingType.location_type === 'in_person'}
											<div class="flex items-center gap-2">
												<MapPin class="h-4 w-4" />
												<span>In Person</span>
											</div>
										{:else}
											<div class="flex items-center gap-2">
												<Settings2 class="h-4 w-4" />
												<span>Custom</span>
											</div>
										{/if}
									</div>

									{#if meetingType.description}
										<p class="mt-4 text-sm text-muted-foreground">{meetingType.description}</p>
									{/if}
								</div>
							</Card.Content>
						</Card.Root>
					</div>

					<!-- Main Content -->
					<div class="lg:col-span-2">
						{#if step === 'calendar'}
							<Card.Root>
								<Card.Content class="p-6">
									<h2 class="mb-4 text-lg font-semibold">Select a Date & Time</h2>

									<!-- Timezone selector -->
									<div class="mb-4">
										<Select.Root
											type="single"
											value={timezone}
											onValueChange={handleTimezoneChange}
										>
											<Select.Trigger class="w-full">
												<Globe class="mr-2 h-4 w-4" />
												{timezone}
											</Select.Trigger>
											<Select.Content>
												{#each commonTimezones as tz}
													<Select.Item value={tz}>{tz}</Select.Item>
												{/each}
											</Select.Content>
										</Select.Root>
									</div>

									<div class="grid gap-6 md:grid-cols-2">
										<!-- Calendar -->
										<div>
											<div class="mb-4 flex items-center justify-between">
												<h3 class="font-medium">
													{currentMonth.toLocaleDateString('en-US', {
														month: 'long',
														year: 'numeric'
													})}
												</h3>
												<div class="flex gap-1">
													<Button variant="ghost" size="icon" onclick={prevMonth}>
														<ChevronLeft class="h-4 w-4" />
													</Button>
													<Button variant="ghost" size="icon" onclick={nextMonth}>
														<ChevronRight class="h-4 w-4" />
													</Button>
												</div>
											</div>

											{#if loadingDates}
												<div class="flex items-center justify-center py-12">
													<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
												</div>
											{:else}
												<div class="grid grid-cols-7 gap-1 text-center text-sm">
													{#each ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as day}
														<div class="py-2 text-muted-foreground font-medium">{day}</div>
													{/each}
													{#each getCalendarDays() as { date, day, isCurrentMonth, isAvailable }}
														<button
															class="rounded-lg p-2 text-sm transition-colors"
															class:text-muted-foreground={!isCurrentMonth}
															class:opacity-50={!isCurrentMonth}
															class:hover:bg-primary={isAvailable}
															class:hover:text-primary-foreground={isAvailable}
															class:bg-primary={selectedDate === date}
															class:text-primary-foreground={selectedDate === date}
															class:cursor-not-allowed={!isAvailable}
															disabled={!isAvailable}
															onclick={() => isAvailable && handleDateSelect(date)}
														>
															{day}
														</button>
													{/each}
												</div>
											{/if}
										</div>

										<!-- Time Slots -->
										<div>
											<h3 class="mb-4 font-medium">
												{#if selectedDate}
													{new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-US', {
														weekday: 'long',
														month: 'short',
														day: 'numeric'
													})}
												{:else}
													Select a date
												{/if}
											</h3>

											{#if !selectedDate}
												<p class="py-8 text-center text-muted-foreground">
													Select a date to see available times
												</p>
											{:else if loadingSlots}
												<div class="flex items-center justify-center py-12">
													<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
												</div>
											{:else if timeSlots.length === 0}
												<p class="py-8 text-center text-muted-foreground">
													No available times for this date
												</p>
											{:else}
												<div class="grid grid-cols-2 gap-2 max-h-80 overflow-y-auto">
													{#each timeSlots.filter((s) => s.available) as slot}
														<Button
															variant={selectedTime === slot.time ? 'default' : 'outline'}
															class="w-full"
															onclick={() => handleTimeSelect(slot.time)}
														>
															{formatTimeSlot(slot.time)}
														</Button>
													{/each}
												</div>
											{/if}
										</div>
									</div>
								</Card.Content>
							</Card.Root>
						{:else if step === 'form'}
							<Card.Root>
								<Card.Content class="p-6">
									<Button
										variant="ghost"
										size="sm"
										class="mb-4"
										onclick={() => (step = 'calendar')}
									>
										<ArrowLeft class="mr-2 h-4 w-4" />
										Back
									</Button>

									<div class="mb-6 rounded-lg border bg-muted/30 p-4">
										<p class="font-medium">{formatSelectedDateTime()}</p>
										<p class="text-sm text-muted-foreground">
											{formatTimeSlot(selectedTime || '')} ({meetingType.duration_minutes} min)
										</p>
									</div>

									{#if bookingError}
										<div class="mb-4 rounded-lg border border-destructive bg-destructive/10 p-4">
											<p class="text-sm text-destructive">{bookingError}</p>
										</div>
									{/if}

									<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
										<div class="space-y-2">
											<Label for="name">Name *</Label>
											<Input
												id="name"
												placeholder="Your name"
												bind:value={formData.name}
												required
											/>
										</div>

										<div class="space-y-2">
											<Label for="email">Email *</Label>
											<Input
												id="email"
												type="email"
												placeholder="your@email.com"
												bind:value={formData.email}
												required
											/>
										</div>

										<div class="space-y-2">
											<Label for="phone">Phone (optional)</Label>
											<Input
												id="phone"
												type="tel"
												placeholder="Your phone number"
												bind:value={formData.phone}
											/>
										</div>

										<!-- Custom Questions -->
										{#each meetingType.questions || [] as question}
											<div class="space-y-2">
												<Label for="q-{question.id}">
													{question.label}
													{#if question.required}*{/if}
												</Label>
												{#if question.type === 'textarea'}
													<Textarea
														id="q-{question.id}"
														placeholder={question.placeholder || ''}
														bind:value={formData.answers[question.id]}
														required={question.required}
													/>
												{:else if question.type === 'select' && question.options}
													<Select.Root
														type="single"
														value={formData.answers[question.id] || ''}
														onValueChange={(v) => (formData.answers[question.id] = v)}
													>
														<Select.Trigger>
															{formData.answers[question.id] || 'Select an option'}
														</Select.Trigger>
														<Select.Content>
															{#each question.options as option}
																<Select.Item value={option}>{option}</Select.Item>
															{/each}
														</Select.Content>
													</Select.Root>
												{:else}
													<Input
														id="q-{question.id}"
														placeholder={question.placeholder || ''}
														bind:value={formData.answers[question.id]}
														required={question.required}
													/>
												{/if}
											</div>
										{/each}

										<div class="space-y-2">
											<Label for="notes">Additional Notes (optional)</Label>
											<Textarea
												id="notes"
												placeholder="Any additional information..."
												bind:value={formData.notes}
												rows={3}
											/>
										</div>

										<Button type="submit" class="w-full" disabled={booking}>
											{#if booking}
												<Loader2 class="mr-2 h-4 w-4 animate-spin" />
											{/if}
											Confirm Booking
										</Button>
									</form>
								</Card.Content>
							</Card.Root>
						{/if}
					</div>
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
