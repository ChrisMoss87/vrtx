<script lang="ts">
	import { goto } from '$app/navigation';
	import { authApi } from '$lib/api/auth';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { AlertCircle, ArrowRight, Check, Sparkles } from 'lucide-svelte';

	let name = $state('');
	let email = $state('');
	let password = $state('');
	let password_confirmation = $state('');
	let error = $state('');
	let loading = $state(false);

	const benefits = [
		'14-day free trial',
		'No credit card required',
		'Cancel anytime',
		'Full access to all features'
	];

	async function handleSubmit(e: Event) {
		e.preventDefault();
		error = '';

		if (password !== password_confirmation) {
			error = 'Passwords do not match';
			return;
		}

		if (password.length < 8) {
			error = 'Password must be at least 8 characters';
			return;
		}

		loading = true;

		try {
			await authApi.register({ name, email, password, password_confirmation });
			goto('/dashboard');
		} catch (err: any) {
			error = err.message || 'Registration failed. Please try again.';
		} finally {
			loading = false;
		}
	}
</script>

<svelte:head>
	<title>Create Account — VRTX CRM</title>
	<meta name="description" content="Create your VRTX CRM account and start your 14-day free trial." />
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
</svelte:head>

<div class="min-h-screen bg-slate-950 flex">
	<!-- Left panel - Form -->
	<div class="flex-1 flex items-center justify-center px-6 py-12 lg:px-8">
		<div class="w-full max-w-md">
			<!-- Logo -->
			<a href="/" class="inline-flex items-center gap-2 mb-12">
				<div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white font-bold text-slate-900">
					V
				</div>
				<span class="text-xl font-bold text-white tracking-tight">VRTX</span>
			</a>

			<!-- Header -->
			<div class="mb-8">
				<h1 class="font-serif text-3xl text-white mb-2">Create your account</h1>
				<p class="text-slate-400">
					Already have an account?
					<a href="/login" class="text-cyan-400 hover:text-cyan-300 transition-colors ml-1">Sign in</a>
				</p>
			</div>

			<!-- Error message -->
			{#if error}
				<div class="mb-6 flex items-start gap-3 rounded-xl bg-rose-500/10 border border-rose-500/20 p-4">
					<AlertCircle class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />
					<div class="text-sm text-rose-200">{error}</div>
				</div>
			{/if}

			<!-- Form -->
			<form onsubmit={handleSubmit} class="space-y-5">
				<div class="space-y-2">
					<Label for="name" class="text-slate-300">Full name</Label>
					<Input
						id="name"
						type="text"
						autocomplete="name"
						required
						bind:value={name}
						disabled={loading}
						placeholder="John Doe"
						class="h-12 bg-slate-900/50 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:ring-cyan-500/20 rounded-xl"
					/>
				</div>

				<div class="space-y-2">
					<Label for="email" class="text-slate-300">Email address</Label>
					<Input
						id="email"
						type="email"
						autocomplete="email"
						required
						bind:value={email}
						disabled={loading}
						placeholder="john@company.com"
						class="h-12 bg-slate-900/50 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:ring-cyan-500/20 rounded-xl"
					/>
				</div>

				<div class="space-y-2">
					<Label for="password" class="text-slate-300">Password</Label>
					<Input
						id="password"
						type="password"
						autocomplete="new-password"
						required
						bind:value={password}
						disabled={loading}
						placeholder="Min. 8 characters"
						class="h-12 bg-slate-900/50 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:ring-cyan-500/20 rounded-xl"
					/>
				</div>

				<div class="space-y-2">
					<Label for="password_confirmation" class="text-slate-300">Confirm password</Label>
					<Input
						id="password_confirmation"
						type="password"
						autocomplete="new-password"
						required
						bind:value={password_confirmation}
						disabled={loading}
						placeholder="Re-enter password"
						class="h-12 bg-slate-900/50 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:ring-cyan-500/20 rounded-xl"
					/>
				</div>

				<Button
					type="submit"
					disabled={loading}
					class="w-full h-12 bg-white text-slate-900 hover:bg-slate-100 rounded-xl font-medium disabled:opacity-50 mt-2"
				>
					{#if loading}
						<span class="inline-flex items-center gap-2">
							<span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-900 border-t-transparent"></span>
							Creating account...
						</span>
					{:else}
						Create account
						<ArrowRight class="ml-2 h-4 w-4" />
					{/if}
				</Button>

				<p class="text-center text-xs text-slate-500 mt-4">
					By creating an account, you agree to our
					<a href="/terms" class="text-slate-400 hover:text-white transition-colors">Terms of Service</a>
					and
					<a href="/privacy" class="text-slate-400 hover:text-white transition-colors">Privacy Policy</a>.
				</p>
			</form>
		</div>
	</div>

	<!-- Right panel - Benefits -->
	<div class="hidden lg:flex lg:flex-1 relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800">
		<!-- Background decorations -->
		<div class="absolute top-0 right-0 w-[500px] h-[500px] bg-gradient-to-bl from-cyan-500/20 via-blue-500/10 to-transparent rounded-full blur-3xl"></div>
		<div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-gradient-to-tr from-violet-500/15 to-transparent rounded-full blur-3xl"></div>

		<!-- Grid pattern -->
		<div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px]"></div>

		<div class="relative flex flex-col justify-center px-16 py-12">
			<div class="max-w-md">
				<div class="inline-flex items-center gap-2 rounded-full bg-white/5 border border-white/10 px-4 py-2 text-sm text-slate-300 backdrop-blur-sm mb-8">
					<Sparkles class="h-4 w-4 text-amber-400" />
					<span>Start your free trial today</span>
				</div>

				<h2 class="font-serif text-4xl text-white mb-6 leading-tight">
					Transform your sales process in minutes
				</h2>

				<p class="text-lg text-slate-400 mb-10 leading-relaxed">
					Join thousands of teams who've simplified their CRM and accelerated their sales growth.
				</p>

				<ul class="space-y-4">
					{#each benefits as benefit (benefit)}
						<li class="flex items-center gap-3">
							<div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
								<Check class="w-3.5 h-3.5 text-emerald-400" />
							</div>
							<span class="text-slate-300">{benefit}</span>
						</li>
					{/each}
				</ul>

				<!-- Testimonial -->
				<div class="mt-12 p-6 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm">
					<p class="text-slate-300 italic mb-4">
						"VRTX helped us close 40% more deals in the first quarter. The automation alone saves us 10+ hours per week."
					</p>
					<div class="flex items-center gap-3">
						<div class="w-10 h-10 rounded-full bg-gradient-to-br from-slate-700 to-slate-800 flex items-center justify-center text-sm font-semibold text-white">
							SC
						</div>
						<div>
							<div class="text-sm font-medium text-white">Sarah Chen</div>
							<div class="text-xs text-slate-500">VP of Sales, Meridian Tech</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
	:global(body) {
		font-family: 'Geist', system-ui, sans-serif;
	}

	.font-serif {
		font-family: 'Instrument Serif', Georgia, serif;
	}
</style>
