<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Tabs, TabsContent, TabsList, TabsTrigger } from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import {
		chatWidgetsApi,
		type ChatWidget,
		type ChatWidgetSettings,
		type ChatWidgetStyling
	} from '$lib/api/live-chat';
	import { Loader2, Copy, Check, MessageSquare } from 'lucide-svelte';

	interface Props {
		widget?: ChatWidget;
		onSave?: (widget: ChatWidget) => void;
		onCancel?: () => void;
	}

	let { widget, onSave, onCancel }: Props = $props();

	let saving = $state(false);
	let copied = $state(false);
	let activeTab = $state('general');

	// Form state
	let name = $state(widget?.name || '');
	let isActive = $state(widget?.is_active ?? true);

	// Settings
	let position = $state(widget?.settings?.position || 'bottom-right');
	let greetingMessage = $state(widget?.settings?.greeting_message || 'Hi! How can we help you today?');
	let offlineMessage = $state(widget?.settings?.offline_message || "We're currently offline. Leave a message and we'll get back to you.");
	let requireEmail = $state(widget?.settings?.require_email ?? true);
	let requireName = $state(widget?.settings?.require_name ?? true);
	let showAvatar = $state(widget?.settings?.show_avatar ?? true);
	let soundEnabled = $state(widget?.settings?.sound_enabled ?? true);

	// Styling
	let primaryColor = $state(widget?.styling?.primary_color || '#3B82F6');
	let textColor = $state(widget?.styling?.text_color || '#FFFFFF');
	let backgroundColor = $state(widget?.styling?.background_color || '#FFFFFF');
	let headerText = $state(widget?.styling?.header_text || 'Chat with us');
	let borderRadius = $state(widget?.styling?.border_radius || 12);

	// Domains
	let allowedDomains = $state(widget?.allowed_domains?.join('\n') || '');

	const positionOptions = [
		{ value: 'bottom-right', label: 'Bottom Right' },
		{ value: 'bottom-left', label: 'Bottom Left' }
	];

	async function handleSave() {
		if (!name.trim()) return;

		saving = true;
		try {
			const settings: ChatWidgetSettings = {
				position: position as 'bottom-right' | 'bottom-left',
				greeting_message: greetingMessage,
				offline_message: offlineMessage,
				require_email: requireEmail,
				require_name: requireName,
				show_avatar: showAvatar,
				sound_enabled: soundEnabled,
				auto_open_delay: 0
			};

			const styling: ChatWidgetStyling = {
				primary_color: primaryColor,
				text_color: textColor,
				background_color: backgroundColor,
				header_text: headerText,
				border_radius: borderRadius,
				launcher_icon: 'chat'
			};

			const domains = allowedDomains
				.split('\n')
				.map((d) => d.trim())
				.filter(Boolean);

			const data = {
				name: name.trim(),
				is_active: isActive,
				settings,
				styling,
				allowed_domains: domains.length > 0 ? domains : undefined
			};

			const result = widget
				? await chatWidgetsApi.update(widget.id, data)
				: await chatWidgetsApi.create(data);

			onSave?.(result);
		} catch (err) {
			console.error('Failed to save widget:', err);
		}
		saving = false;
	}

	async function copyEmbedCode() {
		if (!widget) return;
		try {
			const { embed_code } = await chatWidgetsApi.getEmbedCode(widget.id);
			await navigator.clipboard.writeText(embed_code);
			copied = true;
			setTimeout(() => (copied = false), 2000);
		} catch (err) {
			console.error('Failed to copy embed code:', err);
		}
	}
</script>

<div class="grid lg:grid-cols-2 gap-6">
	<!-- Configuration -->
	<div class="space-y-6">
		<Tabs bind:value={activeTab}>
			<TabsList class="w-full">
				<TabsTrigger value="general" class="flex-1">General</TabsTrigger>
				<TabsTrigger value="messages" class="flex-1">Messages</TabsTrigger>
				<TabsTrigger value="appearance" class="flex-1">Appearance</TabsTrigger>
				<TabsTrigger value="domains" class="flex-1">Domains</TabsTrigger>
			</TabsList>

			<TabsContent value="general" class="space-y-4 mt-4">
				<div class="space-y-2">
					<Label for="name">Widget Name *</Label>
					<Input id="name" bind:value={name} placeholder="Support Chat" />
				</div>

				<div class="flex items-center justify-between">
					<div>
						<Label>Active</Label>
						<p class="text-sm text-muted-foreground">Enable or disable this widget</p>
					</div>
					<Switch bind:checked={isActive} />
				</div>

				<div class="space-y-2">
					<Label>Position</Label>
					<Select.Root type="single" bind:value={position}>
						<Select.Trigger>
							{positionOptions.find(o => o.value === position)?.label || 'Bottom Right'}
						</Select.Trigger>
						<Select.Content>
							{#each positionOptions as opt}
								<Select.Item value={opt.value} label={opt.label}>{opt.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="flex items-center justify-between">
					<div>
						<Label>Require Email</Label>
						<p class="text-sm text-muted-foreground">Ask visitors for email before starting</p>
					</div>
					<Switch bind:checked={requireEmail} />
				</div>

				<div class="flex items-center justify-between">
					<div>
						<Label>Require Name</Label>
						<p class="text-sm text-muted-foreground">Ask visitors for their name</p>
					</div>
					<Switch bind:checked={requireName} />
				</div>

				<div class="flex items-center justify-between">
					<div>
						<Label>Sound Notifications</Label>
						<p class="text-sm text-muted-foreground">Play sound for new messages</p>
					</div>
					<Switch bind:checked={soundEnabled} />
				</div>
			</TabsContent>

			<TabsContent value="messages" class="space-y-4 mt-4">
				<div class="space-y-2">
					<Label for="greeting">Greeting Message</Label>
					<Textarea
						id="greeting"
						bind:value={greetingMessage}
						placeholder="Hi! How can we help you today?"
						rows={3}
					/>
				</div>

				<div class="space-y-2">
					<Label for="offline">Offline Message</Label>
					<Textarea
						id="offline"
						bind:value={offlineMessage}
						placeholder="We're currently offline..."
						rows={3}
					/>
				</div>

				<div class="space-y-2">
					<Label for="header">Header Text</Label>
					<Input id="header" bind:value={headerText} placeholder="Chat with us" />
				</div>
			</TabsContent>

			<TabsContent value="appearance" class="space-y-4 mt-4">
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="primary-color">Primary Color</Label>
						<div class="flex gap-2">
							<Input
								id="primary-color"
								type="color"
								bind:value={primaryColor}
								class="w-12 h-10 p-1 cursor-pointer"
							/>
							<Input bind:value={primaryColor} class="flex-1" />
						</div>
					</div>

					<div class="space-y-2">
						<Label for="text-color">Text Color</Label>
						<div class="flex gap-2">
							<Input
								id="text-color"
								type="color"
								bind:value={textColor}
								class="w-12 h-10 p-1 cursor-pointer"
							/>
							<Input bind:value={textColor} class="flex-1" />
						</div>
					</div>
				</div>

				<div class="space-y-2">
					<Label for="bg-color">Background Color</Label>
					<div class="flex gap-2">
						<Input
							id="bg-color"
							type="color"
							bind:value={backgroundColor}
							class="w-12 h-10 p-1 cursor-pointer"
						/>
						<Input bind:value={backgroundColor} class="flex-1" />
					</div>
				</div>

				<div class="space-y-2">
					<Label for="border-radius">Border Radius: {borderRadius}px</Label>
					<input
						id="border-radius"
						type="range"
						min="0"
						max="24"
						bind:value={borderRadius}
						class="w-full"
					/>
				</div>
			</TabsContent>

			<TabsContent value="domains" class="space-y-4 mt-4">
				<div class="space-y-2">
					<Label for="domains">Allowed Domains</Label>
					<p class="text-sm text-muted-foreground">
						Enter one domain per line. Leave empty to allow all domains.
					</p>
					<Textarea
						id="domains"
						bind:value={allowedDomains}
						placeholder="example.com&#10;*.mysite.com"
						rows={5}
					/>
				</div>
			</TabsContent>
		</Tabs>

		<div class="flex justify-end gap-2">
			{#if onCancel}
				<Button variant="outline" onclick={onCancel}>Cancel</Button>
			{/if}
			<Button onclick={handleSave} disabled={saving || !name.trim()}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-2 animate-spin" />
				{/if}
				{widget ? 'Update Widget' : 'Create Widget'}
			</Button>
		</div>
	</div>

	<!-- Preview -->
	<div class="space-y-4">
		<Card>
			<CardHeader>
				<CardTitle>Preview</CardTitle>
				<CardDescription>See how your widget will look</CardDescription>
			</CardHeader>
			<CardContent>
				<div class="relative h-[400px] bg-muted rounded-lg overflow-hidden">
					<!-- Mock website background -->
					<div class="absolute inset-0 p-4">
						<div class="h-4 w-32 bg-muted-foreground/20 rounded mb-2"></div>
						<div class="h-3 w-48 bg-muted-foreground/10 rounded mb-4"></div>
						<div class="h-24 w-full bg-muted-foreground/10 rounded"></div>
					</div>

					<!-- Widget preview -->
					<div
						class="absolute {position === 'bottom-left' ? 'left-4' : 'right-4'} bottom-4"
					>
						<!-- Chat window (simplified) -->
						<div
							class="w-[280px] h-[320px] shadow-lg mb-3"
							style="background-color: {backgroundColor}; border-radius: {borderRadius}px;"
						>
							<div
								class="p-3"
								style="background-color: {primaryColor}; color: {textColor}; border-radius: {borderRadius}px {borderRadius}px 0 0;"
							>
								<div class="font-medium">{headerText}</div>
							</div>
							<div class="p-3 text-sm" style="color: #374151;">
								{greetingMessage}
							</div>
						</div>

						<!-- Launcher button -->
						<div
							class="w-14 h-14 rounded-full shadow-lg flex items-center justify-center cursor-pointer {position === 'bottom-left' ? '' : 'ml-auto'}"
							style="background-color: {primaryColor};"
						>
							<MessageSquare class="h-6 w-6" style="color: {textColor};" />
						</div>
					</div>
				</div>
			</CardContent>
		</Card>

		{#if widget}
			<Card>
				<CardHeader>
					<CardTitle>Embed Code</CardTitle>
					<CardDescription>Add this code to your website</CardDescription>
				</CardHeader>
				<CardContent>
					<div class="relative">
						<pre class="bg-muted p-3 rounded text-xs overflow-x-auto">{widget.embed_code || 'Save widget to get embed code'}</pre>
						{#if widget.embed_code}
							<Button
								variant="outline"
								size="sm"
								class="absolute top-2 right-2"
								onclick={copyEmbedCode}
							>
								{#if copied}
									<Check class="h-4 w-4" />
								{:else}
									<Copy class="h-4 w-4" />
								{/if}
							</Button>
						{/if}
					</div>
				</CardContent>
			</Card>
		{/if}
	</div>
</div>
