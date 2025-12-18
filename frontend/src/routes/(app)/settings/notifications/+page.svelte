<script lang="ts">
	import { onMount } from 'svelte';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Separator } from '$lib/components/ui/separator';
	import {
		notifications,
		notificationPreferences,
		notificationSchedule
	} from '$lib/stores/notifications';
	import type { NotificationCategory, EmailFrequency } from '$lib/api/notifications';
	import {
		Bell,
		Mail,
		Smartphone,
		Monitor,
		Moon,
		Clock,
		CheckCircle,
		UserPlus,
		AtSign,
		Edit,
		Trophy,
		Clipboard,
		Settings,
		Loader2,
		Calendar
	} from 'lucide-svelte';

	let loading = $state(true);
	let saving = $state(false);

	// Schedule state
	let dndEnabled = $state(false);
	let quietHoursEnabled = $state(false);
	let quietHoursStart = $state('22:00');
	let quietHoursEnd = $state('08:00');
	let weekendNotifications = $state(true);
	let timezone = $state('UTC');

	const categoryIcons: Record<NotificationCategory, typeof Bell> = {
		approvals: CheckCircle,
		assignments: UserPlus,
		mentions: AtSign,
		updates: Edit,
		reminders: Bell,
		deals: Trophy,
		tasks: Clipboard,
		system: Settings
	};

	const frequencyOptions: Array<{ value: EmailFrequency; label: string }> = [
		{ value: 'immediate', label: 'Immediately' },
		{ value: 'hourly', label: 'Hourly digest' },
		{ value: 'daily', label: 'Daily digest' },
		{ value: 'weekly', label: 'Weekly digest' }
	];

	const timezones = [
		'UTC',
		'America/New_York',
		'America/Chicago',
		'America/Denver',
		'America/Los_Angeles',
		'America/Anchorage',
		'Pacific/Honolulu',
		'Europe/London',
		'Europe/Paris',
		'Europe/Berlin',
		'Asia/Tokyo',
		'Asia/Shanghai',
		'Asia/Dubai',
		'Australia/Sydney',
		'Pacific/Auckland'
	];

	onMount(() => {
		// Load preferences
		notifications.loadPreferences().then(() => {
			loading = false;
		});

		// Set schedule state from loaded data
		const unsub = notificationSchedule.subscribe((schedule) => {
			if (schedule) {
				dndEnabled = schedule.dnd_enabled;
				quietHoursEnabled = schedule.quiet_hours_enabled;
				quietHoursStart = schedule.quiet_hours_start || '22:00';
				quietHoursEnd = schedule.quiet_hours_end || '08:00';
				weekendNotifications = schedule.weekend_notifications;
				timezone = schedule.timezone;
			}
		});

		return unsub;
	});

	async function toggleChannel(
		category: NotificationCategory,
		channel: 'in_app' | 'email' | 'push',
		value: boolean
	) {
		saving = true;
		try {
			await notifications.updatePreference(category, { [channel]: value });
		} finally {
			saving = false;
		}
	}

	async function updateEmailFrequency(category: NotificationCategory, frequency: EmailFrequency) {
		saving = true;
		try {
			await notifications.updatePreference(category, { email_frequency: frequency });
		} finally {
			saving = false;
		}
	}

	async function updateSchedule(updates: Record<string, unknown>) {
		saving = true;
		try {
			await notifications.updateSchedule(updates as Parameters<typeof notifications.updateSchedule>[0]);
		} finally {
			saving = false;
		}
	}

	function getCategoryPref(category: NotificationCategory) {
		return $notificationPreferences.find((p) => p.category === category);
	}
</script>

<svelte:head>
	<title>Notification Settings | VRTX</title>
</svelte:head>

{#if loading}
	<div class="flex items-center justify-center h-64">
		<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
	</div>
{:else}
	<div class="max-w-4xl space-y-6">
		<div>
			<h1 class="text-2xl font-bold">Notification Settings</h1>
			<p class="text-muted-foreground">Choose what notifications you receive and how</p>
		</div>

		<!-- Do Not Disturb -->
		<Card>
			<CardHeader>
				<CardTitle class="flex items-center gap-2">
					<Moon class="h-5 w-5" />
					Do Not Disturb
				</CardTitle>
				<CardDescription>
					Pause all notifications temporarily
				</CardDescription>
			</CardHeader>
			<CardContent class="space-y-4">
				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label class="text-sm font-medium">Enable Do Not Disturb</Label>
						<p class="text-sm text-muted-foreground">
							Silence all notifications until you turn this off
						</p>
					</div>
					<Switch
						checked={dndEnabled}
						onCheckedChange={async (checked) => {
							dndEnabled = checked;
							await updateSchedule({ dnd_enabled: checked });
						}}
					/>
				</div>
			</CardContent>
		</Card>

		<!-- Quiet Hours -->
		<Card>
			<CardHeader>
				<CardTitle class="flex items-center gap-2">
					<Clock class="h-5 w-5" />
					Quiet Hours
				</CardTitle>
				<CardDescription>
					Automatically silence notifications during specific times
				</CardDescription>
			</CardHeader>
			<CardContent class="space-y-4">
				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label class="text-sm font-medium">Enable Quiet Hours</Label>
						<p class="text-sm text-muted-foreground">
							Silence notifications during scheduled hours
						</p>
					</div>
					<Switch
						checked={quietHoursEnabled}
						onCheckedChange={async (checked) => {
							quietHoursEnabled = checked;
							await updateSchedule({ quiet_hours_enabled: checked });
						}}
					/>
				</div>

				{#if quietHoursEnabled}
					<div class="grid gap-4 pt-4 sm:grid-cols-2">
						<div class="space-y-2">
							<Label class="text-sm font-medium">Start Time</Label>
							<Input
								type="time"
								value={quietHoursStart}
								onchange={async (e: Event) => {
									const target = e.target as HTMLInputElement;
									quietHoursStart = target.value;
									await updateSchedule({ quiet_hours_start: target.value });
								}}
							/>
						</div>
						<div class="space-y-2">
							<Label class="text-sm font-medium">End Time</Label>
							<Input
								type="time"
								value={quietHoursEnd}
								onchange={async (e: Event) => {
									const target = e.target as HTMLInputElement;
									quietHoursEnd = target.value;
									await updateSchedule({ quiet_hours_end: target.value });
								}}
							/>
						</div>
					</div>
				{/if}

				<Separator />

				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label class="text-sm font-medium">Weekend Notifications</Label>
						<p class="text-sm text-muted-foreground">
							Receive notifications on Saturday and Sunday
						</p>
					</div>
					<Switch
						checked={weekendNotifications}
						onCheckedChange={async (checked) => {
							weekendNotifications = checked;
							await updateSchedule({ weekend_notifications: checked });
						}}
					/>
				</div>

				<div class="space-y-2">
					<Label class="text-sm font-medium">Timezone</Label>
					<Select.Root
						type="single"
						value={timezone}
						onValueChange={async (value) => {
							if (value) {
								timezone = value;
								await updateSchedule({ timezone: value });
							}
						}}
					>
						<Select.Trigger class="w-full sm:w-64">
							{timezone.replace(/_/g, ' ')}
						</Select.Trigger>
						<Select.Content>
							{#each timezones as tz}
								<Select.Item value={tz} label={tz.replace(/_/g, ' ')}>
									{tz.replace(/_/g, ' ')}
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</CardContent>
		</Card>

		<!-- Notification Categories -->
		<Card>
			<CardHeader>
				<CardTitle class="flex items-center gap-2">
					<Bell class="h-5 w-5" />
					Notification Categories
				</CardTitle>
				<CardDescription>
					Choose which notifications you want to receive and how
				</CardDescription>
			</CardHeader>
			<CardContent>
				<!-- Header -->
				<div class="mb-4 hidden sm:grid sm:grid-cols-[1fr,80px,80px,80px,140px] sm:gap-4 text-sm font-medium text-muted-foreground">
					<div>Category</div>
					<div class="text-center">
						<Monitor class="mx-auto h-4 w-4" />
						<span class="text-xs">In-App</span>
					</div>
					<div class="text-center">
						<Mail class="mx-auto h-4 w-4" />
						<span class="text-xs">Email</span>
					</div>
					<div class="text-center">
						<Smartphone class="mx-auto h-4 w-4" />
						<span class="text-xs">Push</span>
					</div>
					<div class="text-center">Email Frequency</div>
				</div>

				<div class="space-y-4">
					{#each $notificationPreferences as pref (pref.category)}
						{@const Icon = categoryIcons[pref.category] || Bell}
						<div class="rounded-lg border p-4">
							<!-- Mobile: Stack vertically -->
							<div class="sm:hidden space-y-4">
								<div class="flex items-center gap-3">
									<Icon class="h-5 w-5 text-muted-foreground" />
									<div>
										<div class="font-medium">{pref.label}</div>
										<div class="text-sm text-muted-foreground">{pref.description}</div>
									</div>
								</div>
								<div class="grid grid-cols-3 gap-4">
									<div class="text-center">
										<Label class="text-xs text-muted-foreground">In-App</Label>
										<Switch
											checked={pref.in_app}
											onCheckedChange={(checked) =>
												toggleChannel(pref.category, 'in_app', checked)}
											class="mt-1"
										/>
									</div>
									<div class="text-center">
										<Label class="text-xs text-muted-foreground">Email</Label>
										<Switch
											checked={pref.email}
											onCheckedChange={(checked) =>
												toggleChannel(pref.category, 'email', checked)}
											class="mt-1"
										/>
									</div>
									<div class="text-center">
										<Label class="text-xs text-muted-foreground">Push</Label>
										<Switch
											checked={pref.push}
											onCheckedChange={(checked) =>
												toggleChannel(pref.category, 'push', checked)}
											class="mt-1"
										/>
									</div>
								</div>
								{#if pref.email}
									<div>
										<Label class="text-xs text-muted-foreground">Email Frequency</Label>
										<Select.Root
											type="single"
											value={pref.email_frequency}
											onValueChange={(value) => {
												if (value) {
													updateEmailFrequency(pref.category, value as EmailFrequency);
												}
											}}
										>
											<Select.Trigger class="mt-1 w-full">
												{frequencyOptions.find((f) => f.value === pref.email_frequency)?.label ||
													'Immediately'}
											</Select.Trigger>
											<Select.Content>
												{#each frequencyOptions as option}
													<Select.Item value={option.value} label={option.label}>
														{option.label}
													</Select.Item>
												{/each}
											</Select.Content>
										</Select.Root>
									</div>
								{/if}
							</div>

							<!-- Desktop: Grid layout -->
							<div class="hidden sm:grid sm:grid-cols-[1fr,80px,80px,80px,140px] sm:items-center sm:gap-4">
								<div class="flex items-center gap-3">
									<Icon class="h-5 w-5 text-muted-foreground" />
									<div>
										<div class="font-medium">{pref.label}</div>
										<div class="text-sm text-muted-foreground">{pref.description}</div>
									</div>
								</div>
								<div class="flex justify-center">
									<Switch
										checked={pref.in_app}
										onCheckedChange={(checked) =>
											toggleChannel(pref.category, 'in_app', checked)}
									/>
								</div>
								<div class="flex justify-center">
									<Switch
										checked={pref.email}
										onCheckedChange={(checked) =>
											toggleChannel(pref.category, 'email', checked)}
									/>
								</div>
								<div class="flex justify-center">
									<Switch
										checked={pref.push}
										onCheckedChange={(checked) =>
											toggleChannel(pref.category, 'push', checked)}
									/>
								</div>
								<div>
									{#if pref.email}
										<Select.Root
											type="single"
											value={pref.email_frequency}
											onValueChange={(value) => {
												if (value) {
													updateEmailFrequency(pref.category, value as EmailFrequency);
												}
											}}
										>
											<Select.Trigger class="h-8 text-xs">
												{frequencyOptions.find((f) => f.value === pref.email_frequency)?.label ||
													'Immediately'}
											</Select.Trigger>
											<Select.Content>
												{#each frequencyOptions as option}
													<Select.Item value={option.value} label={option.label}>
														{option.label}
													</Select.Item>
												{/each}
											</Select.Content>
										</Select.Root>
									{:else}
										<span class="text-sm text-muted-foreground">-</span>
									{/if}
								</div>
							</div>
						</div>
					{/each}
				</div>
			</CardContent>
		</Card>

		<!-- Quick Actions -->
		<Card>
			<CardHeader>
				<CardTitle>Quick Actions</CardTitle>
			</CardHeader>
			<CardContent class="flex flex-wrap gap-3">
				<Button
					variant="outline"
					onclick={async () => {
						saving = true;
						try {
							const allOn = $notificationPreferences.map((p) => ({
								category: p.category,
								in_app: true,
								email: true
							}));
							for (const pref of allOn) {
								await notifications.updatePreference(pref.category, {
									in_app: pref.in_app,
									email: pref.email
								});
							}
						} finally {
							saving = false;
						}
					}}
				>
					Enable All
				</Button>
				<Button
					variant="outline"
					onclick={async () => {
						saving = true;
						try {
							for (const pref of $notificationPreferences) {
								await notifications.updatePreference(pref.category, {
									email: false
								});
							}
						} finally {
							saving = false;
						}
					}}
				>
					Disable All Emails
				</Button>
				<Button
					variant="outline"
					onclick={async () => {
						saving = true;
						try {
							for (const pref of $notificationPreferences) {
								await notifications.updatePreference(pref.category, {
									in_app: true,
									email: false,
									push: false
								});
							}
						} finally {
							saving = false;
						}
					}}
				>
					In-App Only
				</Button>
			</CardContent>
		</Card>

		{#if saving}
			<div
				class="fixed bottom-4 right-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-primary-foreground shadow-lg"
			>
				<Loader2 class="h-4 w-4 animate-spin" />
				Saving...
			</div>
		{/if}
	</div>
{/if}
