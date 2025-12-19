<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Target, Heart, Zap, Shield, Users, Globe, ArrowRight, Linkedin, Twitter } from 'lucide-svelte';

	const values = [
		{
			icon: Target,
			title: 'Customer First',
			description: 'Every feature starts with a real problem. We listen, learn, and deliver.',
			gradient: 'from-cyan-500 to-blue-600'
		},
		{
			icon: Zap,
			title: 'Radical Simplicity',
			description: "Powerful doesn't mean complicated. We ruthlessly simplify.",
			gradient: 'from-amber-500 to-orange-600'
		},
		{
			icon: Shield,
			title: 'Trust & Security',
			description: 'Your data is sacred. Security is a feature, not an afterthought.',
			gradient: 'from-emerald-500 to-teal-600'
		},
		{
			icon: Heart,
			title: 'Craftsmanship',
			description: 'We sweat the details. Quality is non-negotiable.',
			gradient: 'from-rose-500 to-pink-600'
		}
	];

	const stats = [
		{ value: '10K+', label: 'Active Users' },
		{ value: '50+', label: 'Countries' },
		{ value: '99.9%', label: 'Uptime' },
		{ value: '4.8', label: 'Rating' }
	];

	const team = [
		{
			name: 'Alex Thompson',
			role: 'CEO & Co-founder',
			bio: 'Former VP Sales at TechCorp. 15 years in enterprise sales.',
			initials: 'AT'
		},
		{
			name: 'Sarah Chen',
			role: 'CTO & Co-founder',
			bio: 'Ex-Google engineer. Built systems serving millions.',
			initials: 'SC'
		},
		{
			name: 'Marcus Williams',
			role: 'Head of Product',
			bio: 'Product leader from Salesforce and HubSpot.',
			initials: 'MW'
		},
		{
			name: 'Elena Rodriguez',
			role: 'Head of Design',
			bio: 'Design systems lead at Figma. Obsessed with details.',
			initials: 'ER'
		},
		{
			name: 'James Park',
			role: 'Head of Engineering',
			bio: 'Infrastructure architect from AWS. Scale enthusiast.',
			initials: 'JP'
		},
		{
			name: 'Priya Sharma',
			role: 'Head of Customer Success',
			bio: 'Customer experience strategist. Zendesk alumni.',
			initials: 'PS'
		}
	];

	const timeline = [
		{ year: '2021', title: 'Founded', desc: 'Started in a garage with a vision to fix broken CRMs.' },
		{ year: '2022', title: 'First 1,000 users', desc: 'Product-market fit achieved. Series A funding.' },
		{ year: '2023', title: 'Enterprise launch', desc: 'Multi-tenant architecture ships. Fortune 500 clients.' },
		{ year: '2024', title: 'Global expansion', desc: '50+ countries. 10,000+ users. Just getting started.' }
	];
</script>

<svelte:head>
	<title>About — VRTX CRM</title>
	<meta
		name="description"
		content="Learn about VRTX CRM's mission to make customer relationship management simple, powerful, and accessible."
	/>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
</svelte:head>

<!-- Hero -->
<section class="relative bg-slate-950 pt-24 pb-20 overflow-hidden">
	<!-- Background elements -->
	<div class="absolute top-0 right-0 w-[600px] h-[600px] bg-gradient-to-bl from-cyan-500/10 via-violet-500/5 to-transparent rounded-full blur-3xl"></div>
	<div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-gradient-to-tr from-amber-500/10 to-transparent rounded-full blur-3xl"></div>

	<div class="relative mx-auto max-w-7xl px-6 lg:px-8">
		<div class="max-w-3xl">
			<p class="text-sm uppercase tracking-[0.2em] text-cyan-400 mb-4">Our Story</p>
			<h1 class="font-serif text-4xl sm:text-5xl lg:text-6xl text-white leading-tight">
				Building the CRM we<br />
				<span class="text-slate-500">wished existed</span>
			</h1>
			<p class="mt-8 text-xl text-slate-400 leading-relaxed max-w-2xl">
				We started VRTX because every CRM we tried was either too complex to use or too simple to be useful. We believed there had to be a better way.
			</p>
		</div>
	</div>
</section>

<!-- Our Story -->
<section class="relative bg-slate-900 py-24">
	<div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/30 to-transparent"></div>

	<div class="mx-auto max-w-7xl px-6 lg:px-8">
		<div class="grid lg:grid-cols-2 gap-16 items-center">
			<div>
				<h2 class="font-serif text-3xl sm:text-4xl text-white mb-8">The problem we solve</h2>
				<div class="space-y-6 text-lg text-slate-400 leading-relaxed">
					<p>
						As sales professionals and consultants, we used every CRM on the market. Some were powerful but required months of training. Others were easy but couldn't handle real business complexity.
					</p>
					<p>
						We asked: why can't a CRM be both powerful <em class="text-white not-italic">and</em> intuitive? Why choose between flexibility and usability?
					</p>
					<p>
						So we built VRTX—a modern CRM that combines enterprise power with consumer simplicity. Visual pipelines anyone can understand. Automation that doesn't require a developer.
					</p>
				</div>
			</div>

			<!-- Timeline -->
			<div class="relative">
				<div class="absolute left-4 top-0 bottom-0 w-px bg-gradient-to-b from-cyan-500/50 via-violet-500/50 to-transparent"></div>
				<div class="space-y-10">
					{#each timeline as event (event.year)}
						<div class="relative pl-12">
							<div class="absolute left-0 w-8 h-8 rounded-full bg-slate-800 border border-white/10 flex items-center justify-center">
								<div class="w-2 h-2 rounded-full bg-cyan-400"></div>
							</div>
							<div class="text-sm text-cyan-400 font-medium mb-1">{event.year}</div>
							<h3 class="text-lg font-semibold text-white mb-1">{event.title}</h3>
							<p class="text-slate-400">{event.desc}</p>
						</div>
					{/each}
				</div>
			</div>
		</div>
	</div>

	<div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
</section>

<!-- Stats -->
<section class="relative bg-slate-950 py-20">
	<div class="mx-auto max-w-7xl px-6 lg:px-8">
		<div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
			{#each stats as stat (stat.label)}
				<div class="text-center">
					<div class="font-serif text-5xl sm:text-6xl text-white mb-2">{stat.value}</div>
					<div class="text-sm uppercase tracking-widest text-slate-500">{stat.label}</div>
				</div>
			{/each}
		</div>
	</div>
</section>

<!-- Values -->
<section class="relative bg-slate-900 py-24">
	<div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/30 to-transparent"></div>

	<div class="mx-auto max-w-7xl px-6 lg:px-8">
		<div class="text-center max-w-2xl mx-auto mb-16">
			<p class="text-sm uppercase tracking-[0.2em] text-violet-400 mb-4">Our Values</p>
			<h2 class="font-serif text-3xl sm:text-4xl text-white">
				The principles that guide us
			</h2>
		</div>

		<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
			{#each values as value (value.title)}
				<div class="rounded-2xl border border-white/5 bg-slate-950/50 p-8 hover:border-white/10 transition-all duration-300">
					<div class="mb-6 inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br {value.gradient} shadow-lg">
						<value.icon class="w-6 h-6 text-white" />
					</div>
					<h3 class="text-lg font-semibold text-white mb-3">{value.title}</h3>
					<p class="text-slate-400 leading-relaxed">{value.description}</p>
				</div>
			{/each}
		</div>
	</div>

	<div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
</section>

<!-- Team -->
<section class="relative bg-slate-950 py-24">
	<div class="mx-auto max-w-7xl px-6 lg:px-8">
		<div class="text-center max-w-2xl mx-auto mb-16">
			<p class="text-sm uppercase tracking-[0.2em] text-amber-400 mb-4">Leadership</p>
			<h2 class="font-serif text-3xl sm:text-4xl text-white">
				Meet the team
			</h2>
			<p class="mt-4 text-slate-400">
				Builders, dreamers, and obsessive problem-solvers.
			</p>
		</div>

		<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
			{#each team as member (member.name)}
				<div class="group rounded-2xl border border-white/5 bg-slate-900/50 p-8 hover:border-white/10 transition-all duration-300">
					<div class="flex items-start gap-4 mb-4">
						<div class="w-16 h-16 rounded-xl bg-gradient-to-br from-slate-700 to-slate-800 flex items-center justify-center text-lg font-semibold text-white shrink-0">
							{member.initials}
						</div>
						<div>
							<h3 class="text-lg font-semibold text-white">{member.name}</h3>
							<p class="text-sm text-cyan-400">{member.role}</p>
						</div>
					</div>
					<p class="text-slate-400 text-sm leading-relaxed">{member.bio}</p>
					<div class="mt-4 flex gap-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
						<a href="#" class="text-slate-500 hover:text-white transition-colors">
							<Linkedin class="w-4 h-4" />
						</a>
						<a href="#" class="text-slate-500 hover:text-white transition-colors">
							<Twitter class="w-4 h-4" />
						</a>
					</div>
				</div>
			{/each}
		</div>
	</div>
</section>

<!-- CTA -->
<section class="relative bg-slate-900 py-24">
	<div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/30 to-transparent"></div>

	<div class="mx-auto max-w-4xl px-6 lg:px-8 text-center">
		<h2 class="font-serif text-3xl sm:text-4xl text-white mb-4">
			Join us on our mission
		</h2>
		<p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
			Whether you're looking to transform your sales process or join our team, we'd love to hear from you.
		</p>
		<div class="flex flex-col sm:flex-row items-center justify-center gap-4">
			<Button href="/register" class="h-12 px-8 bg-white text-slate-900 hover:bg-slate-100 rounded-xl font-medium">
				Start free trial
				<ArrowRight class="ml-2 h-4 w-4" />
			</Button>
			<Button href="/careers" variant="ghost" class="h-12 px-8 text-slate-300 hover:text-white hover:bg-white/5 rounded-xl">
				View open positions
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
