# Responsive Modal Component

A responsive modal component that automatically adapts to screen size:

- **Mobile (< 768px)**: Displays as a drawer that slides up from the bottom
- **Desktop (≥ 768px)**: Displays as a centered dialog modal

## Features

- Automatic responsive behavior based on screen width
- Smooth transitions between drawer and dialog
- Accessible keyboard navigation
- Focus management
- Bindable open state
- Optional title and description
- Supports custom content via Svelte snippets

## Installation

This component is already set up in your project at:

```
src/lib/components/ui/responsive-modal/
```

## Basic Usage

```svelte
<script lang="ts">
	import { ResponsiveModal } from '$lib/components/ui/responsive-modal';
	import { Button } from '$lib/components/ui/button';

	let open = $state(false);
</script>

<Button onclick={() => (open = true)}>Open Modal</Button>

<ResponsiveModal bind:open title="Modal Title" description="Modal description text">
	<p>Your content here</p>
</ResponsiveModal>
```

## Advanced Usage

### With Custom Content

```svelte
<script lang="ts">
	import { ResponsiveModal } from '$lib/components/ui/responsive-modal';
	import { Button } from '$lib/components/ui/button';

	let open = $state(false);

	function handleSubmit() {
		// Handle form submission
		open = false;
	}
</script>

<ResponsiveModal bind:open title="Edit Profile" description="Make changes to your profile">
	<form onsubmit={handleSubmit} class="space-y-4">
		<div class="space-y-2">
			<label for="name" class="text-sm font-medium">Name</label>
			<input
				id="name"
				type="text"
				class="w-full rounded-md border px-3 py-2"
				placeholder="Enter your name"
			/>
		</div>

		<div class="space-y-2">
			<label for="email" class="text-sm font-medium">Email</label>
			<input
				id="email"
				type="email"
				class="w-full rounded-md border px-3 py-2"
				placeholder="Enter your email"
			/>
		</div>

		<div class="flex justify-end gap-2">
			<Button type="button" variant="outline" onclick={() => (open = false)}>Cancel</Button>
			<Button type="submit">Save Changes</Button>
		</div>
	</form>
</ResponsiveModal>
```

### With OnOpenChange Callback

```svelte
<script lang="ts">
	import { ResponsiveModal } from '$lib/components/ui/responsive-modal';

	let open = $state(false);

	function handleOpenChange(isOpen: boolean) {
		console.log('Modal is now:', isOpen ? 'open' : 'closed');
		// Perform any cleanup or initialization
	}
</script>

<ResponsiveModal bind:open onOpenChange={handleOpenChange} title="Notification Settings">
	<!-- Content -->
</ResponsiveModal>
```

### Without Title/Description

```svelte
<ResponsiveModal bind:open>
	<div class="p-4">
		<h2 class="mb-2 text-lg font-semibold">Custom Header</h2>
		<p>Completely custom content without using the built-in header.</p>
	</div>
</ResponsiveModal>
```

## Props

| Prop           | Type                      | Default     | Description                               |
| -------------- | ------------------------- | ----------- | ----------------------------------------- |
| `open`         | `boolean`                 | `false`     | Controls the open state (use `bind:open`) |
| `onOpenChange` | `(open: boolean) => void` | `undefined` | Callback when open state changes          |
| `title`        | `string`                  | `undefined` | Optional title for the modal/drawer       |
| `description`  | `string`                  | `undefined` | Optional description text                 |
| `children`     | `Snippet`                 | `undefined` | Content to render inside the modal        |

## Styling

The component uses the default styling from shadcn-svelte's Dialog and Drawer components. You can customize:

- Dialog max width: Modify the `sm:max-w-[425px]` class in the Dialog.Content
- Drawer padding: Adjust the `px-4` class in the drawer content wrapper
- Responsive breakpoint: Change the `768px` media query in the component

## Example Component

See the full example at:

```
src/lib/components/examples/responsive-modal-example.svelte
```

## Technical Details

The component:

1. Uses `window.matchMedia` to detect screen size
2. Listens for resize events to switch between modes
3. Cleans up event listeners on component unmount
4. Maintains consistent API regardless of display mode
5. Preserves open state during mode transitions

## Accessibility

Both the Dialog and Drawer components from shadcn-svelte are fully accessible:

- Proper ARIA attributes
- Keyboard navigation (Escape to close)
- Focus trapping
- Screen reader support
