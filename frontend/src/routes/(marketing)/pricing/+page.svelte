<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Check, X, Sparkles, ArrowRight, HelpCircle } from 'lucide-svelte';

	let isAnnual = $state(true);

	const plans = [
		{
			name: 'Starter',
			description: 'For small teams getting started',
			monthlyPrice: 19,
			annualPrice: 15,
			popular: false,
			cta: 'Start free trial',
			href: '/register',
			features: [
				{ name: 'Up to 3 users', included: true },
				{ name: '1,000 contacts', included: true },
				{ name: '2 custom modules', included: true },
				{ name: '1 pipeline per module', included: true },
				{ name: 'Basic reports', included: true },
				{ name: 'Email support', included: true },
				{ name: 'Workflow automation', included: false },
				{ name: 'API access', included: false },
				{ name: 'Custom roles', included: false },
				{ name: 'Multi-tenant workspaces', included: false }
			]
		},
		{
			name: 'Professional',
			description: 'For growing teams that need power',
			monthlyPrice: 49,
			annualPrice: 39,
			popular: true,
			cta: 'Start free trial',
			href: '/register',
			features: [
				{ name: 'Up to 10 users', included: true },
				{ name: '10,000 contacts', included: true },
				{ name: 'Unlimited modules', included: true },
				{ name: 'Unlimited pipelines', included: true },
				{ name: 'Advanced reports', included: true },
				{ name: 'Priority support', included: true },
				{ name: 'Workflow automation', included: true, highlight: true },
				{ name: 'API access', included: true, highlight: true },
				{ name: 'Custom roles', included: true, highlight: true },
				{ name: 'Multi-tenant workspaces', included: false }
			]
		},
		{
			name: 'Enterprise',
			description: 'For organizations at scale',
			monthlyPrice: 99,
			annualPrice: 79,
			popular: false,
			cta: 'Contact sales',
			href: '/contact',
			features: [
				{ name: 'Unlimited users', included: true },
				{ name: 'Unlimited contacts', included: true },
				{ name: 'Unlimited modules', included: true },
				{ name: 'Unlimited pipelines', included: true },
				{ name: 'Custom analytics', included: true },
				{ name: 'Dedicated support + SLA', included: true },
				{ name: 'Advanced automation', included: true },
				{ name: 'Full API + webhooks', included: true },
				{ name: 'Custom roles + SSO', included: true },
				{ name: 'Multi-tenant workspaces', included: true, highlight: true }
			]
		}
	];

	const enterpriseFeatures = [
		'SSO / SAML integration',
		'Custom data retention',
		'Dedicated infrastructure',
		'Custom integrations',
		'On-premises option',
		'24/7 phone support'
	];

	const faqs = [
		{
			q: 'What happens after my free trial ends?',
			a: "After 14 days, you'll choose a plan. No subscription means read-only mode—your data stays safe."
		},
		{
			q: 'Can I change plans at any time?',
			a: 'Yes. Upgrade instantly, downgrade at cycle end. Prorated billing on upgrades.'
		},
		{
			q: 'What payment methods do you accept?',
			a: 'All major credit cards. Annual Enterprise plans can be invoiced.'
		},
		{
			q: 'Do you offer nonprofit/education discounts?',
			a: 'Yes—50% off for registered nonprofits and educational institutions.'
		},
		{
			q: 'What counts as a "user"?',
			a: 'Anyone with login credentials. Read-only and deactivated users are free.'
		},
		{
			q: 'Can I get a refund?',
			a: '30-day money-back guarantee on all plans. No questions asked.'
		}
	];

	const comparisonRows = [
		{ feature: 'Users', starter: 'Up to 3', pro: 'Up to 10', enterprise: 'Unlimited' },
		{ feature: 'Contacts', starter: '1,000', pro: '10,000', enterprise: 'Unlimited' },
		{ feature: 'Custom Modules', starter: '2', pro: 'Unlimited', enterprise: 'Unlimited' },
		{ feature: 'Pipelines', starter: '1 per module', pro: 'Unlimited', enterprise: 'Unlimited' },
		{ feature: 'Workflow Automation', starter: false, pro: true, enterprise: true },
		{ feature: 'API Access', starter: false, pro: true, enterprise: true },
		{ feature: 'Custom Roles', starter: false, pro: true, enterprise: true },
		{ feature: 'Multi-Tenant', starter: false, pro: false, enterprise: true },
		{ feature: 'Support', starter: 'Email', pro: 'Priority', enterprise: 'Dedicated + SLA' }
	];
</script>

<svelte:head>
	<title>Pricing — VRTX CRM</title>
	<meta
		name="description"
		content="Simple, transparent pricing for VRTX CRM. Start free, scale as you grow. No hidden fees."
	/>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
</svelte:head>

<!-- Hero -->
<section class="relative bg-slate-950 pt-24 pb-16 overflow-hidden">
	<!-- Background elements -->
	<div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-cyan-500/10 via-violet-500/5 to-transparent rounded-full blur-3xl"></div>

	<div class="relative mx-auto max-w-7xl px-6 lg:px-8">
		<div class="mx-auto max-w-2xl text-center">
			<p class="text-sm uppercase tracking-[0.2em] text-cyan-400 mb-4">Pricing</p>
			<h1 class="font-serif text-4xl sm:text-5xl lg:text-6xl text-white">
				Simple, transparent pricing
			</h1>
			<p class="mt-6 text-lg text-slate-400 max-w-xl mx-auto">
				Start with a 14-day free trial. No credit card required. Choose the plan that scales with you.
			</p>

			<!-- Billing Toggle -->
			<div class="mt-10 inline-flex items-center gap-4 rounded-full bg-slate-900/50 border border-white/5 p-1.5">
				<button
					onclick={() => (isAnnual = false)}
					class="px-5 py-2 rounded-full text-sm font-medium transition-all duration-200 {!isAnnual
						? 'bg-white text-slate-900'
						: 'text-slate-400 hover:text-white'}"
				>
					Monthly
				</button>
				<button
					onclick={() => (isAnnual = true)}
					class="px-5 py-2 rounded-full text-sm font-medium transition-all duration-200 flex items-center gap-2 {isAnnual
						? 'bg-white text-slate-900'
						: 'text-slate-400 hover:text-white'}"
				>
					Annual
					<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 {isAnnual ? 'bg-emerald-500 text-white' : ''}">
						-20%
					</span>
				</button>
			</div>
		</div>
	</div>
</section>

<!-- Pricing Cards -->
<section class="relative bg-slate-950 pb-24">
	<div class="mx-auto max-w-7xl px-6 lg:px-8">
		<div class="grid gap-8 lg:grid-cols-3">
			{#each plans as plan (plan.name)}
				<div
					class="relative rounded-2xl border {plan.popular
						? 'border-cyan-500/50 bg-gradient-to-b from-cyan-500/10 via-slate-900/50 to-slate-900/50'
						: 'border-white/5 bg-slate-900/50'} p-8 flex flex-col"
				>
					{#if plan.popular}
						<div class="absolute -top-4 left-1/2 -translate-x-1/2">
							<div class="px-4 py-1.5 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 text-sm font-medium text-white shadow-lg shadow-cyan-500/25">
								Most Popular
							</div>
						</div>
					{/if}

					<!-- Plan Header -->
					<div class="mb-8">
						<h3 class="text-xl font-semibold text-white">{plan.name}</h3>
						<p class="mt-2 text-sm text-slate-400">{plan.description}</p>
					</div>

					<!-- Price -->
					<div class="mb-8">
						<div class="flex items-baseline gap-1">
							<span class="font-serif text-5xl text-white">
								${isAnnual ? plan.annualPrice : plan.monthlyPrice}
							</span>
							<span class="text-slate-500">/user/mo</span>
						</div>
						{#if isAnnual}
							<p class="mt-2 text-sm text-slate-500">Billed annually</p>
						{/if}
					</div>

					<!-- CTA -->
					<Button
						href={plan.href}
						class="w-full h-12 mb-8 {plan.popular
							? 'bg-white text-slate-900 hover:bg-slate-100'
							: 'bg-slate-800 text-white hover:bg-slate-700'} rounded-xl font-medium"
					>
						{plan.cta}
						{#if plan.popular}
							<ArrowRight class="ml-2 h-4 w-4" />
						{/if}
					</Button>

					<!-- Features -->
					<ul class="space-y-4 flex-1">
						{#each plan.features as feature (feature.name)}
							<li class="flex items-start gap-3">
								{#if feature.included}
									<div class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full {feature.highlight ? 'bg-cyan-500/20' : 'bg-emerald-500/20'} flex items-center justify-center">
										<Check class="w-3 h-3 {feature.highlight ? 'text-cyan-400' : 'text-emerald-400'}" />
									</div>
									<span class="{feature.highlight ? 'text-white font-medium' : 'text-slate-300'}">{feature.name}</span>
								{:else}
									<div class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full bg-slate-800 flex items-center justify-center">
										<X class="w-3 h-3 text-slate-600" />
									</div>
									<span class="text-slate-500">{feature.name}</span>
								{/if}
							</li>
						{/each}
					</ul>
				</div>
			{/each}
		</div>
	</div>
</section>

<!-- Enterprise Add-ons -->
<section class="relative bg-slate-900 py-20">
	<div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/30 to-transparent"></div>

	<div class="mx-auto max-w-4xl px-6 lg:px-8">
		<div class="text-center mb-12">
			<div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 mb-6">
				<Sparkles class="w-6 h-6 text-white" />
			</div>
			<h2 class="font-serif text-3xl text-white">Enterprise add-ons</h2>
			<p class="mt-4 text-slate-400">
				Need more? Enterprise plans can be customized with additional features.
			</p>
		</div>

		<div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
			{#each enterpriseFeatures as feature (feature)}
				<div class="flex items-center gap-3 rounded-xl bg-slate-800/50 border border-white/5 p-4">
					<Check class="w-5 h-5 text-cyan-400 flex-shrink-0" />
					<span class="text-sm text-slate-300">{feature}</span>
				</div>
			{/each}
		</div>

		<div class="mt-10 text-center">
			<Button href="/contact" variant="outline" class="border-white/10 text-white hover:bg-white/5 rounded-xl">
				Talk to sales
			</Button>
		</div>
	</div>

	<div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
</section>

<!-- Comparison Table (Desktop) -->
<section class="relative bg-slate-950 py-24 hidden lg:block">
	<div class="mx-auto max-w-5xl px-6 lg:px-8">
		<div class="text-center mb-16">
			<h2 class="font-serif text-3xl text-white">Compare plans</h2>
		</div>

		<div class="rounded-2xl border border-white/5 overflow-hidden">
			<table class="w-full">
				<thead>
					<tr class="border-b border-white/5">
						<th class="py-5 px-6 text-left text-sm font-medium text-slate-400">Features</th>
						<th class="py-5 px-6 text-center text-sm font-medium text-white">Starter</th>
						<th class="py-5 px-6 text-center text-sm font-medium text-white bg-cyan-500/5">Professional</th>
						<th class="py-5 px-6 text-center text-sm font-medium text-white">Enterprise</th>
					</tr>
				</thead>
				<tbody>
					{#each comparisonRows as row (row.feature)}
						<tr class="border-b border-white/5 last:border-0">
							<td class="py-4 px-6 text-sm text-slate-300">{row.feature}</td>
							<td class="py-4 px-6 text-center">
								{#if typeof row.starter === 'boolean'}
									{#if row.starter}
										<Check class="w-5 h-5 text-emerald-400 mx-auto" />
									{:else}
										<X class="w-5 h-5 text-slate-600 mx-auto" />
									{/if}
								{:else}
									<span class="text-sm text-slate-400">{row.starter}</span>
								{/if}
							</td>
							<td class="py-4 px-6 text-center bg-cyan-500/5">
								{#if typeof row.pro === 'boolean'}
									{#if row.pro}
										<Check class="w-5 h-5 text-emerald-400 mx-auto" />
									{:else}
										<X class="w-5 h-5 text-slate-600 mx-auto" />
									{/if}
								{:else}
									<span class="text-sm text-slate-300">{row.pro}</span>
								{/if}
							</td>
							<td class="py-4 px-6 text-center">
								{#if typeof row.enterprise === 'boolean'}
									{#if row.enterprise}
										<Check class="w-5 h-5 text-emerald-400 mx-auto" />
									{:else}
										<X class="w-5 h-5 text-slate-600 mx-auto" />
									{/if}
								{:else}
									<span class="text-sm text-slate-300">{row.enterprise}</span>
								{/if}
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	</div>
</section>

<!-- FAQs -->
<section class="relative bg-slate-950 py-24 border-t border-white/5">
	<div class="mx-auto max-w-3xl px-6 lg:px-8">
		<div class="text-center mb-16">
			<h2 class="font-serif text-3xl text-white">Frequently asked questions</h2>
		</div>

		<div class="space-y-8">
			{#each faqs as faq (faq.q)}
				<div class="border-b border-white/5 pb-8">
					<h3 class="text-lg font-medium text-white mb-3 flex items-start gap-3">
						<HelpCircle class="w-5 h-5 text-cyan-400 mt-0.5 flex-shrink-0" />
						{faq.q}
					</h3>
					<p class="text-slate-400 leading-relaxed pl-8">{faq.a}</p>
				</div>
			{/each}
		</div>
	</div>
</section>

<!-- Final CTA -->
<section class="relative bg-slate-900 py-20">
	<div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/30 to-transparent"></div>

	<div class="mx-auto max-w-4xl px-6 lg:px-8 text-center">
		<h2 class="font-serif text-3xl sm:text-4xl text-white mb-4">
			Still have questions?
		</h2>
		<p class="text-lg text-slate-400 mb-10">
			Our team is here to help. Schedule a demo or chat with sales.
		</p>
		<div class="flex flex-col sm:flex-row items-center justify-center gap-4">
			<Button href="/contact" class="h-12 px-8 bg-white text-slate-900 hover:bg-slate-100 rounded-xl font-medium">
				Contact sales
			</Button>
			<Button href="/demo" variant="ghost" class="h-12 px-8 text-slate-300 hover:text-white hover:bg-white/5 rounded-xl">
				Schedule demo
			</Button>
		</div>
	</div>
</section>

<style>
	:global(body) {
		font-family: 'Geist', system-ui, sans-serif;
	}

	.font-serif {
		font-family: 'Instrument Serif', Georgia, serif;
	}
</style>
