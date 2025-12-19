<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import {
		ArrowLeft,
		Loader2,
		Plus,
		Trash2,
		Clock,
		Calendar,
		CalendarOff,
		Copy,
		MoreHorizontal,
		RotateCcw
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getAvailabilityRules,
		updateAvailabilityRules,
		getSchedulingOverrides,
		createSchedulingOverride,
		deleteSchedulingOverride,
		getDayName,
		type SchedulingOverride,
		type AvailabilityWindow
	} from '$lib/api/scheduling';

	interface DayAvailability {
		day_of_week: number;
		is_active: boolean;
		windows: Array<{ start_time: string; end_time: string }>;
	}

	// Time presets for quick selection
	const TIME_PRESETS = [
		{ label: '9 AM - 5 PM', start: '09:00', end: '17:00' },
		{ label: '8 AM - 4 PM', start: '08:00', end: '16:00' },
		{ label: '8 AM - 5 PM', start: '08:00', end: '17:00' },
		{ label: '9 AM - 6 PM', start: '09:00', end: '18:00' },
		{ label: '10 AM - 6 PM', start: '10:00', end: '18:00' },
		{ label: '8 AM - 12 PM (Morning)', start: '08:00', end: '12:00' },
		{ label: '1 PM - 5 PM (Afternoon)', start: '13:00', end: '17:00' },
	];

	let rules = $state<DayAvailability[]>([]);
	let overrides = $state<SchedulingOverride[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let activeTab = $state('weekly');
	let hasUnsavedChanges = $state(false);

	// Override dialog
	let overrideDialogOpen = $state(false);
	let overrideForm = $state({
		date: '',
		is_available: false,
		start_time: '09:00',
		end_time: '17:00',
		reason: ''
	});
	let savingOverride = $state(false);

	// Delete override
	let deletingOverrideId = $state<number | null>(null);

	// Copy hours dialog
	let copyDialogOpen = $state(false);
	let copySourceDay = $state<number | null>(null);
	let copyTargetDays = $state<number[]>([]);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [rulesData, overridesData] = await Promise.all([
				getAvailabilityRules(),
				getSchedulingOverrides()
			]);

			// Convert API rules to form format with multiple windows support
			const daysMap = new Map<number, DayAvailability>();

			// Initialize all days
			for (let i = 0; i < 7; i++) {
				daysMap.set(i, {
					day_of_week: i,
					is_active: i >= 1 && i <= 5, // Mon-Fri default
					windows: [{ start_time: '09:00', end_time: '17:00' }]
				});
			}

			// Apply actual data
			if (rulesData.length > 0) {
				rulesData.forEach(rule => {
					const windows = rule.windows && rule.windows.length > 0
						? rule.windows.filter(w => w.is_available).map(w => ({
							start_time: w.start_time,
							end_time: w.end_time
						}))
						: [{ start_time: rule.start_time, end_time: rule.end_time }];

					daysMap.set(rule.day_of_week, {
						day_of_week: rule.day_of_week,
						is_active: rule.is_active,
						windows: windows.length > 0 ? windows : [{ start_time: '09:00', end_time: '17:00' }]
					});
				});
			}

			rules = Array.from(daysMap.values()).sort((a, b) => a.day_of_week - b.day_of_week);
			overrides = overridesData;
			hasUnsavedChanges = false;
		} catch (error) {
			console.error('Failed to load availability:', error);
			toast.error('Failed to load availability settings');
		} finally {
			loading = false;
		}
	}

	function markChanged() {
		hasUnsavedChanges = true;
	}

	async function handleSaveRules() {
		saving = true;
		try {
			// Convert to backend format
			const backendRules = rules.map(rule => ({
				day_of_week: rule.day_of_week,
				start_time: rule.windows[0]?.start_time || '09:00',
				end_time: rule.windows[0]?.end_time || '17:00',
				is_active: rule.is_active
			}));
			await updateAvailabilityRules(backendRules);
			toast.success('Availability saved');
			hasUnsavedChanges = false;
		} catch (error) {
			console.error('Failed to save availability:', error);
			toast.error('Failed to save availability');
		} finally {
			saving = false;
		}
	}

	function addTimeWindow(dayIndex: number) {
		rules[dayIndex].windows = [
			...rules[dayIndex].windows,
			{ start_time: '13:00', end_time: '17:00' }
		];
		markChanged();
	}

	function removeTimeWindow(dayIndex: number, windowIndex: number) {
		if (rules[dayIndex].windows.length > 1) {
			rules[dayIndex].windows = rules[dayIndex].windows.filter((_, i) => i !== windowIndex);
			markChanged();
		}
	}

	function applyPreset(dayIndex: number, preset: typeof TIME_PRESETS[0]) {
		rules[dayIndex].windows = [{ start_time: preset.start, end_time: preset.end }];
		rules[dayIndex].is_active = true;
		markChanged();
		toast.success(`Applied ${preset.label} to ${getDayName(rules[dayIndex].day_of_week)}`);
	}

	function applyPresetToAll(preset: typeof TIME_PRESETS[0]) {
		rules = rules.map(rule => ({
			...rule,
			is_active: rule.day_of_week >= 1 && rule.day_of_week <= 5, // Mon-Fri
			windows: [{ start_time: preset.start, end_time: preset.end }]
		}));
		markChanged();
		toast.success(`Applied ${preset.label} to all weekdays`);
	}

	function openCopyDialog(dayIndex: number) {
		copySourceDay = dayIndex;
		copyTargetDays = [];
		copyDialogOpen = true;
	}

	function toggleCopyTarget(dayIndex: number) {
		if (copyTargetDays.includes(dayIndex)) {
			copyTargetDays = copyTargetDays.filter(d => d !== dayIndex);
		} else {
			copyTargetDays = [...copyTargetDays, dayIndex];
		}
	}

	function applyCopyHours() {
		if (copySourceDay === null || copyTargetDays.length === 0) return;

		const sourceRule = rules[copySourceDay];
		copyTargetDays.forEach(targetDay => {
			rules[targetDay] = {
				...rules[targetDay],
				is_active: sourceRule.is_active,
				windows: sourceRule.windows.map(w => ({ ...w }))
			};
		});

		markChanged();
		copyDialogOpen = false;
		toast.success(`Copied hours to ${copyTargetDays.length} day(s)`);
	}

	function resetToDefaults() {
		rules = rules.map((rule, index) => ({
			day_of_week: index,
			is_active: index >= 1 && index <= 5,
			windows: [{ start_time: '09:00', end_time: '17:00' }]
		}));
		markChanged();
		toast.success('Reset to default hours (Mon-Fri, 9 AM - 5 PM)');
	}

	function openOverrideDialog() {
		// Set default date to tomorrow
		const tomorrow = new Date();
		tomorrow.setDate(tomorrow.getDate() + 1);
		overrideForm = {
			date: tomorrow.toISOString().split('T')[0],
			is_available: false,
			start_time: '09:00',
			end_time: '17:00',
			reason: ''
		};
		overrideDialogOpen = true;
	}

	async function handleSaveOverride() {
		if (!overrideForm.date) {
			toast.error('Please select a date');
			return;
		}

		savingOverride = true;
		try {
			await createSchedulingOverride({
				date: overrideForm.date,
				is_available: overrideForm.is_available,
				start_time: overrideForm.is_available ? overrideForm.start_time : undefined,
				end_time: overrideForm.is_available ? overrideForm.end_time : undefined,
				reason: overrideForm.reason || undefined
			});

			toast.success('Override added');
			overrideDialogOpen = false;
			await loadData();
		} catch (error: any) {
			console.error('Failed to save override:', error);
			toast.error(error.response?.data?.message || 'Failed to save override');
		} finally {
			savingOverride = false;
		}
	}

	async function handleDeleteOverride(id: number) {
		deletingOverrideId = id;
		try {
			await deleteSchedulingOverride(id);
			toast.success('Override removed');
			await loadData();
		} catch (error) {
			console.error('Failed to delete override:', error);
			toast.error('Failed to delete override');
		} finally {
			deletingOverrideId = null;
		}
	}

	function formatDate(dateString: string): string {
		return new Date(dateString).toLocaleDateString('en-US', {
			weekday: 'short',
			month: 'short',
			day: 'numeric',
			year: 'numeric'
		});
	}

	function formatTime(time: string): string {
		const [hours, minutes] = time.split(':');
		const hour = parseInt(hours);
		const ampm = hour >= 12 ? 'PM' : 'AM';
		const hour12 = hour % 12 || 12;
		return `${hour12}:${minutes} ${ampm}`;
	}

	function getTotalHoursPerWeek(): number {
		let total = 0;
		rules.forEach(rule => {
			if (rule.is_active) {
				rule.windows.forEach(window => {
					const start = parseInt(window.start_time.split(':')[0]) * 60 + parseInt(window.start_time.split(':')[1]);
					const end = parseInt(window.end_time.split(':')[0]) * 60 + parseInt(window.end_time.split(':')[1]);
					total += (end - start) / 60;
				});
			}
		});
		return Math.round(total * 10) / 10;
	}
</script>

<svelte:head>
	<title>Availability | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto max-w-3xl p-6">
	<!-- Header -->
	<div class="mb-6">
		<Button variant="ghost" onclick={() => goto('/settings/scheduling')}>
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to Scheduling
		</Button>
	</div>

	<div class="mb-6 flex items-start justify-between">
		<div>
			<h1 class="text-2xl font-bold">Availability</h1>
			<p class="text-muted-foreground">Set your regular working hours and date-specific overrides</p>
		</div>
		{#if !loading}
			<Badge variant="outline" class="text-sm">
				{getTotalHoursPerWeek()} hrs/week
			</Badge>
		{/if}
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else}
		<Tabs.Root bind:value={activeTab} class="space-y-4">
			<Tabs.List>
				<Tabs.Trigger value="weekly">
					<Clock class="mr-2 h-4 w-4" />
					Weekly Hours
				</Tabs.Trigger>
				<Tabs.Trigger value="overrides">
					<CalendarOff class="mr-2 h-4 w-4" />
					Date Overrides
					{#if overrides.length > 0}
						<Badge variant="secondary" class="ml-2">{overrides.length}</Badge>
					{/if}
				</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="weekly">
				<Card.Root>
					<Card.Header>
						<div class="flex items-center justify-between">
							<div>
								<Card.Title>Weekly Availability</Card.Title>
								<Card.Description>
									Set your regular working hours for each day of the week
								</Card.Description>
							</div>
							<DropdownMenu.Root>
								<DropdownMenu.Trigger>
									{#snippet child({ props })}
										<Button variant="outline" size="sm" {...props}>
											<Clock class="mr-2 h-4 w-4" />
											Quick Presets
										</Button>
									{/snippet}
								</DropdownMenu.Trigger>
								<DropdownMenu.Content align="end" class="w-56">
									<DropdownMenu.Label>Apply to all weekdays</DropdownMenu.Label>
									<DropdownMenu.Separator />
									{#each TIME_PRESETS as preset}
										<DropdownMenu.Item onclick={() => applyPresetToAll(preset)}>
											{preset.label}
										</DropdownMenu.Item>
									{/each}
									<DropdownMenu.Separator />
									<DropdownMenu.Item onclick={resetToDefaults}>
										<RotateCcw class="mr-2 h-4 w-4" />
										Reset to Defaults
									</DropdownMenu.Item>
								</DropdownMenu.Content>
							</DropdownMenu.Root>
						</div>
					</Card.Header>
					<Card.Content class="space-y-4">
						{#each rules as rule, dayIndex}
							<div
								class="rounded-lg border p-4"
								class:bg-muted={!rule.is_active}
							>
								<div class="flex items-center gap-4">
									<Switch
										checked={rule.is_active}
										onCheckedChange={(checked) => {
											rules[dayIndex].is_active = checked;
											markChanged();
										}}
									/>
									<span class="w-24 font-medium">{getDayName(rule.day_of_week)}</span>

									{#if !rule.is_active}
										<span class="flex-1 text-muted-foreground">Unavailable</span>
									{/if}

									<DropdownMenu.Root>
										<DropdownMenu.Trigger>
											{#snippet child({ props })}
												<Button variant="ghost" size="icon" class="h-8 w-8" {...props}>
													<MoreHorizontal class="h-4 w-4" />
												</Button>
											{/snippet}
										</DropdownMenu.Trigger>
										<DropdownMenu.Content align="end" class="w-48">
											<DropdownMenu.Label>Quick Actions</DropdownMenu.Label>
											<DropdownMenu.Separator />
											<DropdownMenu.Item onclick={() => openCopyDialog(dayIndex)}>
												<Copy class="mr-2 h-4 w-4" />
												Copy to other days
											</DropdownMenu.Item>
											<DropdownMenu.Separator />
											<DropdownMenu.Label>Apply Preset</DropdownMenu.Label>
											{#each TIME_PRESETS.slice(0, 4) as preset}
												<DropdownMenu.Item onclick={() => applyPreset(dayIndex, preset)}>
													{preset.label}
												</DropdownMenu.Item>
											{/each}
										</DropdownMenu.Content>
									</DropdownMenu.Root>
								</div>

								{#if rule.is_active}
									<div class="mt-3 space-y-2 pl-14">
										{#each rule.windows as window, windowIndex}
											<div class="flex items-center gap-2">
												<Input
													type="time"
													class="w-32"
													value={window.start_time}
													oninput={(e) => {
														rules[dayIndex].windows[windowIndex].start_time = (e.target as HTMLInputElement).value;
														markChanged();
													}}
												/>
												<span class="text-muted-foreground">to</span>
												<Input
													type="time"
													class="w-32"
													value={window.end_time}
													oninput={(e) => {
														rules[dayIndex].windows[windowIndex].end_time = (e.target as HTMLInputElement).value;
														markChanged();
													}}
												/>
												{#if rule.windows.length > 1}
													<Button
														variant="ghost"
														size="icon"
														class="h-8 w-8 text-muted-foreground hover:text-destructive"
														onclick={() => removeTimeWindow(dayIndex, windowIndex)}
													>
														<Trash2 class="h-4 w-4" />
													</Button>
												{/if}
											</div>
										{/each}
										<Button
											variant="ghost"
											size="sm"
											class="text-muted-foreground"
											onclick={() => addTimeWindow(dayIndex)}
										>
											<Plus class="mr-1 h-3 w-3" />
											Add time window
										</Button>
									</div>
								{/if}
							</div>
						{/each}
					</Card.Content>
					<Card.Footer class="flex justify-between">
						<div class="text-sm text-muted-foreground">
							{#if hasUnsavedChanges}
								<span class="text-orange-600">You have unsaved changes</span>
							{/if}
						</div>
						<Button onclick={handleSaveRules} disabled={saving || !hasUnsavedChanges}>
							{#if saving}
								<Loader2 class="mr-2 h-4 w-4 animate-spin" />
							{/if}
							Save Availability
						</Button>
					</Card.Footer>
				</Card.Root>
			</Tabs.Content>

			<Tabs.Content value="overrides">
				<Card.Root>
					<Card.Header>
						<div class="flex items-center justify-between">
							<div>
								<Card.Title>Date Overrides</Card.Title>
								<Card.Description>
									Block off specific dates or add extra availability
								</Card.Description>
							</div>
							<Button onclick={openOverrideDialog}>
								<Plus class="mr-2 h-4 w-4" />
								Add Override
							</Button>
						</div>
					</Card.Header>
					<Card.Content>
						{#if overrides.length === 0}
							<div class="flex flex-col items-center justify-center py-8">
								<CalendarOff class="mb-4 h-12 w-12 text-muted-foreground" />
								<h3 class="mb-2 text-lg font-medium">No date overrides</h3>
								<p class="mb-4 text-center text-muted-foreground">
									Add overrides for holidays, vacation days, or extra availability
								</p>
								<Button onclick={openOverrideDialog}>
									<Plus class="mr-2 h-4 w-4" />
									Add Override
								</Button>
							</div>
						{:else}
							<div class="space-y-3">
								{#each overrides as override}
									<div
										class="flex items-center justify-between rounded-lg border p-4"
										class:bg-red-50={!override.is_available}
										class:dark:bg-red-950={!override.is_available}
									>
										<div class="flex items-center gap-4">
											<div
												class="flex h-10 w-10 items-center justify-center rounded-lg"
												class:bg-red-100={!override.is_available}
												class:bg-green-100={override.is_available}
												class:dark:bg-red-900={!override.is_available}
												class:dark:bg-green-900={override.is_available}
											>
												{#if override.is_available}
													<Calendar class="h-5 w-5 text-green-600 dark:text-green-400" />
												{:else}
													<CalendarOff class="h-5 w-5 text-red-600 dark:text-red-400" />
												{/if}
											</div>
											<div>
												<p class="font-medium">{formatDate(override.date)}</p>
												<p class="text-sm text-muted-foreground">
													{#if override.is_available && override.start_time && override.end_time}
														Available {formatTime(override.start_time)} - {formatTime(
															override.end_time
														)}
													{:else}
														Unavailable
													{/if}
													{#if override.reason}
														<span class="ml-2">· {override.reason}</span>
													{/if}
												</p>
											</div>
										</div>
										<Button
											variant="ghost"
											size="icon"
											onclick={() => handleDeleteOverride(override.id)}
											disabled={deletingOverrideId === override.id}
										>
											{#if deletingOverrideId === override.id}
												<Loader2 class="h-4 w-4 animate-spin" />
											{:else}
												<Trash2 class="h-4 w-4" />
											{/if}
										</Button>
									</div>
								{/each}
							</div>
						{/if}
					</Card.Content>
				</Card.Root>
			</Tabs.Content>
		</Tabs.Root>
	{/if}
</div>

<!-- Override Dialog -->
<Dialog.Root bind:open={overrideDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Add Date Override</Dialog.Title>
			<Dialog.Description>
				Block off a date or add extra availability for a specific day
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="override-date">Date *</Label>
				<Input id="override-date" type="date" bind:value={overrideForm.date} />
			</div>

			<div class="flex items-center justify-between rounded-lg border p-4">
				<div>
					<p class="font-medium">Available on this day</p>
					<p class="text-sm text-muted-foreground">
						{overrideForm.is_available
							? 'Set custom hours for this day'
							: 'Block this day completely'}
					</p>
				</div>
				<Switch bind:checked={overrideForm.is_available} />
			</div>

			{#if overrideForm.is_available}
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="override-start">Start Time</Label>
						<Input id="override-start" type="time" bind:value={overrideForm.start_time} />
					</div>
					<div class="space-y-2">
						<Label for="override-end">End Time</Label>
						<Input id="override-end" type="time" bind:value={overrideForm.end_time} />
					</div>
				</div>
			{/if}

			<div class="space-y-2">
				<Label for="override-reason">Reason (optional)</Label>
				<Input
					id="override-reason"
					placeholder="e.g., Holiday, Vacation, Conference"
					bind:value={overrideForm.reason}
				/>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (overrideDialogOpen = false)}>Cancel</Button>
			<Button onclick={handleSaveOverride} disabled={savingOverride}>
				{#if savingOverride}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Add Override
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Copy Hours Dialog -->
<Dialog.Root bind:open={copyDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Copy Hours</Dialog.Title>
			<Dialog.Description>
				{#if copySourceDay !== null}
					Copy {getDayName(rules[copySourceDay].day_of_week)}'s hours to other days
				{/if}
			</Dialog.Description>
		</Dialog.Header>

		<div class="py-4">
			<p class="mb-3 text-sm font-medium">Select days to copy to:</p>
			<div class="space-y-2">
				{#each rules as rule, index}
					{#if index !== copySourceDay}
						<button
							type="button"
							class="flex w-full items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted"
							class:bg-primary={copyTargetDays.includes(index)}
							class:text-primary-foreground={copyTargetDays.includes(index)}
							class:border-primary={copyTargetDays.includes(index)}
							onclick={() => toggleCopyTarget(index)}
						>
							<span class="font-medium">{getDayName(rule.day_of_week)}</span>
							{#if copyTargetDays.includes(index)}
								<Badge>Selected</Badge>
							{/if}
						</button>
					{/if}
				{/each}
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (copyDialogOpen = false)}>Cancel</Button>
			<Button onclick={applyCopyHours} disabled={copyTargetDays.length === 0}>
				Copy to {copyTargetDays.length} Day{copyTargetDays.length !== 1 ? 's' : ''}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
