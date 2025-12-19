<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import {
		ArrowRight,
		CheckCircle2,
		BarChart3,
		Users,
		Workflow,
		Zap,
		Shield,
		Globe,
		Play,
		Star,
		ArrowUpRight,
		Sparkles
	} from 'lucide-svelte';

	const features = [
		{
			icon: Users,
			title: 'Contact Intelligence',
			description: 'Deep relationship mapping with AI-powered insights. Know your customers before they know themselves.',
			gradient: 'from-cyan-500 to-blue-600'
		},
		{
			icon: Workflow,
			title: 'Visual Pipelines',
			description: 'Drag. Drop. Close. Intuitive kanban boards that make pipeline management feel effortless.',
			gradient: 'from-violet-500 to-purple-600'
		},
		{
			icon: BarChart3,
			title: 'Real-time Analytics',
			description: 'Live dashboards that pulse with your business rhythm. Every metric, every moment.',
			gradient: 'from-amber-500 to-orange-600'
		},
		{
			icon: Zap,
			title: 'Workflow Automation',
			description: 'Set it. Forget it. Watch your tedious tasks execute themselves flawlessly.',
			gradient: 'from-emerald-500 to-teal-600'
		},
		{
			icon: Shield,
			title: 'Enterprise Security',
			description: 'Bank-grade encryption. SOC 2 compliant. Your data sleeps soundly.',
			gradient: 'from-rose-500 to-pink-600'
		},
		{
			icon: Globe,
			title: 'Multi-Tenant Architecture',
			description: 'One login. Infinite workspaces. Perfect for agencies managing multiple clients.',
			gradient: 'from-sky-500 to-indigo-600'
		}
	];

	const metrics = [
		{ value: '40%', label: 'Faster Closes', sublabel: 'Average deal velocity improvement' },
		{ value: '3.2x', label: 'ROI', sublabel: 'First year return on investment' },
		{ value: '99.99%', label: 'Uptime', sublabel: 'Enterprise-grade reliability' },
		{ value: '<2min', label: 'Response', sublabel: 'Average support response time' }
	];

	const testimonials = [
		{
			quote: "VRTX didn't just improve our sales process—it fundamentally changed how we think about customer relationships.",
			author: 'Sarah Chen',
			role: 'VP of Revenue',
			company: 'Meridian Tech',
			avatar: 'SC'
		},
		{
			quote: "We evaluated 12 CRMs. VRTX was the only one our team actually wanted to use. Adoption was instant.",
			author: 'Marcus Webb',
			role: 'Founder & CEO',
			company: 'Velocity Labs',
			avatar: 'MW'
		},
		{
			quote: "The multi-tenant feature alone saved us $50k/year in tool consolidation. Everything else is gravy.",
			author: 'Elena Rodriguez',
			role: 'Operations Director',
			company: 'Scale Partners',
			avatar: 'ER'
		}
	];

	const logos = ['Stripe', 'Notion', 'Linear', 'Vercel', 'Raycast', 'Arc'];
</script>

<svelte:head>
	<title>VRTX — The CRM That Actually Works</title>
	<meta
		name="description"
		content="VRTX is a modern CRM platform built for teams who refuse to compromise. Visual pipelines, intelligent automation, and multi-tenant architecture."
	/>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
</svelte:head>

<!-- Hero Section -->
<section class="relative min-h-[100vh] overflow-hidden bg-slate-950">
	<!-- Gradient orbs -->
	<div class="absolute top-0 left-1/4 w-[800px] h-[800px] bg-gradient-to-br from-cyan-500/20 via-blue-500/10 to-transparent rounded-full blur-3xl animate-pulse-slow"></div>
	<div class="absolute bottom-0 right-1/4 w-[600px] h-[600px] bg-gradient-to-tl from-violet-500/15 via-purple-500/10 to-transparent rounded-full blur-3xl animate-pulse-slow animation-delay-2000"></div>

	<!-- Grid pattern overlay -->
	<div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px]"></div>

	<!-- Noise texture -->
	<div class="absolute inset-0 opacity-[0.015]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 256 256%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noise%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.9%22 numOctaves=%224%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noise)%22/%3E%3C/svg%3E');"></div>

	<div class="relative mx-auto max-w-7xl px-6 pt-32 pb-24 lg:px-8 lg:pt-40 lg:pb-32">
		<!-- Eyebrow -->
		<div class="flex justify-center mb-8">
			<div class="group inline-flex items-center gap-2 rounded-full bg-white/5 border border-white/10 px-4 py-2 text-sm text-slate-300 backdrop-blur-sm hover:bg-white/10 transition-all duration-300 cursor-pointer">
				<Sparkles class="h-4 w-4 text-amber-400" />
				<span>Introducing AI-powered deal scoring</span>
				<ArrowRight class="h-3.5 w-3.5 text-slate-500 group-hover:translate-x-0.5 transition-transform" />
			</div>
		</div>

		<!-- Main headline -->
		<div class="mx-auto max-w-4xl text-center">
			<h1 class="font-serif text-5xl sm:text-6xl lg:text-7xl xl:text-8xl tracking-tight text-white leading-[0.95]">
				<span class="block">The CRM that</span>
				<span class="block mt-2">
					<span class="relative">
						<span class="bg-gradient-to-r from-cyan-300 via-blue-400 to-violet-400 bg-clip-text text-transparent">actually works</span>
						<svg class="absolute -bottom-2 left-0 w-full h-3 text-cyan-500/30" viewBox="0 0 200 8" preserveAspectRatio="none">
							<path d="M0,5 Q50,0 100,5 T200,5" stroke="currentColor" stroke-width="2" fill="none" />
						</svg>
					</span>
				</span>
			</h1>

			<p class="mt-8 text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed font-light">
				Stop fighting your tools. VRTX combines visual pipelines, intelligent automation,
				and multi-tenant architecture into one seamless experience.
			</p>

			<!-- CTA buttons -->
			<div class="mt-12 flex flex-col sm:flex-row items-center justify-center gap-4">
				<Button
					href="/register"
					size="lg"
					class="group relative h-14 px-8 text-base font-medium bg-white text-slate-900 hover:bg-slate-100 rounded-full transition-all duration-300 hover:shadow-[0_0_40px_rgba(255,255,255,0.15)]"
				>
					Start free trial
					<ArrowRight class="ml-2 h-4 w-4 group-hover:translate-x-1 transition-transform" />
				</Button>
				<Button
					href="#demo"
					variant="ghost"
					size="lg"
					class="group h-14 px-8 text-base font-medium text-slate-300 hover:text-white hover:bg-white/5 rounded-full"
				>
					<Play class="mr-2 h-4 w-4" />
					Watch demo
					<span class="ml-2 text-xs text-slate-500">2:34</span>
				</Button>
			</div>

			<p class="mt-6 text-sm text-slate-500">
				No credit card required · 14-day free trial · Cancel anytime
			</p>
		</div>

		<!-- Hero visual -->
		<div class="mt-20 relative">
			<!-- Glow effect behind the card -->
			<div class="absolute inset-0 bg-gradient-to-t from-cyan-500/20 via-transparent to-transparent blur-3xl scale-110"></div>

			<!-- Main dashboard preview card -->
			<div class="relative mx-auto max-w-5xl">
				<div class="rounded-2xl border border-white/10 bg-slate-900/80 backdrop-blur-xl p-2 shadow-2xl shadow-black/50">
					<div class="rounded-xl bg-slate-900 overflow-hidden">
						<!-- Browser chrome -->
						<div class="flex items-center gap-2 px-4 py-3 border-b border-white/5">
							<div class="flex gap-1.5">
								<div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
								<div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
								<div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
							</div>
							<div class="flex-1 flex justify-center">
								<div class="px-4 py-1 rounded-md bg-slate-800/50 text-xs text-slate-500">
									app.vrtx.io/pipelines
								</div>
							</div>
						</div>

						<!-- Dashboard content placeholder -->
						<div class="aspect-[16/9] bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800 p-8 flex items-center justify-center">
							<div class="text-center">
								<div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 mb-6">
									<BarChart3 class="w-10 h-10 text-white" />
								</div>
								<p class="text-slate-400 text-lg">Interactive Dashboard Preview</p>
								<p class="text-slate-600 text-sm mt-2">Coming soon</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Floating stat cards -->
				<div class="absolute -left-8 top-1/4 hidden lg:block animate-float">
					<div class="rounded-xl border border-white/10 bg-slate-900/90 backdrop-blur-xl p-4 shadow-xl">
						<div class="flex items-center gap-3">
							<div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
								<svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
								</svg>
							</div>
							<div>
								<p class="text-sm font-semibold text-white">+23%</p>
								<p class="text-xs text-slate-500">This month</p>
							</div>
						</div>
					</div>
				</div>

				<div class="absolute -right-8 top-1/3 hidden lg:block animate-float animation-delay-1000">
					<div class="rounded-xl border border-white/10 bg-slate-900/90 backdrop-blur-xl p-4 shadow-xl">
						<div class="flex items-center gap-3">
							<div class="w-10 h-10 rounded-lg bg-violet-500/20 flex items-center justify-center">
								<Users class="w-5 h-5 text-violet-400" />
							</div>
							<div>
								<p class="text-sm font-semibold text-white">2,847</p>
								<p class="text-xs text-slate-500">Active deals</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Scroll indicator -->
	<div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-slate-500">
		<span class="text-xs uppercase tracking-widest">Scroll</span>
		<div class="w-px h-8 bg-gradient-to-b from-slate-500 to-transparent"></div>
	</div>
</section>

<!-- Social proof logos -->
<section class="relative bg-slate-950 border-y border-white/5">
	<div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
		<p class="text-center text-xs uppercase tracking-[0.2em] text-slate-600 mb-8">
			Trusted by forward-thinking teams
		</p>
		<div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6">
			{#each logos as logo, i (logo)}
				<div
					class="text-slate-600 hover:text-slate-400 transition-colors duration-300 text-lg font-semibold tracking-tight"
					style="animation-delay: {i * 100}ms"
				>
					{logo}
				</div>
			{/each}
		</div>
	</div>
</section>

<!-- Features Section -->
<section class="relative bg-slate-950 py-32 overflow-hidden">
	<!-- Background elements -->
	<div class="absolute top-1/2 left-0 w-[500px] h-[500px] bg-cyan-500/5 rounded-full blur-3xl -translate-y-1/2"></div>

	<div class="relative mx-auto max-w-7xl px-6 lg:px-8">
		<!-- Section header -->
		<div class="max-w-2xl mb-20">
			<p class="text-sm uppercase tracking-[0.2em] text-cyan-400 mb-4">Capabilities</p>
			<h2 class="font-serif text-4xl sm:text-5xl text-white leading-tight">
				Everything you need.<br />
				<span class="text-slate-500">Nothing you don't.</span>
			</h2>
		</div>

		<!-- Features grid -->
		<div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
			{#each features as feature, i (feature.title)}
				<div
					class="group relative rounded-2xl border border-white/5 bg-slate-900/50 p-8 hover:border-white/10 hover:bg-slate-900/80 transition-all duration-500"
					style="animation-delay: {i * 100}ms"
				>
					<!-- Icon -->
					<div class="mb-6 inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br {feature.gradient} shadow-lg">
						<feature.icon class="w-6 h-6 text-white" />
					</div>

					<!-- Content -->
					<h3 class="text-xl font-semibold text-white mb-3">{feature.title}</h3>
					<p class="text-slate-400 leading-relaxed">{feature.description}</p>

					<!-- Hover arrow -->
					<div class="absolute top-8 right-8 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
						<ArrowUpRight class="w-5 h-5 text-slate-500" />
					</div>
				</div>
			{/each}
		</div>
	</div>
</section>

<!-- Metrics Section -->
<section class="relative bg-slate-900 py-24 overflow-hidden">
	<!-- Gradient border top -->
	<div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/50 to-transparent"></div>

	<div class="relative mx-auto max-w-7xl px-6 lg:px-8">
		<div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
			{#each metrics as metric, i (metric.label)}
				<div class="text-center lg:text-left" style="animation-delay: {i * 100}ms">
					<div class="font-serif text-5xl sm:text-6xl text-white mb-2">{metric.value}</div>
					<div class="text-lg font-medium text-slate-300 mb-1">{metric.label}</div>
					<div class="text-sm text-slate-500">{metric.sublabel}</div>
				</div>
			{/each}
		</div>
	</div>

	<!-- Gradient border bottom -->
	<div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-violet-500/50 to-transparent"></div>
</section>

<!-- How it works -->
<section class="relative bg-slate-950 py-32">
	<div class="mx-auto max-w-7xl px-6 lg:px-8">
		<!-- Section header -->
		<div class="text-center max-w-2xl mx-auto mb-20">
			<p class="text-sm uppercase tracking-[0.2em] text-violet-400 mb-4">How it works</p>
			<h2 class="font-serif text-4xl sm:text-5xl text-white">
				Three steps to transform your sales
			</h2>
		</div>

		<!-- Steps -->
		<div class="grid gap-12 lg:grid-cols-3">
			{#each [
				{ num: '01', title: 'Import & Organize', desc: 'Bring your contacts from anywhere. Our intelligent parser maps fields automatically.' },
				{ num: '02', title: 'Customize & Automate', desc: 'Build pipelines that match your process. Set up automations that work while you sleep.' },
				{ num: '03', title: 'Close & Scale', desc: 'Focus on relationships, not data entry. Watch your close rate climb.' }
			] as step, i (step.num)}
				<div class="relative">
					<!-- Step number -->
					<div class="font-serif text-8xl text-slate-800 mb-4">{step.num}</div>

					<!-- Content -->
					<h3 class="text-2xl font-semibold text-white mb-4">{step.title}</h3>
					<p class="text-slate-400 leading-relaxed">{step.desc}</p>

					<!-- Connector line -->
					{#if i < 2}
						<div class="hidden lg:block absolute top-12 left-full w-full h-px bg-gradient-to-r from-slate-700 to-transparent"></div>
					{/if}
				</div>
			{/each}
		</div>
	</div>
</section>

<!-- Testimonials -->
<section class="relative bg-slate-900 py-32 overflow-hidden">
	<!-- Background pattern -->
	<div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:24px_24px]"></div>

	<div class="relative mx-auto max-w-7xl px-6 lg:px-8">
		<!-- Section header -->
		<div class="text-center max-w-2xl mx-auto mb-16">
			<p class="text-sm uppercase tracking-[0.2em] text-amber-400 mb-4">Testimonials</p>
			<h2 class="font-serif text-4xl sm:text-5xl text-white">
				Don't take our word for it
			</h2>
		</div>

		<!-- Testimonials grid -->
		<div class="grid gap-8 lg:grid-cols-3">
			{#each testimonials as testimonial (testimonial.author)}
				<div class="relative rounded-2xl border border-white/5 bg-slate-950/50 p-8 backdrop-blur-sm">
					<!-- Stars -->
					<div class="flex gap-1 mb-6">
						{#each [1, 2, 3, 4, 5] as star (star)}
							<Star class="w-4 h-4 fill-amber-400 text-amber-400" />
						{/each}
					</div>

					<!-- Quote -->
					<blockquote class="text-lg text-slate-300 leading-relaxed mb-8">
						"{testimonial.quote}"
					</blockquote>

					<!-- Author -->
					<div class="flex items-center gap-4">
						<div class="w-12 h-12 rounded-full bg-gradient-to-br from-slate-700 to-slate-800 flex items-center justify-center text-sm font-semibold text-white">
							{testimonial.avatar}
						</div>
						<div>
							<div class="font-medium text-white">{testimonial.author}</div>
							<div class="text-sm text-slate-500">{testimonial.role}, {testimonial.company}</div>
						</div>
					</div>
				</div>
			{/each}
		</div>
	</div>
</section>

<!-- Final CTA -->
<section class="relative bg-slate-950 py-32 overflow-hidden">
	<!-- Background gradient -->
	<div class="absolute inset-0 bg-gradient-to-b from-slate-900 via-slate-950 to-slate-950"></div>
	<div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[600px] bg-gradient-to-b from-cyan-500/10 via-violet-500/5 to-transparent rounded-full blur-3xl"></div>

	<div class="relative mx-auto max-w-4xl px-6 lg:px-8 text-center">
		<h2 class="font-serif text-4xl sm:text-5xl lg:text-6xl text-white mb-6">
			Ready to close more deals?
		</h2>
		<p class="text-xl text-slate-400 mb-12 max-w-2xl mx-auto">
			Join thousands of teams who've transformed their sales process. Start your free trial today.
		</p>

		<div class="flex flex-col sm:flex-row items-center justify-center gap-4">
			<Button
				href="/register"
				size="lg"
				class="group h-14 px-8 text-base font-medium bg-white text-slate-900 hover:bg-slate-100 rounded-full"
			>
				Start free trial
				<ArrowRight class="ml-2 h-4 w-4 group-hover:translate-x-1 transition-transform" />
			</Button>
			<Button
				href="/contact"
				variant="ghost"
				size="lg"
				class="h-14 px-8 text-base font-medium text-slate-300 hover:text-white hover:bg-white/5 rounded-full"
			>
				Talk to sales
			</Button>
		</div>

		<p class="mt-8 text-sm text-slate-600">
			No credit card required · 14-day free trial · SOC 2 compliant
		</p>
	</div>
</section>

<!-- FAQ Section -->
<section class="relative bg-slate-950 py-24 border-t border-white/5">
	<div class="mx-auto max-w-3xl px-6 lg:px-8">
		<div class="text-center mb-16">
			<h2 class="font-serif text-3xl sm:text-4xl text-white">
				Frequently asked questions
			</h2>
		</div>

		<div class="space-y-8">
			{#each [
				{ q: 'How long is the free trial?', a: 'Your free trial lasts 14 days with full access to all features. No credit card required to start.' },
				{ q: 'Can I import my existing data?', a: 'Yes! VRTX supports CSV imports and has built-in migration tools for Salesforce, HubSpot, Pipedrive, and more.' },
				{ q: 'Is my data secure?', a: 'Absolutely. We use AES-256 encryption, are SOC 2 Type II compliant, and undergo regular security audits.' },
				{ q: 'What kind of support do you offer?', a: 'All plans include email support. Professional and Enterprise plans include priority support with dedicated account managers.' },
				{ q: 'Can I cancel anytime?', a: 'Yes, you can cancel your subscription at any time. No long-term contracts or cancellation fees.' }
			] as faq (faq.q)}
				<div class="border-b border-white/5 pb-8">
					<h3 class="text-lg font-medium text-white mb-3">{faq.q}</h3>
					<p class="text-slate-400 leading-relaxed">{faq.a}</p>
				</div>
			{/each}
		</div>

		<div class="mt-12 text-center">
			<p class="text-slate-500">
				Can't find what you're looking for?
				<a href="/contact" class="text-cyan-400 hover:text-cyan-300 transition-colors">Contact our team</a>
			</p>
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

	@keyframes float {
		0%, 100% { transform: translateY(0); }
		50% { transform: translateY(-10px); }
	}

	.animate-float {
		animation: float 4s ease-in-out infinite;
	}

	@keyframes pulse-slow {
		0%, 100% { opacity: 0.3; }
		50% { opacity: 0.5; }
	}

	.animate-pulse-slow {
		animation: pulse-slow 8s ease-in-out infinite;
	}

	.animation-delay-1000 {
		animation-delay: 1s;
	}

	.animation-delay-2000 {
		animation-delay: 2s;
	}
</style>
