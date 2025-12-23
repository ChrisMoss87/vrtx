<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { Separator } from '$lib/components/ui/separator';
	import {
		ArrowLeft,
		Clock,
		Calendar,
		Mail,
		FileText,
		Save,
		Trash2,
		Plus,
		X
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { reportsApi, type Report, type ReportSchedule } from '$lib/api/reports';

	let report = $state<Report | null>(null);
	let loading = $state(true);
	let saving = $state(false);

	// Schedule form state
	let enabled = $state(false);
	let frequency = $state<'hourly' | 'daily' | 'weekly' | 'monthly'>('daily');
	let time = $state('09:00');
	let dayOfWeek = $state(1); // Monday
	let dayOfMonth = $state(1);
	let recipients = $state<string[]>([]);
	let newRecipient = $state('');
	let format = $state<'pdf' | 'csv' | 'xlsx'>('pdf');

	const reportId = $derived(Number($page.params.id));

	onMount(async () => {
		await loadReport();
	});

	async function loadReport() {
		loading = true;
		try {
			report = await reportsApi.get(reportId);

			// Load existing schedule
			if (report.schedule) {
				enabled = report.schedule.enabled;
				frequency = report.schedule.frequency || 'daily';
				time = report.schedule.time || '09:00';
				dayOfWeek = report.schedule.day_of_week ?? 1;
				dayOfMonth = report.schedule.day_of_month ?? 1;
				recipients = report.schedule.recipients || [];
				format = report.schedule.format || 'pdf';
			}
		} catch (error) {
			console.error('Failed to load report:', error);
			toast.error('Failed to load report');
			goto('/reports');
		} finally {
			loading = false;
		}
	}

	function addRecipient() {
		const email = newRecipient.trim().toLowerCase();
		if (!email) return;

		// Basic email validation
		if (!email.includes('@') || !email.includes('.')) {
			toast.error('Please enter a valid email address');
			return;
		}

		if (recipients.includes(email)) {
			toast.error('Email already added');
			return;
		}

		recipients = [...recipients, email];
		newRecipient = '';
	}

	function removeRecipient(email: string) {
		recipients = recipients.filter(r => r !== email);
	}

	async function handleSave() {
		if (enabled && recipients.length === 0) {
			toast.error('Please add at least one recipient');
			return;
		}

		saving = true;
		try {
			const scheduleData = enabled ? {
				enabled: true,
				frequency,
				time,
				day_of_week: frequency === 'weekly' ? dayOfWeek : undefined,
				day_of_month: frequency === 'monthly' ? dayOfMonth : undefined,
				recipients,
				format
			} : {
				enabled: false
			};

			await reportsApi.updateSchedule(reportId, scheduleData);
			toast.success(enabled ? 'Schedule saved' : 'Schedule disabled');
			goto(`/reports/${reportId}`);
		} catch (error) {
			console.error('Failed to save schedule:', error);
			toast.error('Failed to save schedule');
		} finally {
			saving = false;
		}
	}

	const daysOfWeek = [
		{ value: 0, label: 'Sunday' },
		{ value: 1, label: 'Monday' },
		{ value: 2, label: 'Tuesday' },
		{ value: 3, label: 'Wednesday' },
		{ value: 4, label: 'Thursday' },
		{ value: 5, label: 'Friday' },
		{ value: 6, label: 'Saturday' }
	];

	const frequencyOptions = [
		{ value: 'hourly', label: 'Hourly' },
		{ value: 'daily', label: 'Daily' },
		{ value: 'weekly', label: 'Weekly' },
		{ value: 'monthly', label: 'Monthly' }
	];

	const formatOptions = [
		{ value: 'pdf', label: 'PDF' },
		{ value: 'csv', label: 'CSV' },
		{ value: 'xlsx', label: 'Excel' }
	];
</script>

<svelte:head>
	<title>Schedule Report | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto max-w-2xl p-6">
	{#if loading}
		<div class="space-y-4">
			<div class="flex items-center gap-4">
				<Skeleton class="h-8 w-8" />
				<Skeleton class="h-8 w-64" />
			</div>
			<Skeleton class="h-96 w-full" />
		</div>
	{:else if report}
		<!-- Header -->
		<div class="mb-6 flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto(`/reports/${reportId}`)}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">Schedule Report</h1>
				<p class="text-muted-foreground">{report.name}</p>
			</div>
		</div>

		<Card.Root>
			<Card.Header>
				<div class="flex items-center justify-between">
					<div class="flex items-center gap-2">
						<Clock class="h-5 w-5" />
						<Card.Title>Schedule Settings</Card.Title>
					</div>
					<div class="flex items-center gap-2">
						<Label for="enabled">Enable Schedule</Label>
						<Switch id="enabled" bind:checked={enabled} />
					</div>
				</div>
				<Card.Description>
					Configure when this report should be automatically sent to recipients
				</Card.Description>
			</Card.Header>

			<Card.Content class="space-y-6">
				{#if enabled}
					<!-- Frequency -->
					<div class="space-y-2">
						<Label>Frequency</Label>
						<Select.Root
							type="single"
							value={frequency}
							onValueChange={(v) => { if (v) frequency = v as typeof frequency; }}
						>
							<Select.Trigger>
								<Calendar class="mr-2 h-4 w-4" />
								<span>{frequencyOptions.find(o => o.value === frequency)?.label}</span>
							</Select.Trigger>
							<Select.Content>
								{#each frequencyOptions as option}
									<Select.Item value={option.value}>{option.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>

					<!-- Time (not for hourly) -->
					{#if frequency !== 'hourly'}
						<div class="space-y-2">
							<Label for="time">Time</Label>
							<Input
								id="time"
								type="time"
								bind:value={time}
							/>
							<p class="text-xs text-muted-foreground">
								Report will be sent at this time (server timezone)
							</p>
						</div>
					{/if}

					<!-- Day of Week (for weekly) -->
					{#if frequency === 'weekly'}
						<div class="space-y-2">
							<Label>Day of Week</Label>
							<Select.Root
								type="single"
								value={dayOfWeek.toString()}
								onValueChange={(v) => { if (v) dayOfWeek = parseInt(v); }}
							>
								<Select.Trigger>
									<span>{daysOfWeek.find(d => d.value === dayOfWeek)?.label}</span>
								</Select.Trigger>
								<Select.Content>
									{#each daysOfWeek as day}
										<Select.Item value={day.value.toString()}>{day.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
					{/if}

					<!-- Day of Month (for monthly) -->
					{#if frequency === 'monthly'}
						<div class="space-y-2">
							<Label>Day of Month</Label>
							<Select.Root
								type="single"
								value={dayOfMonth.toString()}
								onValueChange={(v) => { if (v) dayOfMonth = parseInt(v); }}
							>
								<Select.Trigger>
									<span>{dayOfMonth}</span>
								</Select.Trigger>
								<Select.Content>
									{#each Array.from({ length: 28 }, (_, i) => i + 1) as day}
										<Select.Item value={day.toString()}>{day}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
							<p class="text-xs text-muted-foreground">
								Using 1-28 to ensure compatibility with all months
							</p>
						</div>
					{/if}

					<Separator />

					<!-- Export Format -->
					<div class="space-y-2">
						<Label>Export Format</Label>
						<Select.Root
							type="single"
							value={format}
							onValueChange={(v) => { if (v) format = v as typeof format; }}
						>
							<Select.Trigger>
								<FileText class="mr-2 h-4 w-4" />
								<span>{formatOptions.find(o => o.value === format)?.label}</span>
							</Select.Trigger>
							<Select.Content>
								{#each formatOptions as option}
									<Select.Item value={option.value}>{option.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>

					<Separator />

					<!-- Recipients -->
					<div class="space-y-2">
						<Label class="flex items-center gap-2">
							<Mail class="h-4 w-4" />
							Recipients
						</Label>

						<div class="flex gap-2">
							<Input
								placeholder="Enter email address"
								bind:value={newRecipient}
								onkeydown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addRecipient(); }}}
							/>
							<Button variant="outline" onclick={addRecipient}>
								<Plus class="h-4 w-4" />
							</Button>
						</div>

						{#if recipients.length > 0}
							<div class="flex flex-wrap gap-2 pt-2">
								{#each recipients as email}
									<Badge variant="secondary" class="flex items-center gap-1 pr-1">
										{email}
										<Button
											variant="ghost"
											size="sm"
											class="h-auto p-0.5"
											onclick={() => removeRecipient(email)}
										>
											<X class="h-3 w-3" />
										</Button>
									</Badge>
								{/each}
							</div>
						{:else}
							<p class="text-sm text-muted-foreground">
								No recipients added. Add at least one email address to receive the scheduled report.
							</p>
						{/if}
					</div>
				{:else}
					<div class="flex flex-col items-center justify-center py-8 text-center">
						<Clock class="mb-4 h-12 w-12 text-muted-foreground" />
						<h3 class="mb-2 text-lg font-medium">Schedule Disabled</h3>
						<p class="text-muted-foreground">
							Enable scheduling to automatically send this report to recipients on a regular basis.
						</p>
					</div>
				{/if}
			</Card.Content>

			<Card.Footer class="flex justify-between">
				<Button variant="outline" onclick={() => goto(`/reports/${reportId}`)}>
					Cancel
				</Button>
				<Button onclick={handleSave} disabled={saving}>
					<Save class="mr-2 h-4 w-4" />
					{saving ? 'Saving...' : 'Save Schedule'}
				</Button>
			</Card.Footer>
		</Card.Root>
	{/if}
</div>
