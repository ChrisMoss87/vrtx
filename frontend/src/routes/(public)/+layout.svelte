<script lang="ts">
	import '../../app.css';

	let { children } = $props();
</script>

<div class="min-h-screen bg-muted/30">
	{@render children()}
</div>
