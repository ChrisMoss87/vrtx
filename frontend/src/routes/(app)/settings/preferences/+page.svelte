<script lang="ts">
	import { onMount } from 'svelte';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Label } from '$lib/components/ui/label';
	import { RadioGroup, RadioGroupItem } from '$lib/components/ui/radio-group';
	import { Switch } from '$lib/components/ui/switch';
	import { Button } from '$lib/components/ui/button';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { sidebarStyle, type SidebarStyle } from '$lib/stores/sidebar';
	import { preferences } from '$lib/stores/preferences';
	import type { UserPreferences } from '$lib/api/preferences';
	import {
		Check,
		Sun,
		Moon,
		Monitor,
		Table,
		Bell,
		Mail,
		Volume2,
		Calendar,
		Clock,
		Globe,
		MessageSquare,
		CalendarSync,
		Loader2,
		Home,
		LayoutDashboard
	} from 'lucide-svelte';

	let loading = $state(true);
	let saving = $state(false);
	let currentPrefs = $state<UserPreferences>({});

	// Display
	let selectedSidebar = $state<SidebarStyle>('collapsible');
	let theme = $state<'light' | 'dark' | 'system'>('system');
	let compactMode = $state(false);
	let landingPage = $state<string>('dashboard');

	// Tables
	let rowsPerPage = $state<number>(25);

	// Notifications
	let emailNotifications = $state(true);
	let desktopNotifications = $state(true);
	let notificationSounds = $state(true);

	// Date & Time
	let dateFormat = $state<string>('MM/DD/YYYY');
	let timeFormat = $state<'12h' | '24h'>('12h');
	let weekStartsOn = $state<'sunday' | 'monday'>('sunday');
	let timezone = $state<string>(Intl.DateTimeFormat().resolvedOptions().timeZone);

	// Communication
	let emailSignature = $state('');
	let calendarSync = $state(false);

	const timezones = [
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
		// Subscribe to sidebar store
		const unsubSidebar = sidebarStyle.subscribe((value) => {
			selectedSidebar = value;
		});

		// Subscribe to preferences store
		const unsubPrefs = preferences.subscribe((prefs) => {
			currentPrefs = prefs;

			// Apply loaded preferences to local state
			if (prefs.theme) theme = prefs.theme;
			if (prefs.compact_mode !== undefined) compactMode = prefs.compact_mode;
			if (prefs.default_landing_page) landingPage = prefs.default_landing_page;
			if (prefs.default_rows_per_page) rowsPerPage = prefs.default_rows_per_page;
			if (prefs.email_notifications !== undefined) emailNotifications = prefs.email_notifications;
			if (prefs.desktop_notifications !== undefined) desktopNotifications = prefs.desktop_notifications;
			if (prefs.notification_sounds !== undefined) notificationSounds = prefs.notification_sounds;
			if (prefs.date_format) dateFormat = prefs.date_format;
			if (prefs.time_format) timeFormat = prefs.time_format;
			if (prefs.week_starts_on) weekStartsOn = prefs.week_starts_on;
			if (prefs.timezone) timezone = prefs.timezone;
			if (prefs.email_signature) emailSignature = prefs.email_signature;
			if (prefs.calendar_sync !== undefined) calendarSync = prefs.calendar_sync;

			loading = false;
		});

		return () => {
			unsubSidebar();
			unsubPrefs();
		};
	});

	async function savePref<K extends keyof UserPreferences>(key: K, value: UserPreferences[K]) {
		saving = true;
		try {
			await preferences.set(key, value);
		} catch (error) {
			console.error(`Failed to save ${key}:`, error);
		} finally {
			saving = false;
		}
	}

	async function handleSidebarChange(value: string) {
		if (value === 'rail' || value === 'collapsible') {
			selectedSidebar = value;
			await sidebarStyle.setStyle(value);
		}
	}

	async function handleThemeChange(value: string) {
		if (value === 'light' || value === 'dark' || value === 'system') {
			theme = value;
			await savePref('theme', value);
			// Apply theme
			if (value === 'dark') {
				document.documentElement.classList.add('dark');
			} else if (value === 'light') {
				document.documentElement.classList.remove('dark');
			} else {
				// System preference
				if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
					document.documentElement.classList.add('dark');
				} else {
					document.documentElement.classList.remove('dark');
				}
			}
		}
	}
</script>

<svelte:head>
	<title>Preferences | VRTX</title>
</svelte:head>

{#if loading}
	<div class="flex items-center justify-center h-64">
		<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
	</div>
{:else}
	<div class="max-w-4xl space-y-6">
		<div>
			<h1 class="text-2xl font-bold">Preferences</h1>
			<p class="text-muted-foreground">Customize your experience</p>
		</div>

		<!-- Display Section -->
		<Card>
			<CardHeader>
				<CardTitle class="flex items-center gap-2">
					<Monitor class="h-5 w-5" />
					Display
				</CardTitle>
				<CardDescription>
					Customize the appearance of the application
				</CardDescription>
			</CardHeader>
			<CardContent class="space-y-6">
				<!-- Theme -->
				<div class="space-y-3">
					<Label class="text-sm font-medium">Theme</Label>
					<RadioGroup value={theme} onValueChange={handleThemeChange}>
						<div class="grid grid-cols-3 gap-3">
							<label
								class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors hover:bg-muted/50 {theme === 'light' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="light" class="sr-only" />
								<Sun class="h-6 w-6" />
								<span class="text-sm font-medium">Light</span>
							</label>
							<label
								class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors hover:bg-muted/50 {theme === 'dark' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="dark" class="sr-only" />
								<Moon class="h-6 w-6" />
								<span class="text-sm font-medium">Dark</span>
							</label>
							<label
								class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors hover:bg-muted/50 {theme === 'system' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="system" class="sr-only" />
								<Monitor class="h-6 w-6" />
								<span class="text-sm font-medium">System</span>
							</label>
						</div>
					</RadioGroup>
				</div>

				<!-- Sidebar Style -->
				<div class="space-y-3">
					<Label class="text-sm font-medium">Sidebar Style</Label>
					<RadioGroup value={selectedSidebar} onValueChange={handleSidebarChange}>
						<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
							<!-- Rail Style -->
							<label
								class="relative flex cursor-pointer flex-col rounded-lg border-2 p-4 transition-colors hover:bg-muted/50 {selectedSidebar === 'rail' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="rail" class="sr-only" />
								<div class="flex items-start justify-between">
									<div class="space-y-1">
										<div class="font-medium">Rail</div>
										<div class="text-sm text-muted-foreground">
											Hover over icons to reveal flyout menus
										</div>
									</div>
									{#if selectedSidebar === 'rail'}
										<Check class="h-5 w-5 text-primary" />
									{/if}
								</div>
								<div class="mt-4 flex h-20 rounded border bg-muted/30">
									<div class="w-8 bg-slate-900 flex flex-col items-center py-2 gap-1 rounded-l">
										<div class="w-4 h-4 rounded bg-violet-500"></div>
										<div class="w-3 h-3 rounded bg-slate-700"></div>
										<div class="w-3 h-3 rounded bg-slate-700"></div>
									</div>
									<div class="flex-1 bg-background p-2">
										<div class="h-2 w-16 rounded bg-muted mb-1"></div>
										<div class="h-2 w-12 rounded bg-muted"></div>
									</div>
								</div>
							</label>

							<!-- Collapsible Style -->
							<label
								class="relative flex cursor-pointer flex-col rounded-lg border-2 p-4 transition-colors hover:bg-muted/50 {selectedSidebar === 'collapsible' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="collapsible" class="sr-only" />
								<div class="flex items-start justify-between">
									<div class="space-y-1">
										<div class="font-medium">Collapsible</div>
										<div class="text-sm text-muted-foreground">
											Click icons to expand navigation panel
										</div>
									</div>
									{#if selectedSidebar === 'collapsible'}
										<Check class="h-5 w-5 text-primary" />
									{/if}
								</div>
								<div class="mt-4 flex h-20 rounded border bg-muted/30">
									<div class="w-6 bg-zinc-100 dark:bg-zinc-900 border-r flex flex-col items-center py-2 gap-1 rounded-l">
										<div class="w-3 h-3 rounded bg-violet-500"></div>
										<div class="w-2 h-2 rounded bg-zinc-300 dark:bg-zinc-700"></div>
										<div class="w-2 h-2 rounded bg-zinc-300 dark:bg-zinc-700"></div>
									</div>
									<div class="w-24 bg-background border-r p-2">
										<div class="h-2 w-12 rounded bg-muted mb-1"></div>
										<div class="h-2 w-16 rounded bg-muted"></div>
									</div>
									<div class="flex-1 bg-muted/30 p-2">
										<div class="h-2 w-16 rounded bg-muted mb-1"></div>
										<div class="h-2 w-12 rounded bg-muted"></div>
									</div>
								</div>
							</label>
						</div>
					</RadioGroup>
				</div>

				<!-- Compact Mode -->
				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label class="text-sm font-medium">Compact Mode</Label>
						<p class="text-sm text-muted-foreground">Use a denser layout with smaller spacing</p>
					</div>
					<Switch
						checked={compactMode}
						onCheckedChange={async (checked) => {
							compactMode = checked;
							await savePref('compact_mode', checked);
						}}
					/>
				</div>

				<!-- Default Landing Page -->
				<div class="space-y-2">
					<Label class="text-sm font-medium">Default Landing Page</Label>
					<Select.Root
						type="single"
						value={{ value: landingPage, label: landingPage === 'dashboard' ? 'Dashboard' : landingPage === 'modules' ? 'Modules' : landingPage }}
						onValueChange={async (selected) => {
							if (selected?.value) {
								landingPage = selected.value;
								await savePref('default_landing_page', selected.value);
							}
						}}
					>
						<Select.Trigger class="w-full md:w-64">
							{#snippet children({ open })}
								<span class="flex items-center gap-2">
									{#if landingPage === 'dashboard'}
										<LayoutDashboard class="h-4 w-4" />
									{:else}
										<Home class="h-4 w-4" />
									{/if}
									{landingPage === 'dashboard' ? 'Dashboard' : landingPage === 'modules' ? 'Modules' : landingPage}
								</span>
							{/snippet}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="dashboard">
								<LayoutDashboard class="h-4 w-4 mr-2" />
								Dashboard
							</Select.Item>
							<Select.Item value="modules">
								<Home class="h-4 w-4 mr-2" />
								Modules
							</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>
			</CardContent>
		</Card>

		<!-- Tables Section -->
		<Card>
			<CardHeader>
				<CardTitle class="flex items-center gap-2">
					<Table class="h-5 w-5" />
					Tables
				</CardTitle>
				<CardDescription>
					Configure default settings for data tables
				</CardDescription>
			</CardHeader>
			<CardContent>
				<div class="space-y-2">
					<Label class="text-sm font-medium">Default Rows Per Page</Label>
					<Select.Root
						type="single"
						value={{ value: String(rowsPerPage), label: String(rowsPerPage) }}
						onValueChange={async (selected) => {
							if (selected?.value) {
								rowsPerPage = Number(selected.value);
								await savePref('default_rows_per_page', Number(selected.value) as 10 | 25 | 50 | 100);
							}
						}}
					>
						<Select.Trigger class="w-full md:w-64">
							{#snippet children({ open })}
								{rowsPerPage} rows
							{/snippet}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="10">10 rows</Select.Item>
							<Select.Item value="25">25 rows</Select.Item>
							<Select.Item value="50">50 rows</Select.Item>
							<Select.Item value="100">100 rows</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>
			</CardContent>
		</Card>

		<!-- Notifications Section -->
		<Card>
			<CardHeader>
				<CardTitle class="flex items-center gap-2">
					<Bell class="h-5 w-5" />
					Notifications
				</CardTitle>
				<CardDescription>
					Configure how you receive notifications
				</CardDescription>
			</CardHeader>
			<CardContent class="space-y-4">
				<div class="flex items-center justify-between">
					<div class="flex items-center gap-3">
						<Mail class="h-5 w-5 text-muted-foreground" />
						<div class="space-y-0.5">
							<Label class="text-sm font-medium">Email Notifications</Label>
							<p class="text-sm text-muted-foreground">Receive notifications via email</p>
						</div>
					</div>
					<Switch
						checked={emailNotifications}
						onCheckedChange={async (checked) => {
							emailNotifications = checked;
							await savePref('email_notifications', checked);
						}}
					/>
				</div>

				<div class="flex items-center justify-between">
					<div class="flex items-center gap-3">
						<Bell class="h-5 w-5 text-muted-foreground" />
						<div class="space-y-0.5">
							<Label class="text-sm font-medium">Desktop Notifications</Label>
							<p class="text-sm text-muted-foreground">Show browser push notifications</p>
						</div>
					</div>
					<Switch
						checked={desktopNotifications}
						onCheckedChange={async (checked) => {
							desktopNotifications = checked;
							await savePref('desktop_notifications', checked);
							if (checked && 'Notification' in window) {
								await Notification.requestPermission();
							}
						}}
					/>
				</div>

				<div class="flex items-center justify-between">
					<div class="flex items-center gap-3">
						<Volume2 class="h-5 w-5 text-muted-foreground" />
						<div class="space-y-0.5">
							<Label class="text-sm font-medium">Notification Sounds</Label>
							<p class="text-sm text-muted-foreground">Play sounds for new notifications</p>
						</div>
					</div>
					<Switch
						checked={notificationSounds}
						onCheckedChange={async (checked) => {
							notificationSounds = checked;
							await savePref('notification_sounds', checked);
						}}
					/>
				</div>
			</CardContent>
		</Card>

		<!-- Date & Time Section -->
		<Card>
			<CardHeader>
				<CardTitle class="flex items-center gap-2">
					<Calendar class="h-5 w-5" />
					Date & Time
				</CardTitle>
				<CardDescription>
					Configure date and time display formats
				</CardDescription>
			</CardHeader>
			<CardContent class="space-y-6">
				<!-- Date Format -->
				<div class="space-y-2">
					<Label class="text-sm font-medium">Date Format</Label>
					<Select.Root
						type="single"
						value={{ value: dateFormat, label: dateFormat }}
						onValueChange={async (selected) => {
							if (selected?.value) {
								dateFormat = selected.value;
								await savePref('date_format', selected.value);
							}
						}}
					>
						<Select.Trigger class="w-full md:w-64">
							{#snippet children({ open })}
								{dateFormat}
							{/snippet}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="MM/DD/YYYY">MM/DD/YYYY (12/25/2024)</Select.Item>
							<Select.Item value="DD/MM/YYYY">DD/MM/YYYY (25/12/2024)</Select.Item>
							<Select.Item value="YYYY-MM-DD">YYYY-MM-DD (2024-12-25)</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>

				<!-- Time Format -->
				<div class="space-y-3">
					<Label class="text-sm font-medium">Time Format</Label>
					<RadioGroup
						value={timeFormat}
						onValueChange={async (value) => {
							if (value === '12h' || value === '24h') {
								timeFormat = value;
								await savePref('time_format', value);
							}
						}}
					>
						<div class="flex gap-4">
							<label
								class="flex cursor-pointer items-center gap-3 rounded-lg border-2 px-4 py-3 transition-colors hover:bg-muted/50 {timeFormat === '12h' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="12h" class="sr-only" />
								<Clock class="h-5 w-5" />
								<div>
									<div class="font-medium">12-hour</div>
									<div class="text-sm text-muted-foreground">2:30 PM</div>
								</div>
							</label>
							<label
								class="flex cursor-pointer items-center gap-3 rounded-lg border-2 px-4 py-3 transition-colors hover:bg-muted/50 {timeFormat === '24h' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="24h" class="sr-only" />
								<Clock class="h-5 w-5" />
								<div>
									<div class="font-medium">24-hour</div>
									<div class="text-sm text-muted-foreground">14:30</div>
								</div>
							</label>
						</div>
					</RadioGroup>
				</div>

				<!-- Week Starts On -->
				<div class="space-y-3">
					<Label class="text-sm font-medium">Week Starts On</Label>
					<RadioGroup
						value={weekStartsOn}
						onValueChange={async (value) => {
							if (value === 'sunday' || value === 'monday') {
								weekStartsOn = value;
								await savePref('week_starts_on', value);
							}
						}}
					>
						<div class="flex gap-4">
							<label
								class="flex cursor-pointer items-center gap-2 rounded-lg border-2 px-4 py-2 transition-colors hover:bg-muted/50 {weekStartsOn === 'sunday' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="sunday" class="sr-only" />
								<span class="font-medium">Sunday</span>
							</label>
							<label
								class="flex cursor-pointer items-center gap-2 rounded-lg border-2 px-4 py-2 transition-colors hover:bg-muted/50 {weekStartsOn === 'monday' ? 'border-primary bg-primary/5' : 'border-muted'}"
							>
								<RadioGroupItem value="monday" class="sr-only" />
								<span class="font-medium">Monday</span>
							</label>
						</div>
					</RadioGroup>
				</div>

				<!-- Timezone -->
				<div class="space-y-2">
					<Label class="text-sm font-medium">Timezone</Label>
					<Select.Root
						type="single"
						value={{ value: timezone, label: timezone.replace(/_/g, ' ') }}
						onValueChange={async (selected) => {
							if (selected?.value) {
								timezone = selected.value;
								await savePref('timezone', selected.value);
							}
						}}
					>
						<Select.Trigger class="w-full md:w-64">
							{#snippet children({ open })}
								<span class="flex items-center gap-2">
									<Globe class="h-4 w-4" />
									{timezone.replace(/_/g, ' ')}
								</span>
							{/snippet}
						</Select.Trigger>
						<Select.Content>
							{#each timezones as tz}
								<Select.Item value={tz}>{tz.replace(/_/g, ' ')}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</CardContent>
		</Card>

		<!-- Communication Section -->
		<Card>
			<CardHeader>
				<CardTitle class="flex items-center gap-2">
					<MessageSquare class="h-5 w-5" />
					Communication
				</CardTitle>
				<CardDescription>
					Configure email and calendar settings
				</CardDescription>
			</CardHeader>
			<CardContent class="space-y-6">
				<!-- Email Signature -->
				<div class="space-y-2">
					<Label class="text-sm font-medium">Email Signature</Label>
					<Textarea
						bind:value={emailSignature}
						placeholder="Enter your default email signature..."
						rows={4}
						class="resize-none"
					/>
					<Button
						variant="outline"
						size="sm"
						onclick={async () => {
							await savePref('email_signature', emailSignature);
						}}
						disabled={saving}
					>
						{#if saving}
							<Loader2 class="h-4 w-4 mr-2 animate-spin" />
						{/if}
						Save Signature
					</Button>
				</div>

				<!-- Calendar Sync -->
				<div class="flex items-center justify-between">
					<div class="flex items-center gap-3">
						<CalendarSync class="h-5 w-5 text-muted-foreground" />
						<div class="space-y-0.5">
							<Label class="text-sm font-medium">Calendar Sync</Label>
							<p class="text-sm text-muted-foreground">Sync events with your calendar</p>
						</div>
					</div>
					<Switch
						checked={calendarSync}
						onCheckedChange={async (checked) => {
							calendarSync = checked;
							await savePref('calendar_sync', checked);
						}}
					/>
				</div>
			</CardContent>
		</Card>

		{#if saving}
			<div class="fixed bottom-4 right-4 bg-primary text-primary-foreground px-4 py-2 rounded-lg shadow-lg flex items-center gap-2">
				<Loader2 class="h-4 w-4 animate-spin" />
				Saving...
			</div>
		{/if}
	</div>
{/if}
