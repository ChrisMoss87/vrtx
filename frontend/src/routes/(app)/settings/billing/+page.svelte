<script lang="ts">
	import { onMount } from 'svelte';
	import { license, isTrialing, trialDaysRemaining, isPastDue, isCancelled } from '$lib/stores/license';
	import { PlanBadge, UsageIndicator } from '$lib/components/billing';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Separator } from '$lib/components/ui/separator';
	import { toast } from 'svelte-sonner';
	import CreditCard from 'lucide-svelte/icons/credit-card';
	import Package from 'lucide-svelte/icons/package';
	import BarChart2 from 'lucide-svelte/icons/bar-chart-2';
	import Settings from 'lucide-svelte/icons/settings';
	import ArrowUpCircle from 'lucide-svelte/icons/arrow-up-circle';
	import Calendar from 'lucide-svelte/icons/calendar';
	import Users from 'lucide-svelte/icons/users';

	// Plan details
	const planDetails: Record<string, { name: string; price: number; features: string[] }> = {
		free: {
			name: 'Free',
			price: 0,
			features: ['Basic CRM', '2 users', '1,000 records', 'Email support']
		},
		starter: {
			name: 'Starter',
			price: 15,
			features: ['Everything in Free', '5 users', '10,000 records', 'Workflows', 'Blueprints']
		},
		professional: {
			name: 'Professional',
			price: 45,
			features: ['Everything in Starter', '15 users', '100,000 records', 'Forecasting', 'Reports']
		},
		business: {
			name: 'Business',
			price: 85,
			features: ['Everything in Professional', 'Unlimited users', '500,000 records', 'Deal Rooms', 'API Access']
		},
		enterprise: {
			name: 'Enterprise',
			price: 150,
			features: ['Everything in Business', 'Unlimited records', 'Custom plugins', 'Dedicated support', 'SLA']
		}
	};

	$: currentPlanDetails = planDetails[$license.plan] || planDetails.free;
	$: nextBillingDate = $license.current_period_end
		? new Date($license.current_period_end).toLocaleDateString()
		: null;
</script>

<svelte:head>
	<title>Billing & Subscription | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6 max-w-5xl">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold tracking-tight">Billing & Subscription</h1>
			<p class="text-muted-foreground">Manage your plan, usage, and payment methods</p>
		</div>
		<PlanBadge />
	</div>

	{#if $license.loading}
		<Card.Root>
			<Card.Content class="py-12 text-center">
				<div class="animate-pulse">Loading subscription details...</div>
			</Card.Content>
		</Card.Root>
	{:else}
		<!-- Alert Banners -->
		{#if $isPastDue}
			<div class="rounded-lg border border-destructive bg-destructive/10 p-4">
				<div class="flex items-center gap-3">
					<CreditCard class="h-5 w-5 text-destructive" />
					<div>
						<p class="font-medium text-destructive">Payment Required</p>
						<p class="text-sm text-muted-foreground">
							Your payment method has failed. Please update your billing information to continue using premium features.
						</p>
					</div>
					<Button variant="destructive" class="ml-auto">
						Update Payment
					</Button>
				</div>
			</div>
		{/if}

		{#if $isTrialing && $trialDaysRemaining !== null}
			<div class="rounded-lg border border-amber-500 bg-amber-500/10 p-4">
				<div class="flex items-center gap-3">
					<Calendar class="h-5 w-5 text-amber-600" />
					<div>
						<p class="font-medium text-amber-700">Trial Period</p>
						<p class="text-sm text-muted-foreground">
							You have {$trialDaysRemaining} days remaining in your trial.
							Upgrade now to keep your features.
						</p>
					</div>
					<Button variant="default" class="ml-auto">
						<ArrowUpCircle class="mr-2 h-4 w-4" />
						Upgrade Now
					</Button>
				</div>
			</div>
		{/if}

		<Tabs.Root value="overview">
			<Tabs.List>
				<Tabs.Trigger value="overview">Overview</Tabs.Trigger>
				<Tabs.Trigger value="usage">Usage</Tabs.Trigger>
				<Tabs.Trigger value="payment">Payment</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="overview" class="space-y-6 pt-4">
				<!-- Current Plan -->
				<Card.Root>
					<Card.Header>
						<Card.Title class="flex items-center gap-2">
							<Package class="h-5 w-5" />
							Current Plan
						</Card.Title>
					</Card.Header>
					<Card.Content>
						<div class="flex items-start justify-between">
							<div>
								<h3 class="text-2xl font-bold">{currentPlanDetails.name}</h3>
								<p class="text-3xl font-bold mt-2">
									${currentPlanDetails.price}
									<span class="text-base font-normal text-muted-foreground">
										/user/month
									</span>
								</p>
								{#if nextBillingDate && $license.status === 'active'}
									<p class="text-sm text-muted-foreground mt-2">
										Next billing date: {nextBillingDate}
									</p>
								{/if}
							</div>
							<div class="text-right">
								<div class="flex items-center gap-2 text-muted-foreground">
									<Users class="h-4 w-4" />
									<span>{$license.user_count} user(s)</span>
								</div>
								<p class="text-sm text-muted-foreground mt-1">
									{$license.billing_cycle === 'yearly' ? 'Billed annually' : 'Billed monthly'}
								</p>
							</div>
						</div>

						<Separator class="my-4" />

						<div>
							<h4 class="font-medium mb-2">Plan Features</h4>
							<ul class="grid grid-cols-2 gap-2">
								{#each currentPlanDetails.features as feature}
									<li class="text-sm text-muted-foreground flex items-center gap-2">
										<span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
										{feature}
									</li>
								{/each}
							</ul>
						</div>

						<div class="flex gap-2 mt-6">
							{#if $license.plan !== 'enterprise'}
								<Button href="/settings/billing/upgrade">
									<ArrowUpCircle class="mr-2 h-4 w-4" />
									Upgrade Plan
								</Button>
							{/if}
							<Button variant="outline" href="/settings/billing/plugins">
								<Package class="mr-2 h-4 w-4" />
								Manage Plugins
							</Button>
						</div>
					</Card.Content>
				</Card.Root>

				<!-- Quick Usage Stats -->
				<Card.Root>
					<Card.Header>
						<Card.Title class="flex items-center gap-2">
							<BarChart2 class="h-5 w-5" />
							Usage Overview
						</Card.Title>
					</Card.Header>
					<Card.Content>
						<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
							<UsageIndicator metric="records" />
							<UsageIndicator metric="storage_mb" />
							<UsageIndicator metric="api_calls" />
							<UsageIndicator metric="workflows" />
							<UsageIndicator metric="emails_sent" />
							<UsageIndicator metric="blueprints" />
						</div>
					</Card.Content>
				</Card.Root>

				<!-- Active Plugins Summary -->
				{#if $license.plugins.length > 0}
					<Card.Root>
						<Card.Header>
							<Card.Title>Active Plugins</Card.Title>
							<Card.Description>
								You have {$license.plugins.length} plugin(s) enabled
							</Card.Description>
						</Card.Header>
						<Card.Content>
							<div class="flex flex-wrap gap-2">
								{#each $license.plugins as plugin}
									<span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
										{plugin}
									</span>
								{/each}
							</div>
							<Button variant="link" href="/settings/billing/plugins" class="mt-4 p-0 h-auto">
								Manage plugins →
							</Button>
						</Card.Content>
					</Card.Root>
				{/if}
			</Tabs.Content>

			<Tabs.Content value="usage" class="space-y-6 pt-4">
				<Card.Root>
					<Card.Header>
						<Card.Title>Detailed Usage</Card.Title>
						<Card.Description>
							View your current resource consumption
						</Card.Description>
					</Card.Header>
					<Card.Content class="space-y-6">
						<div class="space-y-4">
							<UsageIndicator metric="records" size="lg" />
							<UsageIndicator metric="storage_mb" size="lg" />
							<UsageIndicator metric="api_calls" size="lg" />
							<UsageIndicator metric="workflows" size="lg" />
							<UsageIndicator metric="emails_sent" size="lg" />
							<UsageIndicator metric="sms_sent" size="lg" />
							<UsageIndicator metric="blueprints" size="lg" />
						</div>
					</Card.Content>
				</Card.Root>
			</Tabs.Content>

			<Tabs.Content value="payment" class="space-y-6 pt-4">
				<Card.Root>
					<Card.Header>
						<Card.Title class="flex items-center gap-2">
							<CreditCard class="h-5 w-5" />
							Payment Method
						</Card.Title>
					</Card.Header>
					<Card.Content>
						<div class="flex items-center justify-between p-4 border rounded-lg">
							<div class="flex items-center gap-3">
								<div class="h-10 w-14 bg-gradient-to-r from-blue-600 to-blue-400 rounded flex items-center justify-center text-white text-xs font-bold">
									VISA
								</div>
								<div>
									<p class="font-medium">•••• •••• •••• 4242</p>
									<p class="text-sm text-muted-foreground">Expires 12/25</p>
								</div>
							</div>
							<Button variant="outline" size="sm">
								Update
							</Button>
						</div>
						<p class="text-sm text-muted-foreground mt-4">
							Your card will be charged on the next billing date.
						</p>
					</Card.Content>
				</Card.Root>

				<Card.Root>
					<Card.Header>
						<Card.Title>Billing History</Card.Title>
					</Card.Header>
					<Card.Content>
						<div class="space-y-3">
							<div class="flex items-center justify-between py-2 border-b">
								<div>
									<p class="font-medium">December 2024</p>
									<p class="text-sm text-muted-foreground">Professional Plan</p>
								</div>
								<div class="text-right">
									<p class="font-medium">$45.00</p>
									<Button variant="link" size="sm" class="h-auto p-0 text-xs">
										Download
									</Button>
								</div>
							</div>
							<div class="flex items-center justify-between py-2 border-b">
								<div>
									<p class="font-medium">November 2024</p>
									<p class="text-sm text-muted-foreground">Professional Plan</p>
								</div>
								<div class="text-right">
									<p class="font-medium">$45.00</p>
									<Button variant="link" size="sm" class="h-auto p-0 text-xs">
										Download
									</Button>
								</div>
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				{#if !$isCancelled}
					<Card.Root class="border-destructive/50">
						<Card.Header>
							<Card.Title class="text-destructive">Danger Zone</Card.Title>
						</Card.Header>
						<Card.Content>
							<p class="text-sm text-muted-foreground mb-4">
								Cancelling your subscription will downgrade you to the Free plan at the end of your billing period.
								You will lose access to premium features.
							</p>
							<Button variant="destructive" size="sm">
								Cancel Subscription
							</Button>
						</Card.Content>
					</Card.Root>
				{/if}
			</Tabs.Content>
		</Tabs.Root>
	{/if}
</div>
