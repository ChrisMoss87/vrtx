<script lang="ts">
	import { ResponsiveModal } from '$lib/components/ui/responsive-modal';
	import { Button } from '$lib/components/ui/button';

	let open = $state(false);
</script>

<div class="flex flex-col items-center justify-center p-8 gap-4">
	<h2 class="text-2xl font-bold">Responsive Modal Example</h2>
	<p class="text-muted-foreground text-center max-w-md">
		This modal will appear as a drawer on mobile devices and as a dialog on desktop screens. Resize
		your browser window to see the difference!
	</p>

	<Button onclick={() => (open = true)}>Open Responsive Modal</Button>

	<ResponsiveModal bind:open title="Responsive Modal" description="This adapts to your screen size">
		<div class="grid gap-4 py-4">
			<div class="space-y-2">
				<h3 class="font-semibold">Mobile (< 768px)</h3>
				<p class="text-sm text-muted-foreground">
					On mobile devices, this displays as a drawer that slides up from the bottom of the screen.
				</p>
			</div>
			<div class="space-y-2">
				<h3 class="font-semibold">Desktop (≥ 768px)</h3>
				<p class="text-sm text-muted-foreground">
					On desktop screens, this displays as a centered dialog modal.
				</p>
			</div>
			<div class="space-y-2">
				<h3 class="font-semibold">Features</h3>
				<ul class="text-sm text-muted-foreground list-disc list-inside">
					<li>Automatic responsive behavior</li>
					<li>Smooth transitions</li>
					<li>Accessible keyboard navigation</li>
					<li>Focus management</li>
				</ul>
			</div>
		</div>
	</ResponsiveModal>
</div>
