<script lang="ts">
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { ArrowLeft, Loader2, Check, X } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { createSchedulingPage, checkSlugAvailability } from '$lib/api/scheduling';

	let name = $state('');
	let slug = $state('');
	let description = $state('');
	let timezone = $state(Intl.DateTimeFormat().resolvedOptions().timeZone);
	let isActive = $state(true);
	let primaryColor = $state('#3B82F6');

	let saving = $state(false);
	let slugChecking = $state(false);
	let slugAvailable = $state<boolean | null>(null);
	let slugTimeout: ReturnType<typeof setTimeout> | null = null;

	// Common timezones
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

	function generateSlug(text: string): string {
		return text
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-|-$/g, '');
	}

	function handleNameChange(e: Event) {
		const target = e.target as HTMLInputElement;
		name = target.value;
		// Auto-generate slug from name if slug hasn't been manually edited
		if (!slug || slug === generateSlug(name.slice(0, -1))) {
			slug = generateSlug(name);
			checkSlug();
		}
	}

	function handleSlugChange(e: Event) {
		const target = e.target as HTMLInputElement;
		slug = generateSlug(target.value);
		checkSlug();
	}

	function checkSlug() {
		if (slugTimeout) clearTimeout(slugTimeout);
		if (!slug) {
			slugAvailable = null;
			return;
		}

		slugChecking = true;
		slugTimeout = setTimeout(async () => {
			try {
				const result = await checkSlugAvailability(slug);
				slugAvailable = result.available;
			} catch (error) {
				console.error('Failed to check slug:', error);
			} finally {
				slugChecking = false;
			}
		}, 300);
	}

	async function handleSubmit() {
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
			const page = await createSchedulingPage({
				name: name.trim(),
				slug: slug.trim(),
				description: description.trim() || undefined,
				timezone,
				is_active: isActive,
				branding: {
					primary_color: primaryColor
				}
			});

			toast.success('Scheduling page created');
			goto(`/settings/scheduling/pages/${page.id}`);
		} catch (error: any) {
			console.error('Failed to create page:', error);
			toast.error(error.response?.data?.message || 'Failed to create scheduling page');
		} finally {
			saving = false;
		}
	}
</script>

<svelte:head>
	<title>Create Scheduling Page | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto max-w-2xl p-6">
	<!-- Header -->
	<div class="mb-6">
		<Button variant="ghost" onclick={() => goto('/settings/scheduling')}>
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to Scheduling
		</Button>
	</div>

	<div class="mb-6">
		<h1 class="text-2xl font-bold">Create Scheduling Page</h1>
		<p class="text-muted-foreground">Set up a new page where others can book meetings with you</p>
	</div>

	<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }}>
		<Card.Root>
			<Card.Header>
				<Card.Title>Page Details</Card.Title>
				<Card.Description>Basic information about your scheduling page</Card.Description>
			</Card.Header>
			<Card.Content class="space-y-4">
				<div class="space-y-2">
					<Label for="name">Page Name *</Label>
					<Input
						id="name"
						placeholder="My Scheduling Page"
						value={name}
						oninput={handleNameChange}
					/>
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
						<p class="text-sm text-muted-foreground">
							Make this page publicly accessible
						</p>
					</div>
					<Switch bind:checked={isActive} />
				</div>
			</Card.Content>
			<Card.Footer class="flex justify-end gap-2">
				<Button variant="outline" onclick={() => goto('/settings/scheduling')}>Cancel</Button>
				<Button type="submit" disabled={saving}>
					{#if saving}
						<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					{/if}
					Create Page
				</Button>
			</Card.Footer>
		</Card.Root>
	</form>
</div>
