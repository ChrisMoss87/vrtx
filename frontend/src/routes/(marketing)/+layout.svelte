<script lang="ts">
	import '../../app.css';
	import { Button } from '$lib/components/ui/button';
	import { Menu, X, ArrowRight, Twitter, Linkedin, Github } from 'lucide-svelte';

	let { children } = $props();
	let mobileMenuOpen = $state(false);

	const navigation = [
		{ name: 'Features', href: '/#features' },
		{ name: 'Pricing', href: '/pricing' },
		{ name: 'About', href: '/about' },
		{ name: 'Contact', href: '/contact' }
	];

	const footerLinks = {
		product: [
			{ name: 'Features', href: '/#features' },
			{ name: 'Pricing', href: '/pricing' },
			{ name: 'Integrations', href: '/integrations' },
			{ name: 'Changelog', href: '/changelog' }
		],
		resources: [
			{ name: 'Documentation', href: '/docs' },
			{ name: 'Blog', href: '/blog' },
			{ name: 'Guides', href: '/guides' },
			{ name: 'API Reference', href: '/api' }
		],
		company: [
			{ name: 'About', href: '/about' },
			{ name: 'Careers', href: '/careers' },
			{ name: 'Contact', href: '/contact' },
			{ name: 'Press', href: '/press' }
		],
		legal: [
			{ name: 'Privacy', href: '/privacy' },
			{ name: 'Terms', href: '/terms' },
			{ name: 'Security', href: '/security' },
			{ name: 'GDPR', href: '/gdpr' }
		]
	};
</script>

<svelte:head>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
</svelte:head>

<div class="min-h-screen bg-slate-950">
	<!-- Header -->
	<header class="fixed top-0 left-0 right-0 z-50">
		<div class="absolute inset-0 bg-slate-950/80 backdrop-blur-xl border-b border-white/5"></div>

		<nav class="relative mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
			<!-- Logo -->
			<a href="/" class="flex items-center gap-2 group">
				<div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white font-bold text-slate-900 group-hover:scale-105 transition-transform">
					V
				</div>
				<span class="text-lg font-bold text-white tracking-tight">VRTX</span>
			</a>

			<!-- Desktop Navigation -->
			<div class="hidden items-center gap-1 md:flex">
				{#each navigation as item (item.name)}
					<a
						href={item.href}
						class="px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors rounded-lg hover:bg-white/5"
					>
						{item.name}
					</a>
				{/each}
			</div>

			<!-- CTA Buttons -->
			<div class="hidden items-center gap-3 md:flex">
				<a
					href="/login"
					class="px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors"
				>
					Sign in
				</a>
				<Button href="/register" class="h-10 px-5 bg-white text-slate-900 hover:bg-slate-100 rounded-lg text-sm font-medium">
					Start free trial
					<ArrowRight class="ml-1.5 h-3.5 w-3.5" />
				</Button>
			</div>

			<!-- Mobile Menu Toggle -->
			<button
				class="rounded-lg p-2 text-slate-400 hover:text-white hover:bg-white/5 md:hidden transition-colors"
				onclick={() => (mobileMenuOpen = !mobileMenuOpen)}
				aria-label="Toggle menu"
			>
				{#if mobileMenuOpen}
					<X class="h-6 w-6" />
				{:else}
					<Menu class="h-6 w-6" />
				{/if}
			</button>
		</nav>

		<!-- Mobile Menu -->
		{#if mobileMenuOpen}
			<div class="relative md:hidden bg-slate-950/95 backdrop-blur-xl border-b border-white/5">
				<div class="px-6 py-6">
					<div class="flex flex-col gap-1">
						{#each navigation as item (item.name)}
							<a
								href={item.href}
								class="px-4 py-3 text-base text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-colors"
								onclick={() => (mobileMenuOpen = false)}
							>
								{item.name}
							</a>
						{/each}
					</div>
					<div class="mt-6 pt-6 border-t border-white/5 flex flex-col gap-3">
						<a
							href="/login"
							class="w-full text-center px-4 py-3 text-sm text-slate-300 hover:text-white rounded-lg border border-white/10 hover:bg-white/5 transition-colors"
							onclick={() => (mobileMenuOpen = false)}
						>
							Sign in
						</a>
						<Button
							href="/register"
							class="w-full h-12 bg-white text-slate-900 hover:bg-slate-100 rounded-lg font-medium"
							onclick={() => (mobileMenuOpen = false)}
						>
							Start free trial
							<ArrowRight class="ml-2 h-4 w-4" />
						</Button>
					</div>
				</div>
			</div>
		{/if}
	</header>

	<!-- Main Content -->
	<main class="pt-[72px]">
		{@render children()}
	</main>

	<!-- Footer -->
	<footer class="bg-slate-900 border-t border-white/5">
		<div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
			<div class="grid grid-cols-2 gap-8 md:grid-cols-4 lg:grid-cols-5">
				<!-- Brand column -->
				<div class="col-span-2 md:col-span-4 lg:col-span-1 mb-8 lg:mb-0">
					<a href="/" class="inline-flex items-center gap-2 mb-4">
						<div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white font-bold text-slate-900 text-sm">
							V
						</div>
						<span class="font-bold text-white">VRTX</span>
					</a>
					<p class="text-sm text-slate-500 mb-6 max-w-xs">
						The modern CRM that helps teams close more deals with less effort.
					</p>
					<div class="flex gap-4">
						<a href="#" class="text-slate-500 hover:text-white transition-colors" aria-label="Twitter">
							<Twitter class="h-5 w-5" />
						</a>
						<a href="#" class="text-slate-500 hover:text-white transition-colors" aria-label="LinkedIn">
							<Linkedin class="h-5 w-5" />
						</a>
						<a href="#" class="text-slate-500 hover:text-white transition-colors" aria-label="GitHub">
							<Github class="h-5 w-5" />
						</a>
					</div>
				</div>

				<!-- Product -->
				<div>
					<h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Product</h3>
					<ul class="space-y-3">
						{#each footerLinks.product as link (link.name)}
							<li>
								<a href={link.href} class="text-sm text-slate-500 hover:text-white transition-colors">
									{link.name}
								</a>
							</li>
						{/each}
					</ul>
				</div>

				<!-- Resources -->
				<div>
					<h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Resources</h3>
					<ul class="space-y-3">
						{#each footerLinks.resources as link (link.name)}
							<li>
								<a href={link.href} class="text-sm text-slate-500 hover:text-white transition-colors">
									{link.name}
								</a>
							</li>
						{/each}
					</ul>
				</div>

				<!-- Company -->
				<div>
					<h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Company</h3>
					<ul class="space-y-3">
						{#each footerLinks.company as link (link.name)}
							<li>
								<a href={link.href} class="text-sm text-slate-500 hover:text-white transition-colors">
									{link.name}
								</a>
							</li>
						{/each}
					</ul>
				</div>

				<!-- Legal -->
				<div class="hidden lg:block">
					<h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Legal</h3>
					<ul class="space-y-3">
						{#each footerLinks.legal as link (link.name)}
							<li>
								<a href={link.href} class="text-sm text-slate-500 hover:text-white transition-colors">
									{link.name}
								</a>
							</li>
						{/each}
					</ul>
				</div>
			</div>

			<!-- Bottom -->
			<div class="mt-12 pt-8 border-t border-white/5 flex flex-col md:flex-row items-center justify-between gap-4">
				<p class="text-sm text-slate-600">
					© {new Date().getFullYear()} VRTX. All rights reserved.
				</p>
				<div class="flex items-center gap-6 lg:hidden">
					{#each footerLinks.legal as link (link.name)}
						<a href={link.href} class="text-xs text-slate-500 hover:text-white transition-colors">
							{link.name}
						</a>
					{/each}
				</div>
			</div>
		</div>
	</footer>
</div>

<style>
	:global(body) {
		font-family: 'Geist', system-ui, sans-serif;
	}

	.font-serif {
		font-family: 'Instrument Serif', Georgia, serif;
	}
</style>
