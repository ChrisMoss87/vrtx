<script lang="ts">
	import ResponsiveModal from '$lib/components/ui/responsive-modal/responsive-modal.svelte';
	import Button from '$lib/components/ui/button/button.svelte';
	import { Calendar } from '$lib/components/ui/calendar';

	let basicModalOpen = $state(false);
	let formModalOpen = $state(false);
	let customModalOpen = $state(false);
	let name = $state('');
	let email = $state('');

	function handleFormSubmit(e: Event) {
		e.preventDefault();
		console.log('Form submitted:', { name, email });
		formModalOpen = false;
		// Reset form
		name = '';
		email = '';
	}

	function handleOpenChange(isOpen: boolean) {
		console.log('Modal state changed:', isOpen ? 'open' : 'closed');
	}
</script>

<svelte:head>
	<title>Responsive Modal Demo</title>
</svelte:head>

<div class="container mx-auto max-w-4xl p-8">
	<div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
		<!-- Basic Modal Example -->
		<div class="space-y-4 rounded-lg border p-6">
			<div>
				<h2 class="mb-2 text-xl font-semibold">Basic Modal</h2>
				<p class="text-sm text-muted-foreground">
					Simple modal with title, description, and content.
				</p>
			</div>
			<Button onclick={() => (basicModalOpen = true)} class="w-full">Open Basic Modal</Button>
		</div>

		<!-- Form Modal Example -->
		<div class="space-y-4 rounded-lg border p-6">
			<div>
				<h2 class="mb-2 text-xl font-semibold">Form Modal</h2>
				<p class="text-sm text-muted-foreground">
					Modal containing a form with input fields and actions.
				</p>
			</div>
			<Button onclick={() => (formModalOpen = true)} class="w-full">Open Form Modal</Button>
		</div>

		<!-- Custom Content Modal -->
		<div class="space-y-4 rounded-lg border p-6">
			<div>
				<h2 class="mb-2 text-xl font-semibold">Custom Content</h2>
				<p class="text-sm text-muted-foreground">Modal with rich custom content and styling.</p>
			</div>
			<Button onclick={() => (customModalOpen = true)} class="w-full">Open Custom Modal</Button>
		</div>
	</div>

	<!-- Features Section -->
	<div class="mt-12 grid gap-6 md:grid-cols-2">
		<div class="space-y-4">
			<h2 class="text-2xl font-semibold">Features</h2>
			<ul class="space-y-2">
				<li class="flex items-start gap-2">
					<span class="mt-1 text-green-600 dark:text-green-400">✓</span>
					<span>Automatic responsive behavior based on screen width</span>
				</li>
				<li class="flex items-start gap-2">
					<span class="mt-1 text-green-600 dark:text-green-400">✓</span>
					<span>Smooth transitions between drawer and dialog modes</span>
				</li>
				<li class="flex items-start gap-2">
					<span class="mt-1 text-green-600 dark:text-green-400">✓</span>
					<span>Accessible keyboard navigation (Escape to close)</span>
				</li>
				<li class="flex items-start gap-2">
					<span class="mt-1 text-green-600 dark:text-green-400">✓</span>
					<span>Focus management and trapping</span>
				</li>
				<li class="flex items-start gap-2">
					<span class="mt-1 text-green-600 dark:text-green-400">✓</span>
					<span>Built with shadcn-svelte components</span>
				</li>
			</ul>
		</div>
	</div>
</div>

<!-- Basic Modal -->
<ResponsiveModal
	bind:open={basicModalOpen}
	onOpenChange={handleOpenChange}
	title="Welcome!"
	description="This is a basic responsive modal example."
>
	<div class="space-y-4 py-4">
		<p>
			This modal demonstrates the basic usage of the responsive modal component. It includes a
			title, description, and custom content.
		</p>
		<p class="text-sm text-muted-foreground">
			Try pressing <kbd
				class="rounded border bg-slate-100 px-2 py-1 text-xs font-semibold dark:bg-slate-800"
				>Escape</kbd
			> to close this modal, or click the overlay.
		</p>
		<div class="flex justify-end gap-2">
			<Button variant="outline" onclick={() => (basicModalOpen = false)}>Close</Button>
			<Button onclick={() => (basicModalOpen = false)}>Got it</Button>
		</div>
	</div>
</ResponsiveModal>

<!-- Form Modal -->
<ResponsiveModal
	bind:open={formModalOpen}
	title="Edit Profile"
	description="Make changes to your profile information."
>
	<form onsubmit={handleFormSubmit} class="space-y-4 py-4">
		<div class="space-y-2">
			<label for="name" class="text-sm leading-none font-medium">Name</label>
			<input
				id="name"
				type="text"
				bind:value={name}
				class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
				placeholder="Enter your name"
				required
			/>
		</div>

		<div class="space-y-2">
			<label for="email" class="text-sm leading-none font-medium">Email</label>
			<input
				id="email"
				type="email"
				bind:value={email}
				class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
				placeholder="Enter your email"
				required
			/>
		</div>

		<div class="flex justify-end gap-2 pt-4">
			<Button type="button" variant="outline" onclick={() => (formModalOpen = false)}>
				Cancel
			</Button>
			<Button type="submit">Save Changes</Button>
		</div>
	</form>
</ResponsiveModal>

<!-- Custom Content Modal -->
<ResponsiveModal bind:open={customModalOpen}>
	<div class="space-y-4 py-4">
		<div class="space-y-2 text-center">
			<div
				class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-pink-500"
			>
				<span class="text-2xl">🎉</span>
			</div>
			<h2 class="text-2xl font-bold">Custom Styled Modal</h2>
			<p class="text-muted-foreground">
				This modal doesn't use the built-in title/description props
			</p>
		</div>

		<div
			class="rounded-lg bg-gradient-to-r from-blue-50 to-purple-50 p-4 dark:from-blue-950 dark:to-purple-950"
		>
			<h3 class="mb-2 font-semibold">Pro Tip</h3>
			<p class="text-sm">
				You can completely customize the content of the modal by not providing title or description
				props and using your own HTML structure.
			</p>
		</div>

		<div class="grid grid-cols-2 gap-2">
			<div class="rounded-lg border p-3 text-center">
				<div class="text-2xl font-bold text-blue-600 dark:text-blue-400">100%</div>
				<div class="text-xs text-muted-foreground">Responsive</div>
			</div>
			<div class="rounded-lg border p-3 text-center">
				<div class="text-2xl font-bold text-green-600 dark:text-green-400">A11y</div>
				<div class="text-xs text-muted-foreground">Accessible</div>
			</div>
		</div>

		<Button onclick={() => (customModalOpen = false)} class="w-full">Close</Button>
	</div>
</ResponsiveModal>
