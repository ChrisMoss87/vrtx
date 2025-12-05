<script lang="ts">
	import { FieldRenderer } from '$lib/components/dynamic-form';
	import {
		Card,
		CardContent,
		CardDescription,
		CardHeader,
		CardTitle
	} from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import { Separator } from '$lib/components/ui/separator';
	import * as Tabs from '$lib/components/ui/tabs';
	import type { Field, FieldOption } from '$lib/api/modules';

	// Form values state
	let formValues = $state<Record<string, any>>({});
	let errors = $state<Record<string, string>>({});

	function handleChange(fieldName: string) {
		return (value: any) => {
			formValues[fieldName] = value;
			// Clear error when value changes
			if (errors[fieldName]) {
				delete errors[fieldName];
			}
		};
	}

	function logFormValues() {
		console.log('Form Values:', JSON.stringify(formValues, null, 2));
	}

	function resetForm() {
		formValues = {};
		errors = {};
	}

	// Helper to create a field definition
	function createField(
		id: number,
		label: string,
		apiName: string,
		type: string,
		options: Partial<Field> = {}
	): Field {
		return {
			id,
			label,
			api_name: apiName,
			type,
			description: options.description ?? null,
			help_text: options.help_text ?? null,
			placeholder: options.placeholder ?? null,
			is_required: options.is_required ?? false,
			is_unique: false,
			is_searchable: true,
			is_filterable: true,
			is_sortable: true,
			is_mass_updatable: true,
			validation_rules: [],
			settings: options.settings ?? { additional_settings: {} },
			conditional_visibility: null,
			field_dependency: null,
			formula_definition: options.formula_definition ?? null,
			default_value: options.default_value ?? null,
			display_order: id,
			width: 100,
			options: options.options ?? []
		};
	}

	// Sample options for select/multiselect/radio fields
	const statusOptions: FieldOption[] = [
		{
			id: 1,
			label: 'Active',
			value: 'active',
			color: '#22c55e',
			is_active: true,
			display_order: 1
		},
		{
			id: 2,
			label: 'Pending',
			value: 'pending',
			color: '#eab308',
			is_active: true,
			display_order: 2
		},
		{
			id: 3,
			label: 'Inactive',
			value: 'inactive',
			color: '#ef4444',
			is_active: true,
			display_order: 3
		}
	];

	const priorityOptions: FieldOption[] = [
		{ id: 4, label: 'Low', value: 'low', color: '#3b82f6', is_active: true, display_order: 1 },
		{
			id: 5,
			label: 'Medium',
			value: 'medium',
			color: '#f59e0b',
			is_active: true,
			display_order: 2
		},
		{ id: 6, label: 'High', value: 'high', color: '#ef4444', is_active: true, display_order: 3 },
		{
			id: 7,
			label: 'Critical',
			value: 'critical',
			color: '#7c3aed',
			is_active: true,
			display_order: 4
		}
	];

	const categoryOptions: FieldOption[] = [
		{
			id: 8,
			label: 'Technology',
			value: 'technology',
			color: null,
			is_active: true,
			display_order: 1
		},
		{ id: 9, label: 'Finance', value: 'finance', color: null, is_active: true, display_order: 2 },
		{
			id: 10,
			label: 'Healthcare',
			value: 'healthcare',
			color: null,
			is_active: true,
			display_order: 3
		},
		{ id: 11, label: 'Retail', value: 'retail', color: null, is_active: true, display_order: 4 },
		{
			id: 12,
			label: 'Education',
			value: 'education',
			color: null,
			is_active: true,
			display_order: 5
		}
	];

	const progressOptions: FieldOption[] = [
		{
			id: 13,
			label: 'Not Started',
			value: '0',
			color: '#6b7280',
			is_active: true,
			display_order: 1
		},
		{
			id: 14,
			label: 'In Progress',
			value: '50',
			color: '#3b82f6',
			is_active: true,
			display_order: 2
		},
		{ id: 15, label: 'Review', value: '75', color: '#f59e0b', is_active: true, display_order: 3 },
		{ id: 16, label: 'Complete', value: '100', color: '#22c55e', is_active: true, display_order: 4 }
	];

	// Define all field types organized by category
	const textFields: Field[] = [
		createField(1, 'Full Name', 'full_name', 'text', {
			placeholder: 'Enter your full name',
			help_text: 'Your legal name as it appears on official documents',
			is_required: true,
			settings: { max_length: 100, additional_settings: {} }
		}),
		createField(2, 'Bio', 'bio', 'textarea', {
			placeholder: 'Tell us about yourself...',
			help_text: 'A brief description (max 500 characters)',
			settings: { max_length: 500, additional_settings: {} }
		}),
		createField(3, 'Email Address', 'email', 'email', {
			placeholder: 'you@example.com',
			is_required: true
		}),
		createField(4, 'Phone Number', 'phone', 'phone', {
			placeholder: '+1 (555) 123-4567',
			help_text: 'Include country code for international numbers'
		}),
		createField(5, 'Website', 'website', 'url', {
			placeholder: 'https://example.com'
		}),
		createField(6, 'Notes', 'notes', 'rich_text', {
			placeholder: 'Enter detailed notes with formatting...',
			help_text: 'Supports bold, italic, lists, and more'
		})
	];

	const numericFields: Field[] = [
		createField(10, 'Age', 'age', 'number', {
			placeholder: '25',
			settings: { min_value: 0, max_value: 150, additional_settings: {} }
		}),
		createField(11, 'Price', 'price', 'decimal', {
			placeholder: '99.99',
			settings: { precision: 2, min_value: 0, additional_settings: {} }
		}),
		createField(12, 'Annual Revenue', 'revenue', 'currency', {
			placeholder: '1000000',
			settings: { currency_code: 'USD', precision: 2, additional_settings: {} }
		}),
		createField(13, 'Discount Rate', 'discount_rate', 'percent', {
			placeholder: '15',
			settings: { precision: 1, min_value: 0, max_value: 100, additional_settings: {} }
		}),
		createField(14, 'Rating', 'star_rating', 'rating', {
			help_text: 'Rate from 1 to 5 stars'
		})
	];

	const dateTimeFields: Field[] = [
		createField(20, 'Birth Date', 'birth_date', 'date', {
			help_text: 'Your date of birth'
		}),
		createField(21, 'Meeting Time', 'meeting_time', 'datetime', {
			help_text: 'Select date and time for the meeting'
		}),
		createField(22, 'Preferred Time', 'preferred_time', 'time', {
			help_text: 'Your preferred contact time'
		})
	];

	const selectionFields: Field[] = [
		createField(30, 'Status', 'status', 'select', {
			options: statusOptions,
			is_required: true,
			help_text: 'Current status of the item'
		}),
		createField(31, 'Categories', 'categories', 'multiselect', {
			options: categoryOptions,
			help_text: 'Select all applicable categories'
		}),
		createField(32, 'Priority Level', 'priority', 'radio', {
			options: priorityOptions,
			is_required: true,
			help_text: 'Select the priority level'
		}),
		createField(33, 'Project Progress', 'progress', 'progress_mapper', {
			options: progressOptions,
			help_text: 'Current progress stage'
		})
	];

	const booleanFields: Field[] = [
		createField(40, 'Subscribe to Newsletter', 'subscribe', 'checkbox', {
			help_text: 'Receive weekly updates and news'
		}),
		createField(41, 'Enable Notifications', 'notifications', 'toggle', {
			help_text: 'Get notified about important updates'
		})
	];

	const specialFields: Field[] = [
		createField(50, 'Brand Color', 'brand_color', 'color', {
			help_text: 'Select your brand color',
			default_value: '#3b82f6'
		}),
		createField(51, 'Total (Formula)', 'total', 'formula', {
			help_text: 'Automatically calculated based on other fields',
			formula_definition: {
				formula: 'price * quantity',
				formula_type: 'calculation',
				return_type: 'currency',
				dependencies: ['price', 'quantity'],
				recalculate_on: ['price', 'quantity'],
				additional_settings: {}
			}
		}),
		createField(52, 'Record ID', 'record_id', 'auto_number', {
			help_text: 'Automatically generated unique ID',
			settings: { additional_settings: { prefix: 'REC-', padding: 6 } }
		}),
		createField(53, 'Customer Signature', 'signature', 'signature', {
			help_text: 'Sign using your mouse or touch screen'
		})
	];

	const fileFields: Field[] = [
		createField(60, 'Resume', 'resume', 'file', {
			help_text: 'Upload your resume (PDF, DOC, DOCX)',
			settings: {
				allowed_file_types: ['.pdf', '.doc', '.docx'],
				max_file_size: 5242880,
				additional_settings: {}
			}
		}),
		createField(61, 'Profile Picture', 'profile_picture', 'image', {
			help_text: 'Upload a profile picture (JPG, PNG, max 2MB)',
			settings: {
				allowed_file_types: ['.jpg', '.jpeg', '.png', '.gif'],
				max_file_size: 2097152,
				additional_settings: {}
			}
		})
	];

	const lookupFields: Field[] = [
		createField(70, 'Assigned User', 'assigned_user', 'lookup', {
			help_text: 'Select a user from the system',
			settings: {
				related_module_id: 1,
				related_module_name: 'Users',
				display_field: 'name',
				search_fields: ['name', 'email'],
				relationship_type: 'many_to_one',
				allow_create: false,
				additional_settings: {}
			}
		})
	];

	// All categories
	const fieldCategories = [
		{
			id: 'text',
			label: 'Text Fields',
			fields: textFields,
			description: 'Text input, textarea, email, phone, URL, and rich text'
		},
		{
			id: 'numeric',
			label: 'Numeric Fields',
			fields: numericFields,
			description: 'Numbers, decimals, currency, percentage, and ratings'
		},
		{
			id: 'datetime',
			label: 'Date & Time',
			fields: dateTimeFields,
			description: 'Date, datetime, and time pickers'
		},
		{
			id: 'selection',
			label: 'Selection Fields',
			fields: selectionFields,
			description: 'Dropdowns, multi-select, radio buttons, and progress'
		},
		{
			id: 'boolean',
			label: 'Boolean Fields',
			fields: booleanFields,
			description: 'Checkboxes and toggles'
		},
		{
			id: 'special',
			label: 'Special Fields',
			fields: specialFields,
			description: 'Colors, formulas, auto-numbers, and signatures'
		},
		{ id: 'file', label: 'File Fields', fields: fileFields, description: 'File and image uploads' },
		{
			id: 'lookup',
			label: 'Lookup Fields',
			fields: lookupFields,
			description: 'Related record lookups'
		}
	];
</script>

<div class="container mx-auto space-y-8 py-8">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-3xl font-bold">Field Types Demo</h1>
			<p class="mt-2 text-muted-foreground">
				Complete showcase of all available field types in the dynamic form system
			</p>
		</div>
		<div class="flex gap-2">
			<Button variant="outline" onclick={resetForm}>Reset All</Button>
			<Button onclick={logFormValues}>Log Values</Button>
		</div>
	</div>

	<div class="flex flex-wrap gap-2">
		{#each fieldCategories as category}
			<Badge variant="secondary">{category.fields.length} {category.label}</Badge>
		{/each}
		<Badge variant="default"
			>{fieldCategories.reduce((acc, c) => acc + c.fields.length, 0)} Total Fields</Badge
		>
	</div>

	<Tabs.Root value="text" class="w-full">
		<Tabs.List class="flex h-auto flex-wrap gap-1">
			{#each fieldCategories as category}
				<Tabs.Trigger value={category.id} class="text-sm">
					{category.label}
					<Badge variant="outline" class="ml-2 text-xs">{category.fields.length}</Badge>
				</Tabs.Trigger>
			{/each}
		</Tabs.List>

		{#each fieldCategories as category}
			<Tabs.Content value={category.id} class="mt-6">
				<Card>
					<CardHeader>
						<CardTitle>{category.label}</CardTitle>
						<CardDescription>{category.description}</CardDescription>
					</CardHeader>
					<CardContent class="space-y-6">
						<div class="grid gap-6 md:grid-cols-2">
							{#each category.fields as field}
								<div class="space-y-2 rounded-lg border bg-card p-4">
									<div class="mb-3 flex items-center gap-2">
										<Badge variant="outline" class="font-mono text-xs">{field.type}</Badge>
										{#if field.is_required}
											<Badge variant="destructive" class="text-xs">Required</Badge>
										{/if}
									</div>
									<FieldRenderer
										{field}
										value={formValues[field.api_name]}
										error={errors[field.api_name]}
										isReadonly={false}
										onchange={handleChange(field.api_name)}
									/>
								</div>
							{/each}
						</div>
					</CardContent>
				</Card>
			</Tabs.Content>
		{/each}
	</Tabs.Root>

	<Separator />

	<!-- Current Values Display -->
	<Card>
		<CardHeader>
			<CardTitle>Current Form Values</CardTitle>
			<CardDescription>Real-time view of all field values as you interact with them</CardDescription
			>
		</CardHeader>
		<CardContent>
			{#if Object.keys(formValues).length === 0}
				<p class="text-sm text-muted-foreground">
					No values entered yet. Start filling out the fields above.
				</p>
			{:else}
				<pre
					class="max-h-96 overflow-x-auto rounded-md bg-muted p-4 text-xs whitespace-pre-wrap">{JSON.stringify(
						formValues,
						null,
						2
					)}</pre>
			{/if}
		</CardContent>
	</Card>

	<!-- Field Type Reference -->
	<Card>
		<CardHeader>
			<CardTitle>Field Type Reference</CardTitle>
			<CardDescription>Quick reference for all supported field types</CardDescription>
		</CardHeader>
		<CardContent>
			<div class="grid gap-4 text-sm md:grid-cols-4">
				<div>
					<h4 class="mb-2 font-semibold">Text Input</h4>
					<ul class="space-y-1 text-muted-foreground">
						<li><code class="rounded bg-muted px-1 text-xs">text</code> - Single line text</li>
						<li><code class="rounded bg-muted px-1 text-xs">textarea</code> - Multi-line text</li>
						<li><code class="rounded bg-muted px-1 text-xs">email</code> - Email address</li>
						<li><code class="rounded bg-muted px-1 text-xs">phone</code> - Phone number</li>
						<li><code class="rounded bg-muted px-1 text-xs">url</code> - Website URL</li>
						<li><code class="rounded bg-muted px-1 text-xs">rich_text</code> - Rich text editor</li>
					</ul>
				</div>
				<div>
					<h4 class="mb-2 font-semibold">Numeric</h4>
					<ul class="space-y-1 text-muted-foreground">
						<li><code class="rounded bg-muted px-1 text-xs">number</code> - Integer</li>
						<li><code class="rounded bg-muted px-1 text-xs">decimal</code> - Decimal number</li>
						<li><code class="rounded bg-muted px-1 text-xs">currency</code> - Money value</li>
						<li><code class="rounded bg-muted px-1 text-xs">percent</code> - Percentage</li>
						<li><code class="rounded bg-muted px-1 text-xs">rating</code> - Star rating</li>
					</ul>
				</div>
				<div>
					<h4 class="mb-2 font-semibold">Selection</h4>
					<ul class="space-y-1 text-muted-foreground">
						<li><code class="rounded bg-muted px-1 text-xs">select</code> - Dropdown</li>
						<li><code class="rounded bg-muted px-1 text-xs">multiselect</code> - Multi-select</li>
						<li><code class="rounded bg-muted px-1 text-xs">radio</code> - Radio buttons</li>
						<li><code class="rounded bg-muted px-1 text-xs">checkbox</code> - Checkbox</li>
						<li><code class="rounded bg-muted px-1 text-xs">toggle</code> - Toggle switch</li>
						<li><code class="rounded bg-muted px-1 text-xs">progress_mapper</code> - Progress</li>
					</ul>
				</div>
				<div>
					<h4 class="mb-2 font-semibold">Special</h4>
					<ul class="space-y-1 text-muted-foreground">
						<li><code class="rounded bg-muted px-1 text-xs">date</code> - Date picker</li>
						<li><code class="rounded bg-muted px-1 text-xs">datetime</code> - Date & time</li>
						<li><code class="rounded bg-muted px-1 text-xs">time</code> - Time picker</li>
						<li><code class="rounded bg-muted px-1 text-xs">color</code> - Color picker</li>
						<li><code class="rounded bg-muted px-1 text-xs">file</code> - File upload</li>
						<li><code class="rounded bg-muted px-1 text-xs">image</code> - Image upload</li>
						<li><code class="rounded bg-muted px-1 text-xs">signature</code> - Signature pad</li>
						<li><code class="rounded bg-muted px-1 text-xs">lookup</code> - Related record</li>
						<li><code class="rounded bg-muted px-1 text-xs">formula</code> - Calculated</li>
						<li><code class="rounded bg-muted px-1 text-xs">auto_number</code> - Auto ID</li>
					</ul>
				</div>
			</div>
		</CardContent>
	</Card>
</div>
