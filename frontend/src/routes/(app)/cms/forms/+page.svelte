<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import {
		Plus,
		Search,
		MoreHorizontal,
		Edit,
		Copy,
		Trash2,
		Eye,
		Code,
		BarChart3,
		FileText,
		CheckCircle,
		XCircle
	} from 'lucide-svelte';
	import { cmsFormApi, type CmsForm } from '$lib/api/cms';
	import { toast } from 'svelte-sonner';

	let forms = $state<CmsForm[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');

	let meta = $state({
		current_page: 1,
		last_page: 1,
		per_page: 25,
		total: 0
	});

	onMount(async () => {
		await loadForms();
	});

	async function loadForms() {
		loading = true;
		try {
			const response = await cmsFormApi.list({
				search: searchQuery || undefined,
				page: meta.current_page,
				per_page: meta.per_page
			});
			forms = response.data;
			meta = response.meta;
		} catch (error) {
			toast.error('Failed to load forms');
		} finally {
			loading = false;
		}
	}

	async function handleDuplicate(form: CmsForm) {
		try {
			const duplicated = await cmsFormApi.duplicate(form.id);
			toast.success('Form duplicated');
			goto(`/cms/forms/${duplicated.id}/edit`);
		} catch (error) {
			toast.error('Failed to duplicate form');
		}
	}

	async function handleDelete(form: CmsForm) {
		if (!confirm(`Are you sure you want to delete "${form.name}"?`)) return;

		try {
			await cmsFormApi.delete(form.id);
			toast.success('Form deleted');
			await loadForms();
		} catch (error) {
			toast.error('Failed to delete form');
		}
	}

	async function handleToggleActive(form: CmsForm) {
		try {
			await cmsFormApi.update(form.id, { is_active: !form.is_active });
			toast.success(form.is_active ? 'Form deactivated' : 'Form activated');
			await loadForms();
		} catch (error) {
			toast.error('Failed to update form');
		}
	}
</script>

<div class="container py-6">
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">CMS Forms</h1>
			<p class="text-muted-foreground">Create forms for lead capture, contact, and feedback</p>
		</div>
		<Button href="/cms/forms/new">
			<Plus class="mr-1 h-4 w-4" />
			New Form
		</Button>
	</div>

	<!-- Search -->
	<Card.Root class="mb-6">
		<Card.Content class="pt-6">
			<div class="relative max-w-md">
				<Search class="text-muted-foreground absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
				<Input
					placeholder="Search forms..."
					class="pl-10"
					bind:value={searchQuery}
					oninput={() => loadForms()}
				/>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Forms Table -->
	<Card.Root>
		<Card.Content class="p-0">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<div class="text-muted-foreground">Loading...</div>
				</div>
			{:else if forms.length === 0}
				<div class="py-12 text-center">
					<FileText class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
					<p class="text-muted-foreground mb-4">No forms found</p>
					<Button href="/cms/forms/new">
						<Plus class="mr-1 h-4 w-4" />
						Create your first form
					</Button>
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Name</Table.Head>
							<Table.Head>Fields</Table.Head>
							<Table.Head>Submissions</Table.Head>
							<Table.Head>Conversion</Table.Head>
							<Table.Head>Status</Table.Head>
							<Table.Head>Updated</Table.Head>
							<Table.Head class="w-12"></Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each forms as form}
							<Table.Row>
								<Table.Cell>
									<div>
										<div class="font-medium">{form.name}</div>
										<div class="text-muted-foreground text-sm">
											<code class="text-xs">{form.slug}</code>
										</div>
									</div>
								</Table.Cell>
								<Table.Cell>
									<span class="text-muted-foreground">{form.fields.length} fields</span>
								</Table.Cell>
								<Table.Cell>
									<span class="font-medium">{form.submission_count.toLocaleString()}</span>
								</Table.Cell>
								<Table.Cell>
									{#if form.view_count > 0}
										<span class="text-muted-foreground">
											{((form.submission_count / form.view_count) * 100).toFixed(1)}%
										</span>
									{:else}
										<span class="text-muted-foreground">—</span>
									{/if}
								</Table.Cell>
								<Table.Cell>
									{#if form.is_active}
										<Badge class="bg-green-100 text-green-800">
											<CheckCircle class="mr-1 h-3 w-3" />
											Active
										</Badge>
									{:else}
										<Badge variant="secondary">
											<XCircle class="mr-1 h-3 w-3" />
											Inactive
										</Badge>
									{/if}
								</Table.Cell>
								<Table.Cell>
									<span class="text-muted-foreground text-sm">
										{new Date(form.updated_at).toLocaleDateString()}
									</span>
								</Table.Cell>
								<Table.Cell>
									<DropdownMenu.Root>
										<DropdownMenu.Trigger>
											<Button variant="ghost" size="sm">
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										</DropdownMenu.Trigger>
										<DropdownMenu.Content align="end">
											<DropdownMenu.Item onclick={() => goto(`/cms/forms/${form.id}/edit`)}>
												<Edit class="mr-2 h-4 w-4" />
												Edit
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => goto(`/cms/forms/${form.id}/submissions`)}>
												<Eye class="mr-2 h-4 w-4" />
												View Submissions
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => goto(`/cms/forms/${form.id}/analytics`)}>
												<BarChart3 class="mr-2 h-4 w-4" />
												Analytics
											</DropdownMenu.Item>
											<DropdownMenu.Separator />
											<DropdownMenu.Item onclick={() => handleToggleActive(form)}>
												{#if form.is_active}
													<XCircle class="mr-2 h-4 w-4" />
													Deactivate
												{:else}
													<CheckCircle class="mr-2 h-4 w-4" />
													Activate
												{/if}
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => handleDuplicate(form)}>
												<Copy class="mr-2 h-4 w-4" />
												Duplicate
											</DropdownMenu.Item>
											<DropdownMenu.Separator />
											<DropdownMenu.Item class="text-red-600" onclick={() => handleDelete(form)}>
												<Trash2 class="mr-2 h-4 w-4" />
												Delete
											</DropdownMenu.Item>
										</DropdownMenu.Content>
									</DropdownMenu.Root>
								</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>

				<!-- Pagination -->
				{#if meta.last_page > 1}
					<div class="flex items-center justify-between border-t px-4 py-3">
						<div class="text-muted-foreground text-sm">
							Showing {(meta.current_page - 1) * meta.per_page + 1} to {Math.min(
								meta.current_page * meta.per_page,
								meta.total
							)} of {meta.total} forms
						</div>
						<div class="flex gap-1">
							<Button
								variant="outline"
								size="sm"
								disabled={meta.current_page === 1}
								onclick={() => {
									meta.current_page--;
									loadForms();
								}}
							>
								Previous
							</Button>
							<Button
								variant="outline"
								size="sm"
								disabled={meta.current_page === meta.last_page}
								onclick={() => {
									meta.current_page++;
									loadForms();
								}}
							>
								Next
							</Button>
						</div>
					</div>
				{/if}
			{/if}
		</Card.Content>
	</Card.Root>
</div>
