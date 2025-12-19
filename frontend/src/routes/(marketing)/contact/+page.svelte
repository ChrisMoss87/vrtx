<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Mail, Phone, MapPin, MessageSquare, Clock, Headphones, Send, CheckCircle2 } from 'lucide-svelte';

	let formData = $state({
		name: '',
		email: '',
		company: '',
		subject: 'general',
		message: ''
	});

	let isSubmitting = $state(false);
	let submitSuccess = $state(false);

	const subjects = [
		{ value: 'general', label: 'General Inquiry' },
		{ value: 'sales', label: 'Sales Question' },
		{ value: 'support', label: 'Technical Support' },
		{ value: 'partnership', label: 'Partnership' },
		{ value: 'press', label: 'Press & Media' }
	];

	const contactMethods = [
		{
			icon: Headphones,
			title: 'Sales',
			description: 'Talk to our team about pricing, demos, and enterprise features.',
			contact: 'sales@vrtx.io',
			href: 'mailto:sales@vrtx.io',
			gradient: 'from-cyan-500 to-blue-600'
		},
		{
			icon: MessageSquare,
			title: 'Support',
			description: 'Need help with VRTX? Our support team is here for you.',
			contact: 'support@vrtx.io',
			href: 'mailto:support@vrtx.io',
			gradient: 'from-violet-500 to-purple-600'
		},
		{
			icon: Clock,
			title: 'Office Hours',
			description: 'Monday - Friday, 9:00 AM - 6:00 PM EST',
			contact: '+1 (888) VRTX-CRM',
			href: 'tel:+18888798276',
			gradient: 'from-amber-500 to-orange-600'
		}
	];

	async function handleSubmit(e: Event) {
		e.preventDefault();
		isSubmitting = true;

		// Simulate form submission
		await new Promise((resolve) => setTimeout(resolve, 1500));

		submitSuccess = true;
		isSubmitting = false;
	}

	function resetForm() {
		submitSuccess = false;
		formData = {
			name: '',
			email: '',
			company: '',
			subject: 'general',
			message: ''
		};
	}
</script>

<svelte:head>
	<title>Contact — VRTX CRM</title>
	<meta
		name="description"
		content="Get in touch with VRTX CRM. Contact our team for sales inquiries, support, or partnership opportunities."
	/>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
	<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
</svelte:head>

<!-- Hero -->
<section class="relative bg-slate-950 pt-24 pb-16 overflow-hidden">
	<div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-violet-500/10 via-cyan-500/5 to-transparent rounded-full blur-3xl"></div>

	<div class="relative mx-auto max-w-7xl px-6 lg:px-8">
		<div class="mx-auto max-w-2xl text-center">
			<p class="text-sm uppercase tracking-[0.2em] text-cyan-400 mb-4">Contact</p>
			<h1 class="font-serif text-4xl sm:text-5xl lg:text-6xl text-white">
				Get in touch
			</h1>
			<p class="mt-6 text-lg text-slate-400">
				Have a question or want to learn more? We'd love to hear from you.
			</p>
		</div>
	</div>
</section>

<!-- Contact Methods -->
<section class="relative bg-slate-950 pb-16">
	<div class="mx-auto max-w-7xl px-6 lg:px-8">
		<div class="grid gap-6 md:grid-cols-3">
			{#each contactMethods as method (method.title)}
				<a
					href={method.href}
					class="group rounded-2xl border border-white/5 bg-slate-900/50 p-8 hover:border-white/10 hover:bg-slate-900/80 transition-all duration-300"
				>
					<div class="mb-6 inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br {method.gradient} shadow-lg group-hover:scale-110 transition-transform duration-300">
						<method.icon class="w-6 h-6 text-white" />
					</div>
					<h3 class="text-lg font-semibold text-white mb-2">{method.title}</h3>
					<p class="text-sm text-slate-400 mb-4">{method.description}</p>
					<span class="text-cyan-400 font-medium group-hover:underline">{method.contact}</span>
				</a>
			{/each}
		</div>
	</div>
</section>

<!-- Contact Form -->
<section class="relative bg-slate-900 py-24">
	<div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/30 to-transparent"></div>

	<div class="mx-auto max-w-3xl px-6 lg:px-8">
		<div class="rounded-2xl border border-white/5 bg-slate-950/50 p-8 sm:p-12">
			{#if submitSuccess}
				<div class="text-center py-12">
					<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/20 mb-6">
						<CheckCircle2 class="w-8 h-8 text-emerald-400" />
					</div>
					<h3 class="font-serif text-2xl text-white mb-3">Message sent!</h3>
					<p class="text-slate-400 mb-8">
						Thank you for reaching out. We'll get back to you within 24 hours.
					</p>
					<Button onclick={resetForm} class="h-12 px-8 bg-white text-slate-900 hover:bg-slate-100 rounded-xl font-medium">
						Send another message
					</Button>
				</div>
			{:else}
				<div class="mb-8">
					<h2 class="font-serif text-2xl text-white mb-2">Send us a message</h2>
					<p class="text-slate-400">Fill out the form and we'll get back to you within 24 hours.</p>
				</div>

				<form onsubmit={handleSubmit} class="space-y-6">
					<div class="grid gap-6 sm:grid-cols-2">
						<div class="space-y-2">
							<Label for="name" class="text-slate-300">Name *</Label>
							<Input
								id="name"
								bind:value={formData.name}
								placeholder="Your name"
								required
								class="h-12 bg-slate-900/50 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:ring-cyan-500/20 rounded-xl"
							/>
						</div>
						<div class="space-y-2">
							<Label for="email" class="text-slate-300">Email *</Label>
							<Input
								id="email"
								type="email"
								bind:value={formData.email}
								placeholder="you@company.com"
								required
								class="h-12 bg-slate-900/50 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:ring-cyan-500/20 rounded-xl"
							/>
						</div>
					</div>

					<div class="grid gap-6 sm:grid-cols-2">
						<div class="space-y-2">
							<Label for="company" class="text-slate-300">Company</Label>
							<Input
								id="company"
								bind:value={formData.company}
								placeholder="Your company"
								class="h-12 bg-slate-900/50 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:ring-cyan-500/20 rounded-xl"
							/>
						</div>
						<div class="space-y-2">
							<Label for="subject" class="text-slate-300">Subject *</Label>
							<select
								id="subject"
								bind:value={formData.subject}
								class="flex h-12 w-full rounded-xl border border-white/10 bg-slate-900/50 px-4 text-sm text-white focus:border-cyan-500/50 focus:outline-none focus:ring-2 focus:ring-cyan-500/20"
							>
								{#each subjects as subject (subject.value)}
									<option value={subject.value} class="bg-slate-900">{subject.label}</option>
								{/each}
							</select>
						</div>
					</div>

					<div class="space-y-2">
						<Label for="message" class="text-slate-300">Message *</Label>
						<textarea
							id="message"
							bind:value={formData.message}
							placeholder="How can we help you?"
							rows="5"
							required
							class="flex w-full rounded-xl border border-white/10 bg-slate-900/50 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 resize-none"
						></textarea>
					</div>

					<Button
						type="submit"
						disabled={isSubmitting}
						class="w-full h-12 bg-white text-slate-900 hover:bg-slate-100 rounded-xl font-medium disabled:opacity-50"
					>
						{#if isSubmitting}
							<span class="inline-flex items-center gap-2">
								<span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-900 border-t-transparent"></span>
								Sending...
							</span>
						{:else}
							Send message
							<Send class="ml-2 h-4 w-4" />
						{/if}
					</Button>

					<p class="text-center text-xs text-slate-500">
						By submitting, you agree to our
						<a href="/privacy" class="text-slate-400 hover:text-white transition-colors">Privacy Policy</a>.
					</p>
				</form>
			{/if}
		</div>
	</div>

	<div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-violet-500/30 to-transparent"></div>
</section>

<!-- Location -->
<section class="relative bg-slate-950 py-24">
	<div class="mx-auto max-w-7xl px-6 lg:px-8">
		<div class="text-center mb-12">
			<h2 class="font-serif text-2xl text-white mb-4">Our Office</h2>
			<div class="inline-flex items-center gap-2 text-slate-400">
				<MapPin class="w-5 h-5 text-cyan-400" />
				<span>123 Innovation Drive, Suite 400, San Francisco, CA 94107</span>
			</div>
		</div>

		<!-- Map placeholder -->
		<div class="rounded-2xl border border-white/5 overflow-hidden">
			<div class="aspect-[2.5/1] bg-slate-900/50 flex items-center justify-center">
				<div class="text-center">
					<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-800/50 mb-4">
						<MapPin class="w-8 h-8 text-slate-600" />
					</div>
					<p class="text-slate-500">Interactive map coming soon</p>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- CTA -->
<section class="relative bg-slate-900 py-20">
	<div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/30 to-transparent"></div>

	<div class="mx-auto max-w-4xl px-6 lg:px-8 text-center">
		<h2 class="font-serif text-2xl sm:text-3xl text-white mb-4">
			Looking for quick answers?
		</h2>
		<p class="text-slate-400 mb-8">
			Check out our documentation or browse frequently asked questions.
		</p>
		<div class="flex flex-col sm:flex-row items-center justify-center gap-4">
			<Button href="/docs" class="h-12 px-8 bg-white text-slate-900 hover:bg-slate-100 rounded-xl font-medium">
				View documentation
			</Button>
			<Button href="/pricing#faq" variant="ghost" class="h-12 px-8 text-slate-300 hover:text-white hover:bg-white/5 rounded-xl">
				Browse FAQs
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
