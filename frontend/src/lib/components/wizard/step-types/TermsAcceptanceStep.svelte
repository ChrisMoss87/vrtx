<script lang="ts">
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import * as Card from '$lib/components/ui/card';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Label } from '$lib/components/ui/label';
	import { FileText, ShieldCheck } from 'lucide-svelte';
	import DOMPurify from 'isomorphic-dompurify';

	interface TermsSection {
		id: string;
		title: string;
		content: string;
		required: boolean;
		accepted?: boolean;
	}

	interface Props {
		title?: string;
		description?: string;
		sections?: TermsSection[];
		accepted?: boolean;
		onAcceptChange?: (accepted: boolean, sections: TermsSection[]) => void;
		showLastUpdated?: boolean;
		lastUpdated?: string;
		termsUrl?: string;
		privacyUrl?: string;
	}

	let {
		title = 'Terms and Conditions',
		description = 'Please review and accept our terms to continue',
		sections = $bindable([
			{
				id: 'terms',
				title: 'Terms of Service',
				content: `By using our service, you agree to the following terms and conditions...`,
				required: true,
				accepted: false
			},
			{
				id: 'privacy',
				title: 'Privacy Policy',
				content: `We collect and process your personal data in accordance with applicable laws...`,
				required: true,
				accepted: false
			}
		]),
		accepted = $bindable(false),
		onAcceptChange,
		showLastUpdated = true,
		lastUpdated = new Date().toLocaleDateString(),
		termsUrl,
		privacyUrl
	}: Props = $props();

	const allRequiredAccepted = $derived(sections.filter((s) => s.required).every((s) => s.accepted));

	function handleSectionAccept(sectionId: string, isAccepted: boolean) {
		sections = sections.map((s) => (s.id === sectionId ? { ...s, accepted: isAccepted } : s));

		// Update overall accepted status
		const newAccepted = allRequiredAccepted;
		accepted = newAccepted;

		if (onAcceptChange) {
			onAcceptChange(newAccepted, sections);
		}
	}

	function handleMasterAccept(isAccepted: boolean) {
		sections = sections.map((s) => ({ ...s, accepted: isAccepted }));
		accepted = isAccepted;

		if (onAcceptChange) {
			onAcceptChange(isAccepted, sections);
		}
	}
</script>

<div class="terms-acceptance-step space-y-6">
	<!-- Header -->
	<div class="text-center">
		<div class="mb-3 flex items-center justify-center gap-2">
			<FileText class="h-8 w-8 text-primary" />
		</div>
		<h2 class="text-2xl font-bold tracking-tight">{title}</h2>
		{#if description}
			<p class="mt-2 text-muted-foreground">{description}</p>
		{/if}
		{#if showLastUpdated && lastUpdated}
			<p class="mt-2 text-xs text-muted-foreground">Last updated: {lastUpdated}</p>
		{/if}
	</div>

	<!-- Terms Sections -->
	<div class="space-y-4">
		{#each sections as section}
			<Card.Root>
				<Card.Header>
					<Card.Title class="flex items-center gap-2">
						{section.title}
						{#if section.required}
							<span class="text-xs text-destructive">*</span>
						{/if}
					</Card.Title>
				</Card.Header>
				<Card.Content>
					<!-- Terms Content -->
					<ScrollArea class="mb-4 h-48 w-full rounded-md border p-4">
						<div class="prose prose-sm dark:prose-invert max-w-none">
							{@html DOMPurify.sanitize(section.content.replace(/\n/g, '<br>'), { ALLOWED_TAGS: ['br', 'p', 'b', 'i', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'a'], ALLOWED_ATTR: ['href', 'target', 'rel'] })}
						</div>
					</ScrollArea>

					<!-- External Link -->
					{#if (section.id === 'terms' && termsUrl) || (section.id === 'privacy' && privacyUrl)}
						<a
							href={section.id === 'terms' ? termsUrl : privacyUrl}
							target="_blank"
							rel="noopener noreferrer"
							class="text-sm text-primary hover:underline"
						>
							View full {section.title.toLowerCase()} →
						</a>
					{/if}

					<!-- Acceptance Checkbox -->
					<div class="mt-4 flex items-start gap-3 rounded-lg bg-muted/30 p-3">
						<Checkbox
							id={`accept-${section.id}`}
							checked={section.accepted}
							onCheckedChange={(checked) => handleSectionAccept(section.id, checked === true)}
							class="mt-0.5"
						/>
						<Label
							for={`accept-${section.id}`}
							class="cursor-pointer text-sm leading-relaxed font-normal"
						>
							I have read and agree to the {section.title}
							{#if section.required}
								<span class="text-destructive">*</span>
							{/if}
						</Label>
					</div>
				</Card.Content>
			</Card.Root>
		{/each}
	</div>

	<!-- Master Acceptance -->
	{#if sections.length > 1}
		<Card.Root class="border-primary/50">
			<Card.Content class="pt-6">
				<div class="flex items-start gap-3">
					<Checkbox
						id="accept-all"
						checked={allRequiredAccepted}
						onCheckedChange={(checked) => handleMasterAccept(checked === true)}
						class="mt-1"
					/>
					<div class="flex-1">
						<Label for="accept-all" class="cursor-pointer text-base font-medium">
							I accept all terms and conditions
						</Label>
						<p class="mt-1 text-xs text-muted-foreground">
							By checking this box, you agree to all the terms listed above
						</p>
					</div>
					{#if allRequiredAccepted}
						<ShieldCheck class="h-6 w-6 text-green-600" />
					{/if}
				</div>
			</Card.Content>
		</Card.Root>
	{/if}

	<!-- Notice -->
	<div class="text-center text-xs text-muted-foreground">
		<p>
			By accepting these terms, you acknowledge that you have read, understood, and agree to be
			bound by these terms and conditions.
		</p>
	</div>
</div>

<style>
	.terms-acceptance-step {
		max-width: 800px;
		margin: 0 auto;
	}
</style>
