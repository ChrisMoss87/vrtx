<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { ArrowLeft, Settings, Globe, Save } from 'lucide-svelte';
	import {
		landingPageApi,
		type LandingPage,
		type PageElement,
		type PageStyles,
		type PageSettings,
		type SeoSettings
	} from '$lib/api/landing-pages';
	import { PageBuilder } from '$lib/components/landing-pages';
	import { toast } from 'svelte-sonner';

	const pageId = $derived(parseInt($page.params.id || '0'));

	let landingPage = $state<LandingPage | null>(null);
	let loading = $state(true);
	let saving = $state(false);

	let content = $state<PageElement[]>([]);
	let styles = $state<PageStyles>({});
	let settings = $state<PageSettings>({});
	let seoSettings = $state<SeoSettings>({});

	let showSettingsDialog = $state(false);
	let showPublishDialog = $state(false);

	// Settings form
	let pageName = $state('');
	let pageSlug = $state('');
	let pageDescription = $state('');
	let thankYouType = $state<'message' | 'redirect' | 'page'>('message');
	let thankYouMessage = $state('');
	let thankYouRedirectUrl = $state('');

	// SEO settings
	let seoTitle = $state('');
	let seoDescription = $state('');
	let seoKeywords = $state('');

	onMount(async () => {
		try {
			landingPage = await landingPageApi.get(pageId);
			content = landingPage.content || [];
			styles = landingPage.styles || {};
			settings = landingPage.settings || {};
			seoSettings = landingPage.seo_settings || {};

			// Populate form fields
			pageName = landingPage.name;
			pageSlug = landingPage.slug;
			pageDescription = landingPage.description || '';
			thankYouType = landingPage.thank_you_page_type;
			thankYouMessage = landingPage.thank_you_message || '';
			thankYouRedirectUrl = landingPage.thank_you_redirect_url || '';
			seoTitle = seoSettings.title || '';
			seoDescription = seoSettings.description || '';
			seoKeywords = seoSettings.keywords?.join(', ') || '';
		} catch (error) {
			toast.error('Failed to load landing page');
			goto('/landing-pages');
		} finally {
			loading = false;
		}
	});

	function handleContentChange(data: {
		content: PageElement[];
		styles: PageStyles;
		settings: PageSettings;
		seoSettings: SeoSettings;
	}) {
		content = data.content;
		styles = data.styles;
		settings = data.settings;
		seoSettings = data.seoSettings;
	}

	async function handleSave() {
		saving = true;
		try {
			await landingPageApi.update(pageId, {
				content,
				styles,
				settings,
				seo_settings: seoSettings
			});
			toast.success('Page saved');
		} catch (error) {
			toast.error('Failed to save page');
		} finally {
			saving = false;
		}
	}

	async function handleSaveSettings() {
		saving = true;
		try {
			const updatedSeoSettings = {
				...seoSettings,
				title: seoTitle || undefined,
				description: seoDescription || undefined,
				keywords: seoKeywords ? seoKeywords.split(',').map((k) => k.trim()) : undefined
			};

			await landingPageApi.update(pageId, {
				name: pageName,
				slug: pageSlug,
				description: pageDescription || undefined,
				thank_you_page_type: thankYouType,
				thank_you_message: thankYouMessage || undefined,
				thank_you_redirect_url: thankYouRedirectUrl || undefined,
				seo_settings: updatedSeoSettings
			});

			seoSettings = updatedSeoSettings;
			toast.success('Settings saved');
			showSettingsDialog = false;
		} catch (error) {
			toast.error('Failed to save settings');
		} finally {
			saving = false;
		}
	}

	async function handlePublish() {
		try {
			await landingPageApi.publish(pageId);
			toast.success('Page published!');
			showPublishDialog = false;
			landingPage = await landingPageApi.get(pageId);
		} catch (error) {
			toast.error('Failed to publish page');
		}
	}
</script>

{#if loading}
	<div class="flex h-screen items-center justify-center">
		<div class="text-muted-foreground">Loading...</div>
	</div>
{:else if landingPage}
	<div class="flex h-screen flex-col">
		<!-- Header -->
		<div class="flex items-center justify-between border-b px-4 py-2">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="sm" href={`/landing-pages/${pageId}`}>
					<ArrowLeft class="mr-1 h-4 w-4" />
					Back
				</Button>
				<div>
					<h1 class="font-semibold">{landingPage.name}</h1>
					<span class="text-muted-foreground text-sm">/{landingPage.slug}</span>
				</div>
			</div>
			<div class="flex items-center gap-2">
				<Button variant="outline" size="sm" onclick={() => (showSettingsDialog = true)}>
					<Settings class="mr-1 h-4 w-4" />
					Settings
				</Button>
				{#if landingPage.status !== 'published'}
					<Button size="sm" onclick={() => (showPublishDialog = true)}>
						<Globe class="mr-1 h-4 w-4" />
						Publish
					</Button>
				{:else}
					<Button
						variant="outline"
						size="sm"
						onclick={async () => {
							await landingPageApi.unpublish(pageId);
							landingPage = await landingPageApi.get(pageId);
							toast.success('Page unpublished');
						}}
					>
						Unpublish
					</Button>
				{/if}
			</div>
		</div>

		<!-- Builder -->
		<div class="flex-1 overflow-hidden">
			<PageBuilder
				{content}
				{styles}
				{settings}
				{seoSettings}
				onChange={handleContentChange}
				onSave={handleSave}
				{saving}
			/>
		</div>
	</div>

	<!-- Settings Dialog -->
	<Dialog.Root bind:open={showSettingsDialog}>
		<Dialog.Content class="max-w-2xl">
			<Dialog.Header>
				<Dialog.Title>Page Settings</Dialog.Title>
			</Dialog.Header>

			<Tabs.Root value="general">
				<Tabs.List class="mb-4">
					<Tabs.Trigger value="general">General</Tabs.Trigger>
					<Tabs.Trigger value="seo">SEO</Tabs.Trigger>
					<Tabs.Trigger value="thankyou">Thank You</Tabs.Trigger>
				</Tabs.List>

				<Tabs.Content value="general" class="space-y-4">
					<div>
						<Label for="name">Page Name</Label>
						<Input id="name" bind:value={pageName} />
					</div>
					<div>
						<Label for="slug">URL Slug</Label>
						<div class="flex items-center gap-2">
							<span class="text-muted-foreground text-sm">/p/</span>
							<Input id="slug" bind:value={pageSlug} />
						</div>
					</div>
					<div>
						<Label for="description">Description</Label>
						<Textarea id="description" bind:value={pageDescription} rows={3} />
					</div>
				</Tabs.Content>

				<Tabs.Content value="seo" class="space-y-4">
					<div>
						<Label for="seo-title">SEO Title</Label>
						<Input
							id="seo-title"
							bind:value={seoTitle}
							placeholder={pageName}
						/>
					</div>
					<div>
						<Label for="seo-description">Meta Description</Label>
						<Textarea
							id="seo-description"
							bind:value={seoDescription}
							rows={3}
							placeholder="Brief description for search engines..."
						/>
					</div>
					<div>
						<Label for="seo-keywords">Keywords</Label>
						<Input
							id="seo-keywords"
							bind:value={seoKeywords}
							placeholder="keyword1, keyword2, keyword3"
						/>
						<p class="text-muted-foreground mt-1 text-xs">Comma-separated list</p>
					</div>
				</Tabs.Content>

				<Tabs.Content value="thankyou" class="space-y-4">
					<div>
						<Label>After Form Submission</Label>
						<select
							class="mt-1 w-full rounded-md border px-3 py-2"
							bind:value={thankYouType}
						>
							<option value="message">Show message</option>
							<option value="redirect">Redirect to URL</option>
							<option value="page">Show another page</option>
						</select>
					</div>

					{#if thankYouType === 'message'}
						<div>
							<Label for="thank-you-message">Thank You Message</Label>
							<Textarea
								id="thank-you-message"
								bind:value={thankYouMessage}
								rows={4}
								placeholder="Thank you for your submission!"
							/>
						</div>
					{:else if thankYouType === 'redirect'}
						<div>
							<Label for="redirect-url">Redirect URL</Label>
							<Input
								id="redirect-url"
								type="url"
								bind:value={thankYouRedirectUrl}
								placeholder="https://example.com/thank-you"
							/>
						</div>
					{/if}
				</Tabs.Content>
			</Tabs.Root>

			<Dialog.Footer>
				<Button variant="outline" onclick={() => (showSettingsDialog = false)}>Cancel</Button>
				<Button onclick={handleSaveSettings} disabled={saving}>
					{saving ? 'Saving...' : 'Save Settings'}
				</Button>
			</Dialog.Footer>
		</Dialog.Content>
	</Dialog.Root>

	<!-- Publish Dialog -->
	<Dialog.Root bind:open={showPublishDialog}>
		<Dialog.Content>
			<Dialog.Header>
				<Dialog.Title>Publish Landing Page</Dialog.Title>
				<Dialog.Description>
					Make this page available to the public. You can unpublish it at any time.
				</Dialog.Description>
			</Dialog.Header>

			<div class="py-4">
				<p class="text-sm">
					Your page will be available at:
					<code class="bg-muted ml-1 rounded px-2 py-1">
						{window.location.origin}/p/{landingPage.slug}
					</code>
				</p>
			</div>

			<Dialog.Footer>
				<Button variant="outline" onclick={() => (showPublishDialog = false)}>Cancel</Button>
				<Button onclick={handlePublish}>
					<Globe class="mr-1 h-4 w-4" />
					Publish Now
				</Button>
			</Dialog.Footer>
		</Dialog.Content>
	</Dialog.Root>
{/if}
