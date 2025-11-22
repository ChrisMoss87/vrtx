<script lang="ts">
	import { ResponsiveModal } from '$lib/components/ui/responsive-modal';
	import { Button } from '$lib/components/ui/button';

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

<div class="container mx-auto p-8 max-w-4xl">
	<div class="mb-8">
		<h1 class="text-4xl font-bold mb-4">Responsive Modal Component</h1>
		<p class="text-lg text-muted-foreground mb-4">
			This modal automatically adapts to your screen size. On mobile devices (&lt; 768px), it appears
			as a drawer that slides up from the bottom. On desktop screens (≥ 768px), it appears as a
			centered dialog modal.
		</p>
		<div class="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
			<p class="text-sm text-blue-900 dark:text-blue-100">
				<strong>Try it:</strong> Resize your browser window or open this on different devices to see
				the responsive behavior in action!
			</p>
		</div>
	</div>

	<div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
		<!-- Basic Modal Example -->
		<div class="border rounded-lg p-6 space-y-4">
			<div>
				<h2 class="text-xl font-semibold mb-2">Basic Modal</h2>
				<p class="text-sm text-muted-foreground">
					Simple modal with title, description, and content.
				</p>
			</div>
			<Button onclick={() => (basicModalOpen = true)} class="w-full">Open Basic Modal</Button>
		</div>

		<!-- Form Modal Example -->
		<div class="border rounded-lg p-6 space-y-4">
			<div>
				<h2 class="text-xl font-semibold mb-2">Form Modal</h2>
				<p class="text-sm text-muted-foreground">
					Modal containing a form with input fields and actions.
				</p>
			</div>
			<Button onclick={() => (formModalOpen = true)} class="w-full">Open Form Modal</Button>
		</div>

		<!-- Custom Content Modal -->
		<div class="border rounded-lg p-6 space-y-4">
			<div>
				<h2 class="text-xl font-semibold mb-2">Custom Content</h2>
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
					<span class="text-green-600 dark:text-green-400 mt-1">✓</span>
					<span>Automatic responsive behavior based on screen width</span>
				</li>
				<li class="flex items-start gap-2">
					<span class="text-green-600 dark:text-green-400 mt-1">✓</span>
					<span>Smooth transitions between drawer and dialog modes</span>
				</li>
				<li class="flex items-start gap-2">
					<span class="text-green-600 dark:text-green-400 mt-1">✓</span>
					<span>Accessible keyboard navigation (Escape to close)</span>
				</li>
				<li class="flex items-start gap-2">
					<span class="text-green-600 dark:text-green-400 mt-1">✓</span>
					<span>Focus management and trapping</span>
				</li>
				<li class="flex items-start gap-2">
					<span class="text-green-600 dark:text-green-400 mt-1">✓</span>
					<span>Built with shadcn-svelte components</span>
				</li>
			</ul>
		</div>

		<div class="space-y-4">
			<h2 class="text-2xl font-semibold">Breakpoints</h2>
			<div class="space-y-3">
				<div class="border rounded-lg p-4">
					<h3 class="font-semibold mb-1">Mobile (&lt; 768px)</h3>
					<p class="text-sm text-muted-foreground">
						Displays as a drawer component that slides from the bottom
					</p>
				</div>
				<div class="border rounded-lg p-4">
					<h3 class="font-semibold mb-1">Desktop (≥ 768px)</h3>
					<p class="text-sm text-muted-foreground">
						Displays as a centered dialog modal with overlay
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Code Example -->
	<div class="mt-12">
		<h2 class="text-2xl font-semibold mb-4">Usage Example</h2>
		<pre
			class="bg-slate-950 text-slate-50 rounded-lg p-4 overflow-x-auto text-sm"><code>{`<script lang="ts">
  import { ResponsiveModal } from '$lib/components/ui/responsive-modal';
  import { Button } from '$lib/components/ui/button';

  let open = $state(false);
</script>

<Button onclick={() => (open = true)}>
  Open Modal
</Button>

<ResponsiveModal
  bind:open
  title="Modal Title"
  description="Modal description"
>
  <p>Your content here</p>
</ResponsiveModal>`}</code></pre>
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
				class="px-2 py-1 text-xs font-semibold bg-slate-100 dark:bg-slate-800 border rounded">Escape</kbd
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
			<label for="name" class="text-sm font-medium leading-none">Name</label>
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
			<label for="email" class="text-sm font-medium leading-none">Email</label>
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
	<div class="py-4 space-y-4">
		<div class="text-center space-y-2">
			<div
				class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full mx-auto flex items-center justify-center"
			>
				<span class="text-2xl">🎉</span>
			</div>
			<h2 class="text-2xl font-bold">Custom Styled Modal</h2>
			<p class="text-muted-foreground">
				This modal doesn't use the built-in title/description props
			</p>
		</div>

		<div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950 dark:to-purple-950 rounded-lg p-4">
			<h3 class="font-semibold mb-2">Pro Tip</h3>
			<p class="text-sm">
				You can completely customize the content of the modal by not providing title or
				description props and using your own HTML structure.
			</p>
		</div>

		<div class="grid grid-cols-2 gap-2">
			<div class="border rounded-lg p-3 text-center">
				<div class="text-2xl font-bold text-blue-600 dark:text-blue-400">100%</div>
				<div class="text-xs text-muted-foreground">Responsive</div>
			</div>
			<div class="border rounded-lg p-3 text-center">
				<div class="text-2xl font-bold text-green-600 dark:text-green-400">A11y</div>
				<div class="text-xs text-muted-foreground">Accessible</div>
			</div>
		</div>

		<Button onclick={() => (customModalOpen = false)} class="w-full">Close</Button>
	</div>
</ResponsiveModal>
