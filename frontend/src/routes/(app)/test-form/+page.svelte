<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft, FormInput } from 'lucide-svelte';
	import { goto } from '$app/navigation';
	import DynamicForm from '$lib/components/dynamic-form/DynamicForm.svelte';
	import type { Module, Block, Field } from '$lib/api/modules';
	import { toast } from 'svelte-sonner';

	// Demo module with various field types
	const demoModule: Module = {
		id: 999,
		name: 'Test Form',
		singular_name: 'Test Entry',
		api_name: 'test_form',
		icon: 'form',
		description: 'A demonstration of all available field types',
		is_active: true,
		display_order: 0,
		settings: {
			has_import: false,
			has_export: false,
			has_mass_actions: false,
			has_comments: false,
			has_attachments: false,
			has_activity_log: false,
			has_custom_views: false,
			record_name_field: 'full_name',
			additional_settings: {}
		},
		created_at: new Date().toISOString(),
		updated_at: null,
		blocks: [
			{
				id: 1,
				name: 'Basic Information',
				description: 'Essential text and input fields',
				type: 'section',
				display_order: 0,
				settings: { columns: 2 },
				fields: [
					{
						id: 1,
						label: 'Full Name',
						api_name: 'full_name',
						type: 'text',
						description: null,
						help_text: 'Enter your full legal name',
						placeholder: 'John Doe',
						is_required: true,
						is_unique: false,
						is_searchable: true,
						is_filterable: true,
						is_sortable: true,
						is_mass_updatable: true,
						validation_rules: [],
						settings: { max_length: 100 },
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 0,
						width: 50,
						options: []
					},
					{
						id: 2,
						label: 'Email Address',
						api_name: 'email',
						type: 'email',
						description: null,
						help_text: 'Your primary email',
						placeholder: 'you@example.com',
						is_required: true,
						is_unique: true,
						is_searchable: true,
						is_filterable: true,
						is_sortable: true,
						is_mass_updatable: true,
						validation_rules: [],
						settings: {},
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 1,
						width: 50,
						options: []
					},
					{
						id: 3,
						label: 'Phone Number',
						api_name: 'phone',
						type: 'phone',
						description: null,
						help_text: null,
						placeholder: '+1 (555) 000-0000',
						is_required: false,
						is_unique: false,
						is_searchable: true,
						is_filterable: true,
						is_sortable: true,
						is_mass_updatable: true,
						validation_rules: [],
						settings: {},
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 2,
						width: 50,
						options: []
					},
					{
						id: 4,
						label: 'Website',
						api_name: 'website',
						type: 'url',
						description: null,
						help_text: null,
						placeholder: 'https://example.com',
						is_required: false,
						is_unique: false,
						is_searchable: true,
						is_filterable: false,
						is_sortable: false,
						is_mass_updatable: true,
						validation_rules: [],
						settings: {},
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 3,
						width: 50,
						options: []
					}
				]
			},
			{
				id: 2,
				name: 'Numbers & Currency',
				description: 'Numeric input fields',
				type: 'section',
				display_order: 1,
				settings: { columns: 2 },
				fields: [
					{
						id: 5,
						label: 'Quantity',
						api_name: 'quantity',
						type: 'number',
						description: null,
						help_text: 'Number of items',
						placeholder: '0',
						is_required: false,
						is_unique: false,
						is_searchable: false,
						is_filterable: true,
						is_sortable: true,
						is_mass_updatable: true,
						validation_rules: [],
						settings: { min_value: 0, max_value: 1000 },
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 0,
						width: 50,
						options: []
					},
					{
						id: 6,
						label: 'Price',
						api_name: 'price',
						type: 'currency',
						description: null,
						help_text: null,
						placeholder: '0.00',
						is_required: false,
						is_unique: false,
						is_searchable: false,
						is_filterable: true,
						is_sortable: true,
						is_mass_updatable: true,
						validation_rules: [],
						settings: { currency_code: 'USD', currency_symbol: '$', precision: 2 },
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 1,
						width: 50,
						options: []
					}
				]
			},
			{
				id: 3,
				name: 'Selection Fields',
				description: 'Dropdowns and choice fields',
				type: 'section',
				display_order: 2,
				settings: { columns: 2 },
				fields: [
					{
						id: 7,
						label: 'Status',
						api_name: 'status',
						type: 'select',
						description: null,
						help_text: 'Current status',
						placeholder: null,
						is_required: true,
						is_unique: false,
						is_searchable: true,
						is_filterable: true,
						is_sortable: true,
						is_mass_updatable: true,
						validation_rules: [],
						settings: {},
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: 'active',
						display_order: 0,
						width: 50,
						options: [
							{ id: 1, label: 'Active', value: 'active', color: '#22c55e', is_active: true, display_order: 0 },
							{ id: 2, label: 'Inactive', value: 'inactive', color: '#ef4444', is_active: true, display_order: 1 },
							{ id: 3, label: 'Pending', value: 'pending', color: '#f59e0b', is_active: true, display_order: 2 }
						]
					},
					{
						id: 8,
						label: 'Tags',
						api_name: 'tags',
						type: 'multiselect',
						description: null,
						help_text: 'Select multiple tags',
						placeholder: null,
						is_required: false,
						is_unique: false,
						is_searchable: true,
						is_filterable: true,
						is_sortable: false,
						is_mass_updatable: true,
						validation_rules: [],
						settings: {},
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 1,
						width: 50,
						options: [
							{ id: 4, label: 'Important', value: 'important', color: '#ef4444', is_active: true, display_order: 0 },
							{ id: 5, label: 'Urgent', value: 'urgent', color: '#f59e0b', is_active: true, display_order: 1 },
							{ id: 6, label: 'New', value: 'new', color: '#3b82f6', is_active: true, display_order: 2 },
							{ id: 7, label: 'Featured', value: 'featured', color: '#8b5cf6', is_active: true, display_order: 3 }
						]
					}
				]
			},
			{
				id: 4,
				name: 'Dates & Toggle',
				description: 'Date pickers and boolean fields',
				type: 'section',
				display_order: 3,
				settings: { columns: 2 },
				fields: [
					{
						id: 9,
						label: 'Start Date',
						api_name: 'start_date',
						type: 'date',
						description: null,
						help_text: null,
						placeholder: null,
						is_required: false,
						is_unique: false,
						is_searchable: false,
						is_filterable: true,
						is_sortable: true,
						is_mass_updatable: true,
						validation_rules: [],
						settings: {},
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 0,
						width: 50,
						options: []
					},
					{
						id: 10,
						label: 'Is Featured',
						api_name: 'is_featured',
						type: 'checkbox',
						description: null,
						help_text: 'Show on homepage',
						placeholder: null,
						is_required: false,
						is_unique: false,
						is_searchable: false,
						is_filterable: true,
						is_sortable: true,
						is_mass_updatable: true,
						validation_rules: [],
						settings: {},
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: 'false',
						display_order: 1,
						width: 50,
						options: []
					}
				]
			},
			{
				id: 5,
				name: 'Notes',
				description: 'Long text content',
				type: 'section',
				display_order: 4,
				settings: { columns: 1 },
				fields: [
					{
						id: 11,
						label: 'Description',
						api_name: 'description',
						type: 'textarea',
						description: null,
						help_text: 'Add any additional notes',
						placeholder: 'Enter description...',
						is_required: false,
						is_unique: false,
						is_searchable: true,
						is_filterable: false,
						is_sortable: false,
						is_mass_updatable: true,
						validation_rules: [],
						settings: { rows: 4 },
						conditional_visibility: null,
						field_dependency: null,
						formula_definition: null,
						default_value: null,
						display_order: 0,
						width: 100,
						options: []
					}
				]
			}
		]
	};

	async function handleSubmit(data: Record<string, any>) {
		console.log('Form submitted:', data);
		toast.success('Form submitted successfully!');
	}

	function handleCancel() {
		goto('/dashboard');
	}
</script>

<div class="container mx-auto py-8">
	<div class="mb-8">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/dashboard')}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<div class="flex items-center gap-2">
					<FormInput class="h-6 w-6 text-primary" />
					<h1 class="text-3xl font-bold">Test Form Demo</h1>
				</div>
				<p class="mt-1 text-muted-foreground">
					Demonstrates the DynamicForm component with various field types
				</p>
			</div>
		</div>
	</div>

	<div class="mx-auto max-w-4xl">
		<DynamicForm
			module={demoModule}
			mode="create"
			onSubmit={handleSubmit}
			onCancel={handleCancel}
		/>
	</div>
</div>
