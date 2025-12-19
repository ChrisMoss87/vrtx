<script lang="ts">
	import type { PageElement, PageStyles } from '$lib/api/landing-pages';
	import { cn } from '$lib/utils';

	interface Props {
		element: PageElement;
		styles?: PageStyles;
		isSelected?: boolean;
		isEditing?: boolean;
		onSelect?: () => void;
		onUpdate?: (props: Record<string, unknown>) => void;
	}

	let {
		element,
		styles = {},
		isSelected = false,
		isEditing = false,
		onSelect,
		onUpdate
	}: Props = $props();

	const primaryColor = $derived((styles.primary_color as string) || '#3b82f6');
	const textColor = $derived((styles.text_color as string) || '#1f2937');
</script>

<div
	class={cn(
		'relative transition-all',
		isEditing && 'cursor-pointer hover:outline hover:outline-2 hover:outline-blue-300',
		isSelected && isEditing && 'outline outline-2 outline-blue-500'
	)}
	onclick={onSelect}
	onkeydown={(e) => e.key === 'Enter' && onSelect?.()}
	role={isEditing ? 'button' : undefined}
	tabindex={isEditing ? 0 : undefined}
>
	{#if element.type === 'section'}
		<section
			class="px-5 py-16"
			style="background-color: {element.props.backgroundColor || '#ffffff'}; padding: {element.props
				.padding || '60px 20px'}"
		>
			{#if element.children}
				{#each element.children as child}
					<svelte:self element={child} {styles} {isEditing} />
				{/each}
			{/if}
		</section>
	{:else if element.type === 'container'}
		<div class="mx-auto" style="max-width: {element.props.maxWidth || '1200px'}">
			{#if element.children}
				{#each element.children as child}
					<svelte:self element={child} {styles} {isEditing} />
				{/each}
			{/if}
		</div>
	{:else if element.type === 'hero'}
		<div class="py-20 text-center">
			<h1 class="mb-4 text-5xl font-bold" style="color: {textColor}">
				{element.props.title || 'Your Headline Here'}
			</h1>
			<p class="text-muted-foreground mx-auto mb-8 max-w-2xl text-xl">
				{element.props.subtitle || 'Your compelling subheadline'}
			</p>
			<button
				class="rounded-lg px-8 py-3 text-lg font-semibold text-white"
				style="background-color: {primaryColor}"
			>
				{element.props.ctaText || 'Get Started'}
			</button>
		</div>
	{:else if element.type === 'heading'}
		{@const level = element.props.level || 'h2'}
		{#if level === 'h1'}
			<h1 class="text-4xl font-bold" style="color: {textColor}">{element.props.text || 'Heading'}</h1>
		{:else if level === 'h2'}
			<h2 class="text-3xl font-bold" style="color: {textColor}">{element.props.text || 'Heading'}</h2>
		{:else if level === 'h3'}
			<h3 class="text-2xl font-bold" style="color: {textColor}">{element.props.text || 'Heading'}</h3>
		{:else}
			<h4 class="text-xl font-bold" style="color: {textColor}">{element.props.text || 'Heading'}</h4>
		{/if}
	{:else if element.type === 'text'}
		<p class="text-base" style="color: {textColor}">
			{element.props.text || 'Your paragraph text goes here.'}
		</p>
	{:else if element.type === 'image'}
		{#if element.props.src}
			<img
				src={element.props.src as string}
				alt={(element.props.alt as string) || 'Image'}
				class="h-auto max-w-full"
			/>
		{:else}
			<div class="bg-muted flex h-48 items-center justify-center rounded-lg">
				<span class="text-muted-foreground">Image placeholder</span>
			</div>
		{/if}
	{:else if element.type === 'video'}
		{#if element.props.src}
			<video
				src={element.props.src as string}
				controls
				autoplay={Boolean(element.props.autoplay) || false}
				class="h-auto w-full rounded-lg"
			>
				<track kind="captions" />
			</video>
		{:else}
			<div class="bg-muted flex h-64 items-center justify-center rounded-lg">
				<span class="text-muted-foreground">Video placeholder</span>
			</div>
		{/if}
	{:else if element.type === 'button'}
		<button
			class={cn(
				'rounded-lg px-6 py-2 font-semibold transition-colors',
				element.props.variant === 'secondary'
					? 'border-2 bg-transparent'
					: 'text-white'
			)}
			style={element.props.variant === 'secondary'
				? `border-color: ${primaryColor}; color: ${primaryColor}`
				: `background-color: ${primaryColor}`}
		>
			{element.props.text || 'Click Me'}
		</button>
	{:else if element.type === 'cta'}
		<div class="rounded-lg py-12 text-center" style="background-color: {primaryColor}10">
			<h2 class="mb-4 text-3xl font-bold" style="color: {textColor}">
				{element.props.title || 'Ready to Get Started?'}
			</h2>
			<button
				class="rounded-lg px-8 py-3 text-lg font-semibold text-white"
				style="background-color: {primaryColor}"
			>
				{element.props.buttonText || 'Sign Up Now'}
			</button>
		</div>
	{:else if element.type === 'form'}
		<div class="bg-muted/30 rounded-lg border p-6">
			{#if element.props.formId}
				<p class="text-muted-foreground text-center">Form #{element.props.formId} will be embedded here</p>
			{:else}
				<p class="text-muted-foreground text-center">Select a form to embed</p>
			{/if}
		</div>
	{:else if element.type === 'testimonials'}
		<div class="grid gap-6 md:grid-cols-3">
			{#if element.props.items && Array.isArray(element.props.items) && element.props.items.length > 0}
				{#each element.props.items as testimonial}
					<div class="rounded-lg border p-6">
						<p class="text-muted-foreground mb-4 italic">"{testimonial.quote}"</p>
						<div class="font-semibold">{testimonial.author}</div>
						{#if testimonial.role}
							<div class="text-muted-foreground text-sm">{testimonial.role}</div>
						{/if}
					</div>
				{/each}
			{:else}
				<div class="col-span-3 rounded-lg border p-6 text-center">
					<p class="text-muted-foreground">Add testimonials to display here</p>
				</div>
			{/if}
		</div>
	{:else if element.type === 'features'}
		<div class="grid gap-6 md:grid-cols-3">
			{#if element.props.items && Array.isArray(element.props.items) && element.props.items.length > 0}
				{#each element.props.items as feature}
					<div class="rounded-lg border p-6 text-center">
						<div class="mb-4 text-4xl">{feature.icon || '✨'}</div>
						<h3 class="mb-2 text-lg font-semibold">{feature.title}</h3>
						<p class="text-muted-foreground text-sm">{feature.description}</p>
					</div>
				{/each}
			{:else}
				<div class="col-span-3 rounded-lg border p-6 text-center">
					<p class="text-muted-foreground">Add features to display here</p>
				</div>
			{/if}
		</div>
	{:else if element.type === 'pricing'}
		<div class="grid gap-6 md:grid-cols-3">
			{#if element.props.items && Array.isArray(element.props.items) && element.props.items.length > 0}
				{#each element.props.items as plan}
					<div class={cn('rounded-lg border p-6', plan.featured && 'border-2 ring-2')} style={plan.featured ? `border-color: ${primaryColor}; --tw-ring-color: ${primaryColor}20` : ''}>
						<h3 class="mb-2 text-xl font-bold">{plan.name}</h3>
						<div class="mb-4 text-3xl font-bold">{plan.price}</div>
						<ul class="text-muted-foreground mb-6 space-y-2 text-sm">
							{#each plan.features || [] as feature}
								<li>✓ {feature}</li>
							{/each}
						</ul>
						<button
							class="w-full rounded-lg py-2 font-semibold text-white"
							style="background-color: {primaryColor}"
						>
							{plan.buttonText || 'Get Started'}
						</button>
					</div>
				{/each}
			{:else}
				<div class="col-span-3 rounded-lg border p-6 text-center">
					<p class="text-muted-foreground">Add pricing plans to display here</p>
				</div>
			{/if}
		</div>
	{:else if element.type === 'faq'}
		<div class="mx-auto max-w-3xl space-y-4">
			{#if element.props.items && Array.isArray(element.props.items) && element.props.items.length > 0}
				{#each element.props.items as faq}
					<div class="rounded-lg border p-4">
						<h4 class="font-semibold">{faq.question}</h4>
						<p class="text-muted-foreground mt-2 text-sm">{faq.answer}</p>
					</div>
				{/each}
			{:else}
				<div class="rounded-lg border p-6 text-center">
					<p class="text-muted-foreground">Add FAQ items to display here</p>
				</div>
			{/if}
		</div>
	{:else if element.type === 'footer'}
		<footer class="bg-muted/30 py-8 text-center">
			<p class="text-muted-foreground text-sm">
				{element.props.copyright || '© 2025 Your Company'}
			</p>
		</footer>
	{:else if element.type === 'divider'}
		<hr class="border-border my-4" style="border-style: {element.props.style || 'solid'}" />
	{:else if element.type === 'spacer'}
		<div style="height: {element.props.height || '40px'}"></div>
	{/if}

	{#if isSelected && isEditing}
		<div class="absolute -top-2 left-2 rounded bg-blue-500 px-2 py-0.5 text-xs text-white">
			{element.type}
		</div>
	{/if}
</div>
