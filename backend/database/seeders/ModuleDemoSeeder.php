<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Field;
use App\Models\FieldOption;
use App\Models\Module;
use App\Models\ModuleRecord;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Seeds demo modules with sample data for CRM functionality.
 * Creates Organizations, Contacts, Deals, and Invoices modules.
 */
class ModuleDemoSeeder extends Seeder
{
    private $faker;

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    public function run(): void
    {
        $this->command->info('Cleaning up existing demo modules...');

        // Clean up existing modules (delete in order due to foreign key constraints)
        // Include soft-deleted records with withTrashed()
        $moduleApiNames = ['organizations', 'contacts', 'deals', 'invoices'];
        foreach ($moduleApiNames as $apiName) {
            $module = Module::withTrashed()->where('api_name', $apiName)->first();
            if ($module) {
                // Delete records first
                ModuleRecord::where('module_id', $module->id)->delete();
                // Delete field options
                $fieldIds = Field::where('module_id', $module->id)->pluck('id');
                FieldOption::whereIn('field_id', $fieldIds)->delete();
                // Delete fields
                Field::where('module_id', $module->id)->delete();
                // Delete blocks
                Block::where('module_id', $module->id)->delete();
                // Force delete module (required for SoftDeletes)
                $module->forceDelete();
            }
        }

        $this->command->info('Creating demo modules...');

        // Create modules
        $organizations = $this->createOrganizationsModule();
        $contacts = $this->createContactsModule();
        $deals = $this->createDealsModule();
        $invoices = $this->createInvoicesModule();

        $this->command->info('Seeding sample data...');

        // Seed sample data
        $orgIds = $this->seedOrganizations($organizations, 25);
        $contactIds = $this->seedContacts($contacts, 50, $orgIds);
        $dealIds = $this->seedDeals($deals, 30, $orgIds, $contactIds);
        $this->seedInvoices($invoices, 40, $orgIds, $dealIds);

        $this->command->info('Demo modules and data created successfully!');
    }

    private function createOrganizationsModule(): Module
    {
        $module = Module::create([
            'name' => 'Organizations',
            'singular_name' => 'Organization',
            'api_name' => 'organizations',
            'icon' => 'building-2',
            'description' => 'Manage company and organization records',
            'is_active' => true,
            'display_order' => 1,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'name',
                'additional_settings' => [],
            ],
        ]);

        // Basic Information Block
        $basicBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Basic Information',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $this->createField($basicBlock, 'name', 'Organization Name', 'text', 1, true, true, true, true);
        $industryField = $this->createField($basicBlock, 'industry', 'Industry', 'select', 2, false, true, false, true);
        $this->createFieldOptions($industryField, [
            'Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing',
            'Education', 'Real Estate', 'Consulting', 'Media', 'Other'
        ]);
        $sizeField = $this->createField($basicBlock, 'company_size', 'Company Size', 'select', 3, false, true, false, true);
        $this->createFieldOptions($sizeField, ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+']);
        $this->createField($basicBlock, 'website', 'Website', 'url', 4, false, true, false, true);
        $this->createField($basicBlock, 'annual_revenue', 'Annual Revenue', 'currency', 5, false, true, false, true);

        // Contact Information Block
        $contactBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Contact Information',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $this->createField($contactBlock, 'phone', 'Phone', 'phone', 1, false, true, false, true);
        $this->createField($contactBlock, 'email', 'Email', 'email', 2, false, true, true, true);
        $this->createField($contactBlock, 'address', 'Address', 'textarea', 3, false, false, false, false);
        $this->createField($contactBlock, 'city', 'City', 'text', 4, false, true, true, true);
        $this->createField($contactBlock, 'country', 'Country', 'text', 5, false, true, true, true);

        // Additional Details Block
        $detailsBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Additional Details',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 1, false, true, false, true);
        $this->createFieldOptions($statusField, ['Active', 'Inactive', 'Prospect', 'Partner']);
        $this->createField($detailsBlock, 'description', 'Description', 'textarea', 2, false, false, false, false);

        return $module;
    }

    private function createContactsModule(): Module
    {
        $module = Module::create([
            'name' => 'Contacts',
            'singular_name' => 'Contact',
            'api_name' => 'contacts',
            'icon' => 'users',
            'description' => 'Manage contact and people records',
            'is_active' => true,
            'display_order' => 2,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'first_name',
                'additional_settings' => [],
            ],
        ]);

        // Personal Information Block
        $personalBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Personal Information',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $this->createField($personalBlock, 'first_name', 'First Name', 'text', 1, true, true, true, true);
        $this->createField($personalBlock, 'last_name', 'Last Name', 'text', 2, true, true, true, true);
        $this->createField($personalBlock, 'email', 'Email', 'email', 3, true, true, true, true);
        $this->createField($personalBlock, 'phone', 'Phone', 'phone', 4, false, true, false, true);
        $this->createField($personalBlock, 'mobile', 'Mobile', 'phone', 5, false, true, false, true);

        // Work Information Block
        $workBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Work Information',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $this->createField($workBlock, 'job_title', 'Job Title', 'text', 1, false, true, true, true);
        $this->createField($workBlock, 'department', 'Department', 'text', 2, false, true, true, true);
        $this->createField($workBlock, 'organization_name', 'Organization', 'text', 3, false, true, true, true);

        // Additional Details Block
        $detailsBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Additional Details',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $leadSourceField = $this->createField($detailsBlock, 'lead_source', 'Lead Source', 'select', 1, false, true, false, true);
        $this->createFieldOptions($leadSourceField, ['Website', 'Referral', 'Social Media', 'Trade Show', 'Cold Call', 'Advertisement', 'Other']);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 2, false, true, false, true);
        $this->createFieldOptions($statusField, ['Lead', 'Qualified', 'Customer', 'Inactive']);
        $this->createField($detailsBlock, 'do_not_call', 'Do Not Call', 'checkbox', 3, false, true, false, true);
        $this->createField($detailsBlock, 'notes', 'Notes', 'textarea', 4, false, false, false, false);

        return $module;
    }

    private function createDealsModule(): Module
    {
        $module = Module::create([
            'name' => 'Deals',
            'singular_name' => 'Deal',
            'api_name' => 'deals',
            'icon' => 'handshake',
            'description' => 'Track sales opportunities and deals',
            'is_active' => true,
            'display_order' => 3,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'deal_name',
                'additional_settings' => [],
            ],
        ]);

        // Deal Information Block
        $dealBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Deal Information',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $this->createField($dealBlock, 'deal_name', 'Deal Name', 'text', 1, true, true, true, true);
        $this->createField($dealBlock, 'organization_name', 'Organization', 'text', 2, false, true, true, true);
        $this->createField($dealBlock, 'contact_name', 'Primary Contact', 'text', 3, false, true, true, true);
        $this->createField($dealBlock, 'amount', 'Deal Amount', 'currency', 4, false, true, false, true);
        $this->createField($dealBlock, 'probability', 'Probability (%)', 'percent', 5, false, true, false, true);

        // Stage & Timeline Block
        $stageBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Stage & Timeline',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $stageField = $this->createField($stageBlock, 'stage', 'Stage', 'select', 1, true, true, false, true);
        $this->createFieldOptions($stageField, ['Qualification', 'Needs Analysis', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost']);
        $typeField = $this->createField($stageBlock, 'deal_type', 'Deal Type', 'select', 2, false, true, false, true);
        $this->createFieldOptions($typeField, ['New Business', 'Existing Business', 'Renewal', 'Expansion']);
        $this->createField($stageBlock, 'expected_close_date', 'Expected Close Date', 'date', 3, false, true, false, true);
        $this->createField($stageBlock, 'actual_close_date', 'Actual Close Date', 'date', 4, false, true, false, true);

        // Additional Information Block
        $additionalBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Additional Information',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $leadSourceField = $this->createField($additionalBlock, 'lead_source', 'Lead Source', 'select', 1, false, true, false, true);
        $this->createFieldOptions($leadSourceField, ['Website', 'Referral', 'Partner', 'Outbound', 'Event', 'Other']);
        $this->createField($additionalBlock, 'description', 'Description', 'textarea', 2, false, false, false, false);
        $this->createField($additionalBlock, 'next_step', 'Next Step', 'text', 3, false, false, false, false);

        return $module;
    }

    private function createInvoicesModule(): Module
    {
        $module = Module::create([
            'name' => 'Invoices',
            'singular_name' => 'Invoice',
            'api_name' => 'invoices',
            'icon' => 'file-text',
            'description' => 'Manage invoices and billing',
            'is_active' => true,
            'display_order' => 4,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'invoice_number',
                'additional_settings' => [],
            ],
        ]);

        // Invoice Details Block
        $detailsBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Invoice Details',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $this->createField($detailsBlock, 'invoice_number', 'Invoice Number', 'text', 1, true, true, true, true);
        $this->createField($detailsBlock, 'organization_name', 'Organization', 'text', 2, false, true, true, true);
        $this->createField($detailsBlock, 'deal_name', 'Related Deal', 'text', 3, false, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 4, true, true, false, true);
        $this->createFieldOptions($statusField, ['Draft', 'Sent', 'Paid', 'Partially Paid', 'Overdue', 'Cancelled']);

        // Financial Details Block
        $financialBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Financial Details',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $this->createField($financialBlock, 'subtotal', 'Subtotal', 'currency', 1, false, true, false, true);
        $this->createField($financialBlock, 'tax_rate', 'Tax Rate (%)', 'percent', 2, false, true, false, true);
        $this->createField($financialBlock, 'tax_amount', 'Tax Amount', 'currency', 3, false, true, false, true);
        $this->createField($financialBlock, 'discount', 'Discount', 'currency', 4, false, true, false, true);
        $this->createField($financialBlock, 'total', 'Total Amount', 'currency', 5, true, true, false, true);

        // Dates Block
        $datesBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Dates',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $this->createField($datesBlock, 'invoice_date', 'Invoice Date', 'date', 1, true, true, false, true);
        $this->createField($datesBlock, 'due_date', 'Due Date', 'date', 2, true, true, false, true);
        $this->createField($datesBlock, 'paid_date', 'Paid Date', 'date', 3, false, true, false, true);

        // Notes Block
        $notesBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Notes',
            'type' => 'section',
            'display_order' => 4,
            'settings' => [],
        ]);

        $this->createField($notesBlock, 'notes', 'Notes', 'textarea', 1, false, false, false, false);
        $this->createField($notesBlock, 'terms', 'Terms & Conditions', 'textarea', 2, false, false, false, false);

        return $module;
    }

    private function createField(
        Block $block,
        string $apiName,
        string $label,
        string $type,
        int $order,
        bool $required = false,
        bool $filterable = true,
        bool $searchable = true,
        bool $sortable = true
    ): Field {
        return Field::create([
            'module_id' => $block->module_id,
            'block_id' => $block->id,
            'label' => $label,
            'api_name' => $apiName,
            'type' => $type,
            'description' => null,
            'help_text' => null,
            'placeholder' => null,
            'is_required' => $required,
            'is_unique' => false,
            'is_searchable' => $searchable,
            'is_filterable' => $filterable,
            'is_sortable' => $sortable,
            'default_value' => null,
            'display_order' => $order,
            'width' => 100,
            'validation_rules' => [],
            'settings' => ['additional_settings' => []],
            'conditional_visibility' => null,
            'field_dependency' => null,
            'formula_definition' => null,
        ]);
    }

    private function createFieldOptions(Field $field, array $options): void
    {
        foreach ($options as $index => $option) {
            FieldOption::create([
                'field_id' => $field->id,
                'label' => $option,
                'value' => strtolower(str_replace([' ', '&'], ['_', ''], $option)),
                'is_active' => true,
                'display_order' => $index + 1,
            ]);
        }
    }

    private function seedOrganizations(Module $module, int $count): array
    {
        $ids = [];
        $industries = ['technology', 'healthcare', 'finance', 'retail', 'manufacturing', 'education', 'real_estate', 'consulting', 'media', 'other'];
        $sizes = ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'];
        $statuses = ['active', 'inactive', 'prospect', 'partner'];

        for ($i = 0; $i < $count; $i++) {
            $record = ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'name' => $this->faker->company,
                    'industry' => $this->faker->randomElement($industries),
                    'company_size' => $this->faker->randomElement($sizes),
                    'website' => $this->faker->url,
                    'annual_revenue' => $this->faker->numberBetween(100000, 50000000),
                    'phone' => $this->faker->phoneNumber,
                    'email' => $this->faker->companyEmail,
                    'address' => $this->faker->streetAddress,
                    'city' => $this->faker->city,
                    'country' => $this->faker->country,
                    'status' => $this->faker->randomElement($statuses),
                    'description' => $this->faker->paragraph,
                ],
                'created_by' => 1,
            ]);
            $ids[] = $record->id;
        }

        $this->command->info("Created {$count} organizations");
        return $ids;
    }

    private function seedContacts(Module $module, int $count, array $orgIds): array
    {
        $ids = [];
        $leadSources = ['website', 'referral', 'social_media', 'trade_show', 'cold_call', 'advertisement', 'other'];
        $statuses = ['lead', 'qualified', 'customer', 'inactive'];
        $departments = ['Sales', 'Marketing', 'Engineering', 'Finance', 'HR', 'Operations', 'Executive'];
        $titles = ['CEO', 'CTO', 'CFO', 'VP of Sales', 'Director', 'Manager', 'Senior Engineer', 'Analyst', 'Consultant'];

        // Get organization names for lookup
        $organizations = ModuleRecord::whereIn('id', $orgIds)->get()->pluck('data', 'id');

        for ($i = 0; $i < $count; $i++) {
            $orgId = $this->faker->randomElement($orgIds);
            $orgData = $organizations[$orgId] ?? [];
            $orgName = $orgData['name'] ?? '';

            $record = ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'first_name' => $this->faker->firstName,
                    'last_name' => $this->faker->lastName,
                    'email' => $this->faker->unique()->safeEmail,
                    'phone' => $this->faker->phoneNumber,
                    'mobile' => $this->faker->phoneNumber,
                    'job_title' => $this->faker->randomElement($titles),
                    'department' => $this->faker->randomElement($departments),
                    'organization_name' => $orgName,
                    'lead_source' => $this->faker->randomElement($leadSources),
                    'status' => $this->faker->randomElement($statuses),
                    'do_not_call' => $this->faker->boolean(10),
                    'notes' => $this->faker->optional()->paragraph,
                ],
                'created_by' => 1,
            ]);
            $ids[] = $record->id;
        }

        $this->command->info("Created {$count} contacts");
        return $ids;
    }

    private function seedDeals(Module $module, int $count, array $orgIds, array $contactIds): array
    {
        $ids = [];
        $stages = ['qualification', 'needs_analysis', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
        $dealTypes = ['new_business', 'existing_business', 'renewal', 'expansion'];
        $leadSources = ['website', 'referral', 'partner', 'outbound', 'event', 'other'];

        // Get organization and contact names
        $organizations = ModuleRecord::whereIn('id', $orgIds)->get()->pluck('data', 'id');
        $contacts = ModuleRecord::whereIn('id', $contactIds)->get()->pluck('data', 'id');

        for ($i = 0; $i < $count; $i++) {
            $orgId = $this->faker->randomElement($orgIds);
            $contactId = $this->faker->randomElement($contactIds);
            $orgData = $organizations[$orgId] ?? [];
            $contactData = $contacts[$contactId] ?? [];

            $stage = $this->faker->randomElement($stages);
            $amount = $this->faker->numberBetween(5000, 500000);
            $probability = match($stage) {
                'qualification' => $this->faker->numberBetween(10, 25),
                'needs_analysis' => $this->faker->numberBetween(25, 50),
                'proposal' => $this->faker->numberBetween(50, 75),
                'negotiation' => $this->faker->numberBetween(75, 90),
                'closed_won' => 100,
                'closed_lost' => 0,
                default => 50,
            };

            $expectedClose = $this->faker->dateTimeBetween('-1 month', '+3 months');
            $actualClose = in_array($stage, ['closed_won', 'closed_lost'])
                ? $this->faker->dateTimeBetween('-2 months', 'now')
                : null;

            $record = ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'deal_name' => $orgData['name'] . ' - ' . $this->faker->words(3, true),
                    'organization_name' => $orgData['name'] ?? '',
                    'contact_name' => ($contactData['first_name'] ?? '') . ' ' . ($contactData['last_name'] ?? ''),
                    'amount' => $amount,
                    'probability' => $probability,
                    'stage' => $stage,
                    'deal_type' => $this->faker->randomElement($dealTypes),
                    'expected_close_date' => $expectedClose->format('Y-m-d'),
                    'actual_close_date' => $actualClose?->format('Y-m-d'),
                    'lead_source' => $this->faker->randomElement($leadSources),
                    'description' => $this->faker->paragraph,
                    'next_step' => $this->faker->optional()->sentence,
                ],
                'created_by' => 1,
            ]);
            $ids[] = $record->id;
        }

        $this->command->info("Created {$count} deals");
        return $ids;
    }

    private function seedInvoices(Module $module, int $count, array $orgIds, array $dealIds): void
    {
        $statuses = ['draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled'];

        // Get organization and deal names
        $organizations = ModuleRecord::whereIn('id', $orgIds)->get()->pluck('data', 'id');
        $deals = ModuleRecord::whereIn('id', $dealIds)->get()->pluck('data', 'id');

        for ($i = 0; $i < $count; $i++) {
            $orgId = $this->faker->randomElement($orgIds);
            $dealId = $this->faker->randomElement($dealIds);
            $orgData = $organizations[$orgId] ?? [];
            $dealData = $deals[$dealId] ?? [];

            $status = $this->faker->randomElement($statuses);
            $subtotal = $this->faker->numberBetween(1000, 50000);
            $taxRate = $this->faker->randomElement([0, 5, 10, 15, 20]);
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $discount = $this->faker->boolean(30) ? $this->faker->numberBetween(100, 1000) : 0;
            $total = $subtotal + $taxAmount - $discount;

            $invoiceDate = $this->faker->dateTimeBetween('-3 months', 'now');
            $dueDate = (clone $invoiceDate)->modify('+30 days');
            $paidDate = $status === 'paid' ? $this->faker->dateTimeBetween($invoiceDate, 'now') : null;

            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'invoice_number' => 'INV-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                    'organization_name' => $orgData['name'] ?? '',
                    'deal_name' => $dealData['deal_name'] ?? '',
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'discount' => $discount,
                    'total' => $total,
                    'invoice_date' => $invoiceDate->format('Y-m-d'),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'paid_date' => $paidDate?->format('Y-m-d'),
                    'notes' => $this->faker->optional()->paragraph,
                    'terms' => 'Payment due within 30 days. Late payments subject to 2% monthly interest.',
                ],
                'created_by' => 1,
            ]);
        }

        $this->command->info("Created {$count} invoices");
    }
}
