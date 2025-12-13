<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { authApi } from '$lib/api/auth';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { AlertCircle, ArrowRight, BarChart3, Users, Zap } from 'lucide-svelte';

	let email = $state('');
	let password = $state('');
	let error = $state('');
	let loading = $state(false);

	// Get redirect URL from query params
	const redirectUrl = $derived($page.url.searchParams.get('redirect') || '/dashboard');

	async function handleSubmit(e: Event) {
		e.preventDefault();
		error = '';
		loading = true;

		try {
			await authApi.login({ email, password });
			goto(redirectUrl);
		} catch (err: any) {
			error = err.message || 'Login failed. Please check your credentials.';
		} finally {
			loading = false;
		}
	}

	const features = [
		{ icon: BarChart3, text: 'Real-time analytics' },
		{ icon: Users, text: 'Team collaboration' },
		{ icon: Zap, text: 'Workflow automation' }
	];
</script>

<svelte:head>
	<title>Sign In — VRTX CRM</title>
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
				<h1 class="font-serif text-3xl text-white mb-2">Welcome back</h1>
				<p class="text-slate-400">
					Don't have an account?
					<a href="/register" class="text-cyan-400 hover:text-cyan-300 transition-colors ml-1">Create one</a>
				</p>
			</div>

			<!-- Error message -->
			{#if error}
				<div class="mb-6 flex items-start gap-3 rounded-xl bg-rose-500/10 border border-rose-500/20 p-4">
					<AlertCircle class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />
					<div>
						<div class="text-sm font-medium text-rose-200">{error}</div>
						<p class="text-xs text-rose-300/70 mt-1">Please verify your credentials and try again.</p>
					</div>
				</div>
			{/if}

			<!-- Form -->
			<form onsubmit={handleSubmit} class="space-y-5">
				<div class="space-y-2">
					<Label for="email" class="text-slate-300">Email address</Label>
					<Input
						id="email"
						type="email"
						autocomplete="email"
						required
						bind:value={email}
						disabled={loading}
						placeholder="you@company.com"
						class="h-12 bg-slate-900/50 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:ring-cyan-500/20 rounded-xl"
					/>
				</div>

				<div class="space-y-2">
					<div class="flex items-center justify-between">
						<Label for="password" class="text-slate-300">Password</Label>
						<a href="/forgot-password" class="text-xs text-slate-500 hover:text-slate-300 transition-colors">
							Forgot password?
						</a>
					</div>
					<Input
						id="password"
						type="password"
						autocomplete="current-password"
						required
						bind:value={password}
						disabled={loading}
						placeholder="Enter your password"
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
							Signing in...
						</span>
					{:else}
						Sign in
						<ArrowRight class="ml-2 h-4 w-4" />
					{/if}
				</Button>
			</form>

			<!-- Divider -->
			<div class="relative my-8">
				<div class="absolute inset-0 flex items-center">
					<div class="w-full border-t border-white/5"></div>
				</div>
				<div class="relative flex justify-center">
					<span class="bg-slate-950 px-4 text-xs text-slate-600">or continue with</span>
				</div>
			</div>

			<!-- Social logins placeholder -->
			<div class="grid grid-cols-2 gap-3">
				<button class="h-12 rounded-xl border border-white/10 bg-slate-900/30 text-sm text-slate-400 hover:bg-slate-900/50 hover:text-white transition-all">
					Google
				</button>
				<button class="h-12 rounded-xl border border-white/10 bg-slate-900/30 text-sm text-slate-400 hover:bg-slate-900/50 hover:text-white transition-all">
					Microsoft
				</button>
			</div>
		</div>
	</div>

	<!-- Right panel - Visual -->
	<div class="hidden lg:flex lg:flex-1 relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800">
		<!-- Background decorations -->
		<div class="absolute top-0 right-0 w-[500px] h-[500px] bg-gradient-to-bl from-cyan-500/20 via-blue-500/10 to-transparent rounded-full blur-3xl"></div>
		<div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-gradient-to-tr from-violet-500/15 to-transparent rounded-full blur-3xl"></div>

		<!-- Grid pattern -->
		<div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:64px_64px]"></div>

		<div class="relative flex flex-col justify-center px-16 py-12">
			<div class="max-w-md">
				<h2 class="font-serif text-4xl text-white mb-6 leading-tight">
					Your sales command center awaits
				</h2>

				<p class="text-lg text-slate-400 mb-10 leading-relaxed">
					Pick up where you left off. Your pipeline, contacts, and insights are ready.
				</p>

				<div class="space-y-4">
					{#each features as feature (feature.text)}
						<div class="flex items-center gap-4 p-4 rounded-xl bg-white/5 border border-white/5">
							<div class="w-10 h-10 rounded-lg bg-cyan-500/20 flex items-center justify-center">
								<feature.icon class="w-5 h-5 text-cyan-400" />
							</div>
							<span class="text-slate-300">{feature.text}</span>
						</div>
					{/each}
				</div>

				<!-- Stats -->
				<div class="mt-12 grid grid-cols-3 gap-6">
					{#each [
						{ value: '10K+', label: 'Users' },
						{ value: '99.9%', label: 'Uptime' },
						{ value: '4.8', label: 'Rating' }
					] as stat (stat.label)}
						<div class="text-center">
							<div class="font-serif text-2xl text-white">{stat.value}</div>
							<div class="text-xs text-slate-500 uppercase tracking-wider mt-1">{stat.label}</div>
						</div>
					{/each}
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
