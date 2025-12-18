<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import {
		Settings,
		User,
		Users,
		Shield,
		CreditCard,
		Plug,
		ClipboardList,
		ChevronRight,
		Building
	} from 'lucide-svelte';

	interface SettingsSection {
		title: string;
		description: string;
		url: string;
		icon: any;
	}

	const settingsSections: SettingsSection[] = [
		{
			title: 'Preferences',
			description: 'Customize your personal experience, theme, and display settings',
			url: '/settings/preferences',
			icon: User
		},
		{
			title: 'Users',
			description: 'Manage users, invitations, and access control',
			url: '/settings/users',
			icon: Users
		},
		{
			title: 'Roles & Permissions',
			description: 'Configure roles and define what users can access',
			url: '/settings/roles',
			icon: Shield
		},
		{
			title: 'Billing & Plugins',
			description: 'Manage subscription, billing details, and installed plugins',
			url: '/settings/billing',
			icon: CreditCard
		},
		{
			title: 'Integrations',
			description: 'Connect third-party services, API keys, and webhooks',
			url: '/settings/integrations',
			icon: Plug
		},
		{
			title: 'Audit Logs',
			description: 'View system activity and change history',
			url: '/settings/audit-logs',
			icon: ClipboardList
		}
	];
</script>

<svelte:head>
	<title>Settings | VRTX</title>
</svelte:head>

<div class="max-w-4xl space-y-6">
	<div>
		<h1 class="text-2xl font-bold">Settings</h1>
		<p class="text-muted-foreground">Manage your account and application settings</p>
	</div>

	<!-- Company/Tenant Info Card -->
	<Card>
		<CardHeader>
			<CardTitle class="flex items-center gap-2">
				<Building class="h-5 w-5" />
				Organization
			</CardTitle>
			<CardDescription>
				Your organization's information and settings
			</CardDescription>
		</CardHeader>
		<CardContent>
			<div class="flex items-center justify-between">
				<div class="space-y-1">
					<p class="text-sm font-medium">Organization Name</p>
					<p class="text-muted-foreground">Configure your company name, logo, and branding</p>
				</div>
				<Button variant="outline" href="/admin/organization">
					Manage
				</Button>
			</div>
		</CardContent>
	</Card>

	<!-- Settings Navigation Grid -->
	<div class="grid gap-4 md:grid-cols-2">
		{#each settingsSections as section}
			{@const Icon = section.icon}
			<a href={section.url} class="group">
				<Card class="h-full transition-colors hover:bg-muted/50">
					<CardContent class="flex items-start gap-4 p-6">
						<div class="rounded-lg bg-primary/10 p-3">
							<Icon class="h-6 w-6 text-primary" />
						</div>
						<div class="flex-1 space-y-1">
							<h3 class="font-semibold group-hover:text-primary transition-colors">
								{section.title}
							</h3>
							<p class="text-sm text-muted-foreground">{section.description}</p>
						</div>
						<ChevronRight class="h-5 w-5 text-muted-foreground group-hover:text-primary transition-colors mt-1" />
					</CardContent>
				</Card>
			</a>
		{/each}
	</div>
</div>
