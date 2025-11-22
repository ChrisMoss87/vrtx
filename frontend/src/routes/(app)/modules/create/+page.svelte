<script lang="ts">
	import { goto } from '$app/navigation';
	import { modulesApi, type CreateModuleRequest, type CreateBlockRequest, type CreateFieldRequest } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import Input from '$lib/components/ui/input/input.svelte';
	import Label  from '$lib/components/ui/label/label.svelte';
	import Textarea  from '$lib/components/ui/textarea/textarea.svelte';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Plus, Trash2, ArrowLeft, Save } from 'lucide-svelte';

	let moduleName = $state('');
	let singularName = $state('');
	let icon = $state('database');
	let description = $state('');
	let blocks = $state<Array<CreateBlockRequest & { _id: number }>>([]);
	let loading = $state(false);
	let error = $state<string | null>(null);
	let nextBlockId = $state(1);
	let nextFieldId = $state(1);

	function addBlock() {
		blocks = [
			...blocks,
			{
				_id: nextBlockId++,
				name: '',
				type: 'section',
				display_order: blocks.length,
				fields: []
			}
		];
	}

	function removeBlock(blockId: number) {
		blocks = blocks.filter((b) => b._id !== blockId);
	}

	function addField(blockIndex: number) {
		const block = blocks[blockIndex];
		if (!block) return;

		block.fields = [
			...(block.fields || []),
			{
				label: '',
				type: 'text',
				is_required: false,
				is_unique: false,
				is_searchable: true,
				is_filterable: true,
				is_sortable: true,
				display_order: block.fields?.length || 0,
				width: 100
			}
		];
		blocks = [...blocks];
	}

	function removeField(blockIndex: number, fieldIndex: number) {
		const block = blocks[blockIndex];
		if (!block || !block.fields) return;

		block.fields = block.fields.filter((_, i) => i !== fieldIndex);
		blocks = [...blocks];
	}

	async function handleSubmit() {
		error = null;

		// Validation
		if (!moduleName.trim()) {
			error = 'Module name is required';
			return;
		}
		if (!singularName.trim()) {
			error = 'Singular name is required';
			return;
		}
		if (blocks.length === 0) {
			error = 'At least one block is required';
			return;
		}

		for (const block of blocks) {
			if (!block.name.trim()) {
				error = 'All blocks must have a name';
				return;
			}
			if (!block.fields || block.fields.length === 0) {
				error = `Block "${block.name}" must have at least one field`;
				return;
			}
			for (const field of block.fields) {
				if (!field.label.trim()) {
					error = `All fields in block "${block.name}" must have a label`;
					return;
				}
			}
		}

		loading = true;

		try {
			const request: CreateModuleRequest = {
				name: moduleName,
				singular_name: singularName,
				icon: icon || 'database',
				description: description || undefined,
				blocks: blocks.map((block) => ({
					name: block.name,
					type: block.type,
					display_order: block.display_order,
					fields: block.fields
				}))
			};

			const module = await modulesApi.create(request);
			goto('/modules');
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to create module';
		} finally {
			loading = false;
		}
	}
</script>

<div class="container mx-auto py-8 max-w-5xl">
	<div class="flex items-center gap-4 mb-8">
		<Button variant="ghost" size="icon" onclick={() => goto('/modules')}>
			<ArrowLeft class="w-4 h-4" />
		</Button>
		<div>
			<h1 class="text-3xl font-bold">Create Module</h1>
			<p class="text-muted-foreground mt-2">Define a new custom module with fields</p>
		</div>
	</div>

	{#if error}
		<Card class="border-destructive mb-6">
			<CardContent class="pt-6">
				<p class="text-destructive" data-testid="error-message">{error}</p>
			</CardContent>
		</Card>
	{/if}

	<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }}>
		<!-- Module Basic Info -->
		<Card class="mb-6">
			<CardHeader>
				<CardTitle>Basic Information</CardTitle>
				<CardDescription>Define the module name and description</CardDescription>
			</CardHeader>
			<CardContent class="space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<div class="space-y-2">
						<Label for="moduleName">Module Name (Plural)</Label>
						<Input
							id="moduleName"
							data-testid="module-name"
							bind:value={moduleName}
							placeholder="e.g., Contacts, Products"
							required
						/>
					</div>
					<div class="space-y-2">
						<Label for="singularName">Singular Name</Label>
						<Input
							id="singularName"
							data-testid="singular-name"
							bind:value={singularName}
							placeholder="e.g., Contact, Product"
							required
						/>
					</div>
				</div>

				<div class="space-y-2">
					<Label for="icon">Icon</Label>
					<Input
						id="icon"
						data-testid="module-icon"
						bind:value={icon}
						placeholder="database"
					/>
				</div>

				<div class="space-y-2">
					<Label for="description">Description</Label>
					<Textarea
						id="description"
						data-testid="module-description"
						bind:value={description}
						placeholder="What is this module for?"
						rows={3}
					/>
				</div>
			</CardContent>
		</Card>

		<!-- Blocks & Fields -->
		<Card class="mb-6">
			<CardHeader>
				<div class="flex items-center justify-between">
					<div>
						<CardTitle>Blocks & Fields</CardTitle>
						<CardDescription>Organize your fields into logical groups</CardDescription>
					</div>
					<Button type="button" variant="outline" onclick={addBlock} data-testid="add-block">
						<Plus class="w-4 h-4 mr-2" />
						Add Block
					</Button>
				</div>
			</CardHeader>
			<CardContent class="space-y-6">
				{#if blocks.length === 0}
					<div class="text-center py-8 text-muted-foreground">
						<p>No blocks yet. Add your first block to get started.</p>
					</div>
				{:else}
					{#each blocks as block, blockIndex (block._id)}
						<Card data-testid="block-{blockIndex}">
							<CardHeader>
								<div class="flex items-center justify-between">
									<div class="flex-1 grid grid-cols-2 gap-4">
										<div class="space-y-2">
											<Label>Block Name</Label>
											<Input
												bind:value={block.name}
												placeholder="e.g., Basic Information"
												data-testid="block-name-{blockIndex}"
											/>
										</div>
										<div class="space-y-2">
											<Label>Block Type</Label>
											<select
												bind:value={block.type}
												class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
												data-testid="block-type-{blockIndex}"
											>
												<option value="section">Section</option>
												<option value="tab">Tab</option>
												<option value="accordion">Accordion</option>
												<option value="card">Card</option>
											</select>
										</div>
									</div>
									<Button
										type="button"
										variant="ghost"
										size="icon"
										onclick={() => removeBlock(block._id)}
										data-testid="remove-block-{blockIndex}"
									>
										<Trash2 class="w-4 h-4" />
									</Button>
								</div>
							</CardHeader>
							<CardContent class="space-y-4">
								<div class="flex items-center justify-between mb-4">
									<Label>Fields</Label>
									<Button
										type="button"
										variant="outline"
										size="sm"
										onclick={() => addField(blockIndex)}
										data-testid="add-field-{blockIndex}"
									>
										<Plus class="w-4 h-4 mr-2" />
										Add Field
									</Button>
								</div>

								{#if !block.fields || block.fields.length === 0}
									<p class="text-sm text-muted-foreground">No fields yet</p>
								{:else}
									<div class="space-y-3">
										{#each block.fields as field, fieldIndex (fieldIndex)}
											<div class="flex items-start gap-3 p-3 border rounded-lg" data-testid="field-{blockIndex}-{fieldIndex}">
												<div class="flex-1 grid grid-cols-3 gap-3">
													<div class="space-y-2">
														<Label class="text-xs">Label</Label>
														<Input
															bind:value={field.label}
															placeholder="Field label"
															data-testid="field-label-{blockIndex}-{fieldIndex}"
														/>
													</div>
													<div class="space-y-2">
														<Label class="text-xs">Type</Label>
														<select
															bind:value={field.type}
															class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
															data-testid="field-type-{blockIndex}-{fieldIndex}"
														>
															<option value="text">Text</option>
															<option value="textarea">Text Area</option>
															<option value="number">Number</option>
															<option value="email">Email</option>
															<option value="phone">Phone</option>
															<option value="url">URL</option>
															<option value="date">Date</option>
															<option value="datetime">Date Time</option>
															<option value="select">Select</option>
															<option value="checkbox">Checkbox</option>
															<option value="toggle">Toggle</option>
														</select>
													</div>
													<div class="flex items-end gap-2">
														<label class="flex items-center gap-2 text-sm">
															<input
																type="checkbox"
																bind:checked={field.is_required}
																data-testid="field-required-{blockIndex}-{fieldIndex}"
															/>
															Required
														</label>
														<label class="flex items-center gap-2 text-sm">
															<input
																type="checkbox"
																bind:checked={field.is_unique}
																data-testid="field-unique-{blockIndex}-{fieldIndex}"
															/>
															Unique
														</label>
													</div>
												</div>
												<Button
													type="button"
													variant="ghost"
													size="icon"
													onclick={() => removeField(blockIndex, fieldIndex)}
													data-testid="remove-field-{blockIndex}-{fieldIndex}"
												>
													<Trash2 class="w-4 h-4" />
												</Button>
											</div>
										{/each}
									</div>
								{/if}
							</CardContent>
						</Card>
					{/each}
				{/if}
			</CardContent>
		</Card>

		<!-- Submit -->
		<div class="flex justify-end gap-3">
			<Button type="button" variant="outline" onclick={() => goto('/modules')}>
				Cancel
			</Button>
			<Button type="submit" disabled={loading} data-testid="submit-module">
				{#if loading}
					<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
					Creating...
				{:else}
					<Save class="w-4 h-4 mr-2" />
					Create Module
				{/if}
			</Button>
		</div>
	</form>
</div>
