<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Button } from '$lib/components/ui/button';
	import * as Select from '$lib/components/ui/select';
	import { getRottingSettings, updateRottingSettings, type RottingAlertSetting } from '$lib/api/rotting';
	import { toast } from 'svelte-sonner';

	interface Props {
		pipelineId?: number;
		class?: string;
	}

	let { pipelineId, class: className = '' }: Props = $props();

	let settings = $state<RottingAlertSetting | null>(null);
	let loading = $state(true);
	let saving = $state(false);
	let error = $state<string | null>(null);

	// Form state
	let emailDigestEnabled = $state(false);
	let digestFrequency = $state<'daily' | 'weekly' | 'none'>('daily');
	let inAppNotifications = $state(true);
	let excludeWeekends = $state(false);

	async function loadSettings() {
		loading = true;
		error = null;
		try {
			settings = await getRottingSettings(pipelineId);
			emailDigestEnabled = settings.email_digest_enabled;
			digestFrequency = settings.digest_frequency;
			inAppNotifications = settings.in_app_notifications;
			excludeWeekends = settings.exclude_weekends;
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to load settings';
		} finally {
			loading = false;
		}
	}

	async function saveSettings() {
		saving = true;
		try {
			await updateRottingSettings({
				pipeline_id: pipelineId ?? null,
				email_digest_enabled: emailDigestEnabled,
				digest_frequency: digestFrequency,
				in_app_notifications: inAppNotifications,
				exclude_weekends: excludeWeekends
			});
			toast.success('Settings saved successfully');
		} catch (e) {
			toast.error(e instanceof Error ? e.message : 'Failed to save settings');
		} finally {
			saving = false;
		}
	}

	$effect(() => {
		loadSettings();
	});

	const frequencyOptions = [
		{ value: 'daily', label: 'Daily' },
		{ value: 'weekly', label: 'Weekly' },
		{ value: 'none', label: 'Never' }
	];
</script>

<Card class={className}>
	<CardHeader>
		<CardTitle>Rotting Alert Settings</CardTitle>
		<CardDescription>Configure how you want to be notified about rotting deals</CardDescription>
	</CardHeader>
	<CardContent>
		{#if loading}
			<div class="space-y-4 animate-pulse">
				<div class="h-10 bg-muted rounded" />
				<div class="h-10 bg-muted rounded" />
				<div class="h-10 bg-muted rounded" />
			</div>
		{:else if error}
			<div class="text-center py-4 text-muted-foreground">
				<p class="text-sm">{error}</p>
				<Button variant="ghost" size="sm" onclick={loadSettings} class="mt-2">Retry</Button>
			</div>
		{:else}
			<div class="space-y-6">
				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label>In-App Notifications</Label>
						<p class="text-sm text-muted-foreground">
							Receive notifications in the app when deals start rotting
						</p>
					</div>
					<Switch bind:checked={inAppNotifications} />
				</div>

				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label>Email Digest</Label>
						<p class="text-sm text-muted-foreground">
							Receive a summary of rotting deals via email
						</p>
					</div>
					<Switch bind:checked={emailDigestEnabled} />
				</div>

				{#if emailDigestEnabled}
					<div class="space-y-2">
						<Label>Digest Frequency</Label>
						<Select.Root type="single" bind:value={digestFrequency}>
							<Select.Trigger class="w-full">
								{frequencyOptions.find((o) => o.value === digestFrequency)?.label ?? 'Select frequency'}
							</Select.Trigger>
							<Select.Content>
								{#each frequencyOptions as option}
									<Select.Item value={option.value}>{option.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
				{/if}

				<div class="flex items-center justify-between">
					<div class="space-y-0.5">
						<Label>Exclude Weekends</Label>
						<p class="text-sm text-muted-foreground">
							Don't count weekend days when calculating inactivity
						</p>
					</div>
					<Switch bind:checked={excludeWeekends} />
				</div>

				<Button onclick={saveSettings} disabled={saving} class="w-full">
					{saving ? 'Saving...' : 'Save Settings'}
				</Button>
			</div>
		{/if}
	</CardContent>
</Card>
