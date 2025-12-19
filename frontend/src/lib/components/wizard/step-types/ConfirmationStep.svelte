<script lang="ts">
	import type { Snippet } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { CheckCircle, Download, Mail, Home, ArrowRight } from 'lucide-svelte';

	type ActionButton = {
		label: string;
		icon?: any;
		variant?: 'default' | 'outline' | 'secondary';
		onClick: () => void;
	};

	interface Props {
		title?: string;
		message?: string;
		variant?: 'success' | 'info' | 'warning';
		iconColor?: string;
		actions?: ActionButton[];
		showDefaultActions?: boolean;
		children?: Snippet;
		onDownloadReceipt?: () => void;
		onEmailConfirmation?: () => void;
		onReturnHome?: () => void;
		customIcon?: any;
	}

	let {
		title = 'Success!',
		message = 'Your submission has been completed successfully.',
		variant = 'success',
		iconColor,
		actions = [],
		showDefaultActions = true,
		children,
		onDownloadReceipt,
		onEmailConfirmation,
		onReturnHome,
		customIcon
	}: Props = $props();

	const variantConfig = $derived(
		{
			success: {
				bgColor: 'bg-green-100',
				iconColor: iconColor || 'text-green-600',
				icon: customIcon || CheckCircle
			},
			info: {
				bgColor: 'bg-blue-100',
				iconColor: iconColor || 'text-blue-600',
				icon: customIcon || CheckCircle
			},
			warning: {
				bgColor: 'bg-yellow-100',
				iconColor: iconColor || 'text-yellow-600',
				icon: customIcon || CheckCircle
			}
		}[variant]
	);

	const defaultActions = $derived(
		[
			onDownloadReceipt && {
				label: 'Download Receipt',
				icon: Download,
				variant: 'outline' as const,
				onClick: onDownloadReceipt
			},
			onEmailConfirmation && {
				label: 'Email Confirmation',
				icon: Mail,
				variant: 'outline' as const,
				onClick: onEmailConfirmation
			},
			onReturnHome && {
				label: 'Return Home',
				icon: Home,
				variant: 'default' as const,
				onClick: onReturnHome
			}
		].filter(Boolean) as ActionButton[]
	);

	const allActions = $derived(showDefaultActions ? [...defaultActions, ...actions] : actions);
</script>

<div class="confirmation-step">
	<div class="flex flex-col items-center justify-center px-4 py-12">
		<!-- Icon -->
		<div class="rounded-full {variantConfig.bgColor} animate-scaleIn mb-6 p-4">
			<svelte:component this={variantConfig.icon} class="h-16 w-16 {variantConfig.iconColor}" />
		</div>

		<!-- Title & Message -->
		<div class="mb-8 max-w-lg text-center">
			<h2 class="mb-3 text-3xl font-bold tracking-tight">{title}</h2>
			<p class="text-lg text-muted-foreground">{message}</p>
		</div>

		<!-- Custom Content -->
		{#if children}
			<div class="mb-8 w-full max-w-2xl">
				{@render children()}
			</div>
		{/if}

		<!-- Actions -->
		{#if allActions.length > 0}
			<div class="flex flex-wrap items-center justify-center gap-3">
				{#each allActions as action}
					<Button variant={action.variant || 'default'} onclick={action.onClick}>
						{#if action.icon}
							<svelte:component this={action.icon} class="mr-2 h-4 w-4" />
						{/if}
						{action.label}
					</Button>
				{/each}
			</div>
		{/if}
	</div>
</div>

<style>
	.confirmation-step {
		animation: fadeIn 0.5s ease-in;
	}

	@keyframes fadeIn {
		from {
			opacity: 0;
			transform: translateY(20px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	:global(.animate-scaleIn) {
		animation: scaleIn 0.5s ease-out;
	}

	@keyframes scaleIn {
		from {
			transform: scale(0);
			opacity: 0;
		}
		to {
			transform: scale(1);
			opacity: 1;
		}
	}
</style>
