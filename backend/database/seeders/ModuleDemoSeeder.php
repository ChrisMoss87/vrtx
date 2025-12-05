<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Field;
use App\Models\FieldOption;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\Pipeline;
use App\Models\Stage;
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

        // Clean up existing pipelines first (due to foreign key constraints)
        Pipeline::query()->delete();
        Stage::query()->delete();

        // Clean up existing modules (delete in order due to foreign key constraints)
        // Include soft-deleted records with withTrashed()
        $moduleApiNames = ['organizations', 'contacts', 'deals', 'invoices', 'tasks', 'activities', 'quotes', 'cases', 'products'];
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
        $tasks = $this->createTasksModule();
        $activities = $this->createActivitiesModule();
        $quotes = $this->createQuotesModule();
        $cases = $this->createCasesModule();
        $products = $this->createProductsModule();

        $this->command->info('Creating pipelines...');

        // Create pipelines for modules with stage fields
        $dealsPipeline = $this->createDealsPipeline($deals);
        $casesPipeline = $this->createCasesPipeline($cases);

        $this->command->info('Seeding sample data...');

        // Seed sample data
        $orgIds = $this->seedOrganizations($organizations, 25);
        $contactIds = $this->seedContacts($contacts, 50, $orgIds);
        $dealIds = $this->seedDeals($deals, 30, $orgIds, $contactIds, $dealsPipeline);
        $this->seedInvoices($invoices, 40, $orgIds, $dealIds);
        $this->seedTasks($tasks, 60, $contactIds, $dealIds);
        $this->seedActivities($activities, 80, $contactIds, $dealIds);
        $this->seedQuotes($quotes, 20, $orgIds, $dealIds);
        $this->seedCases($cases, 35, $orgIds, $contactIds, $casesPipeline);
        $this->seedProducts($products, 40);

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

    private function createTasksModule(): Module
    {
        $module = Module::create([
            'name' => 'Tasks',
            'singular_name' => 'Task',
            'api_name' => 'tasks',
            'icon' => 'check-square',
            'description' => 'Manage tasks and to-do items',
            'is_active' => true,
            'display_order' => 5,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'subject',
                'additional_settings' => [],
            ],
        ]);

        // Task Details Block
        $detailsBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Task Details',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $this->createField($detailsBlock, 'subject', 'Subject', 'text', 1, true, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 2, true, true, false, true);
        $this->createFieldOptions($statusField, ['Not Started', 'In Progress', 'Waiting', 'Completed', 'Deferred']);
        $priorityField = $this->createField($detailsBlock, 'priority', 'Priority', 'select', 3, false, true, false, true);
        $this->createFieldOptions($priorityField, ['Low', 'Normal', 'High', 'Urgent']);
        $this->createField($detailsBlock, 'description', 'Description', 'textarea', 4, false, false, false, false);

        // Dates & Assignment Block
        $datesBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Dates & Assignment',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $this->createField($datesBlock, 'due_date', 'Due Date', 'datetime', 1, false, true, false, true);
        $this->createField($datesBlock, 'start_date', 'Start Date', 'datetime', 2, false, true, false, true);
        $this->createField($datesBlock, 'completed_date', 'Completed Date', 'datetime', 3, false, true, false, true);
        $this->createField($datesBlock, 'reminder_date', 'Reminder', 'datetime', 4, false, true, false, true);
        $this->createField($datesBlock, 'assigned_to', 'Assigned To', 'text', 5, false, true, true, true);

        // Related To Block
        $relatedBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Related To',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $this->createField($relatedBlock, 'related_contact', 'Related Contact', 'text', 1, false, true, true, true);
        $this->createField($relatedBlock, 'related_deal', 'Related Deal', 'text', 2, false, true, true, true);
        $this->createField($relatedBlock, 'related_organization', 'Related Organization', 'text', 3, false, true, true, true);

        return $module;
    }

    private function createActivitiesModule(): Module
    {
        $module = Module::create([
            'name' => 'Activities',
            'singular_name' => 'Activity',
            'api_name' => 'activities',
            'icon' => 'activity',
            'description' => 'Track calls, meetings, and interactions',
            'is_active' => true,
            'display_order' => 6,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'subject',
                'additional_settings' => [],
            ],
        ]);

        // Activity Details Block
        $detailsBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Activity Details',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $typeField = $this->createField($detailsBlock, 'activity_type', 'Activity Type', 'select', 1, true, true, false, true);
        $this->createFieldOptions($typeField, ['Call', 'Meeting', 'Email', 'Note', 'Demo', 'Follow-up', 'Other']);
        $this->createField($detailsBlock, 'subject', 'Subject', 'text', 2, true, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 3, true, true, false, true);
        $this->createFieldOptions($statusField, ['Planned', 'In Progress', 'Completed', 'Cancelled']);
        $this->createField($detailsBlock, 'description', 'Description', 'textarea', 4, false, false, false, false);

        // Timing Block
        $timingBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Timing',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $this->createField($timingBlock, 'start_time', 'Start Time', 'datetime', 1, false, true, false, true);
        $this->createField($timingBlock, 'end_time', 'End Time', 'datetime', 2, false, true, false, true);
        $this->createField($timingBlock, 'duration_minutes', 'Duration (minutes)', 'number', 3, false, true, false, true);
        $this->createField($timingBlock, 'location', 'Location', 'text', 4, false, true, true, true);

        // Related Records Block
        $relatedBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Related Records',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $this->createField($relatedBlock, 'related_contact', 'Related Contact', 'text', 1, false, true, true, true);
        $this->createField($relatedBlock, 'related_deal', 'Related Deal', 'text', 2, false, true, true, true);
        $this->createField($relatedBlock, 'related_organization', 'Related Organization', 'text', 3, false, true, true, true);
        $outcomeField = $this->createField($relatedBlock, 'outcome', 'Outcome', 'select', 4, false, true, false, true);
        $this->createFieldOptions($outcomeField, ['Successful', 'No Answer', 'Left Voicemail', 'Rescheduled', 'Not Interested', 'Other']);

        return $module;
    }

    private function createQuotesModule(): Module
    {
        $module = Module::create([
            'name' => 'Quotes',
            'singular_name' => 'Quote',
            'api_name' => 'quotes',
            'icon' => 'file-signature',
            'description' => 'Create and manage quotes and proposals',
            'is_active' => true,
            'display_order' => 7,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'quote_number',
                'additional_settings' => [],
            ],
        ]);

        // Quote Details Block
        $detailsBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Quote Details',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $this->createField($detailsBlock, 'quote_number', 'Quote Number', 'text', 1, true, true, true, true);
        $this->createField($detailsBlock, 'quote_name', 'Quote Name', 'text', 2, true, true, true, true);
        $this->createField($detailsBlock, 'organization_name', 'Organization', 'text', 3, false, true, true, true);
        $this->createField($detailsBlock, 'related_deal', 'Related Deal', 'text', 4, false, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 5, true, true, false, true);
        $this->createFieldOptions($statusField, ['Draft', 'Pending Approval', 'Sent', 'Accepted', 'Rejected', 'Expired']);

        // Financial Details Block
        $financialBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Financial Details',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $this->createField($financialBlock, 'subtotal', 'Subtotal', 'currency', 1, false, true, false, true);
        $this->createField($financialBlock, 'discount_percent', 'Discount (%)', 'percent', 2, false, true, false, true);
        $this->createField($financialBlock, 'discount_amount', 'Discount Amount', 'currency', 3, false, true, false, true);
        $this->createField($financialBlock, 'tax_rate', 'Tax Rate (%)', 'percent', 4, false, true, false, true);
        $this->createField($financialBlock, 'tax_amount', 'Tax Amount', 'currency', 5, false, true, false, true);
        $this->createField($financialBlock, 'total', 'Total Amount', 'currency', 6, true, true, false, true);

        // Dates Block
        $datesBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Dates',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $this->createField($datesBlock, 'quote_date', 'Quote Date', 'date', 1, true, true, false, true);
        $this->createField($datesBlock, 'valid_until', 'Valid Until', 'date', 2, false, true, false, true);
        $this->createField($datesBlock, 'accepted_date', 'Accepted Date', 'date', 3, false, true, false, true);

        // Notes Block
        $notesBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Notes',
            'type' => 'section',
            'display_order' => 4,
            'settings' => [],
        ]);

        $this->createField($notesBlock, 'notes', 'Internal Notes', 'textarea', 1, false, false, false, false);
        $this->createField($notesBlock, 'terms', 'Terms & Conditions', 'textarea', 2, false, false, false, false);

        return $module;
    }

    private function createCasesModule(): Module
    {
        $module = Module::create([
            'name' => 'Cases',
            'singular_name' => 'Case',
            'api_name' => 'cases',
            'icon' => 'headset',
            'description' => 'Manage support tickets and customer issues',
            'is_active' => true,
            'display_order' => 8,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'case_number',
                'additional_settings' => [],
            ],
        ]);

        // Case Details Block
        $detailsBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Case Details',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $this->createField($detailsBlock, 'case_number', 'Case Number', 'text', 1, true, true, true, true);
        $this->createField($detailsBlock, 'subject', 'Subject', 'text', 2, true, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 3, true, true, false, true);
        $this->createFieldOptions($statusField, ['New', 'Open', 'In Progress', 'Waiting on Customer', 'Escalated', 'Resolved', 'Closed']);
        $priorityField = $this->createField($detailsBlock, 'priority', 'Priority', 'select', 4, false, true, false, true);
        $this->createFieldOptions($priorityField, ['Low', 'Medium', 'High', 'Critical']);
        $typeField = $this->createField($detailsBlock, 'case_type', 'Case Type', 'select', 5, false, true, false, true);
        $this->createFieldOptions($typeField, ['Question', 'Problem', 'Feature Request', 'Bug Report', 'Feedback', 'Other']);

        // Description Block
        $descBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Description',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $this->createField($descBlock, 'description', 'Description', 'textarea', 1, true, false, false, false);
        $this->createField($descBlock, 'resolution', 'Resolution', 'textarea', 2, false, false, false, false);

        // Customer Information Block
        $customerBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Customer Information',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $this->createField($customerBlock, 'contact_name', 'Contact', 'text', 1, false, true, true, true);
        $this->createField($customerBlock, 'organization_name', 'Organization', 'text', 2, false, true, true, true);
        $this->createField($customerBlock, 'contact_email', 'Email', 'email', 3, false, true, true, true);
        $this->createField($customerBlock, 'contact_phone', 'Phone', 'phone', 4, false, true, false, true);
        $sourceField = $this->createField($customerBlock, 'case_source', 'Source', 'select', 5, false, true, false, true);
        $this->createFieldOptions($sourceField, ['Email', 'Phone', 'Web Form', 'Chat', 'Social Media', 'Other']);

        // Dates & Assignment Block
        $datesBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Dates & Assignment',
            'type' => 'section',
            'display_order' => 4,
            'settings' => [],
        ]);

        $this->createField($datesBlock, 'opened_date', 'Opened Date', 'datetime', 1, false, true, false, true);
        $this->createField($datesBlock, 'closed_date', 'Closed Date', 'datetime', 2, false, true, false, true);
        $this->createField($datesBlock, 'assigned_to', 'Assigned To', 'text', 3, false, true, true, true);
        $this->createField($datesBlock, 'first_response_date', 'First Response', 'datetime', 4, false, true, false, true);

        return $module;
    }

    private function createProductsModule(): Module
    {
        $module = Module::create([
            'name' => 'Products',
            'singular_name' => 'Product',
            'api_name' => 'products',
            'icon' => 'package',
            'description' => 'Manage products and services catalog',
            'is_active' => true,
            'display_order' => 9,
            'settings' => [
                'has_import' => true,
                'has_export' => true,
                'has_mass_actions' => true,
                'has_comments' => true,
                'has_attachments' => true,
                'has_activity_log' => true,
                'has_custom_views' => true,
                'record_name_field' => 'product_name',
                'additional_settings' => [],
            ],
        ]);

        // Product Details Block
        $detailsBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Product Details',
            'type' => 'section',
            'display_order' => 1,
            'settings' => [],
        ]);

        $this->createField($detailsBlock, 'product_name', 'Product Name', 'text', 1, true, true, true, true);
        $this->createField($detailsBlock, 'product_code', 'Product Code', 'text', 2, false, true, true, true);
        $categoryField = $this->createField($detailsBlock, 'category', 'Category', 'select', 3, false, true, false, true);
        $this->createFieldOptions($categoryField, ['Software', 'Hardware', 'Service', 'Subscription', 'Add-on', 'Support', 'Training', 'Other']);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 4, true, true, false, true);
        $this->createFieldOptions($statusField, ['Active', 'Inactive', 'Discontinued', 'Coming Soon']);
        $this->createField($detailsBlock, 'description', 'Description', 'textarea', 5, false, false, false, false);

        // Pricing Block
        $pricingBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Pricing',
            'type' => 'section',
            'display_order' => 2,
            'settings' => [],
        ]);

        $this->createField($pricingBlock, 'unit_price', 'Unit Price', 'currency', 1, true, true, false, true);
        $this->createField($pricingBlock, 'cost', 'Cost', 'currency', 2, false, true, false, true);
        $this->createField($pricingBlock, 'margin_percent', 'Margin (%)', 'percent', 3, false, true, false, true);
        $this->createField($pricingBlock, 'tax_rate', 'Tax Rate (%)', 'percent', 4, false, true, false, true);

        // Inventory Block
        $inventoryBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Inventory',
            'type' => 'section',
            'display_order' => 3,
            'settings' => [],
        ]);

        $this->createField($inventoryBlock, 'quantity_in_stock', 'Quantity in Stock', 'number', 1, false, true, false, true);
        $this->createField($inventoryBlock, 'reorder_level', 'Reorder Level', 'number', 2, false, true, false, true);
        $this->createField($inventoryBlock, 'quantity_ordered', 'Quantity Ordered', 'number', 3, false, true, false, true);
        $this->createField($inventoryBlock, 'is_tracked', 'Track Inventory', 'checkbox', 4, false, true, false, true);

        // Additional Info Block
        $infoBlock = Block::create([
            'module_id' => $module->id,
            'name' => 'Additional Information',
            'type' => 'section',
            'display_order' => 4,
            'settings' => [],
        ]);

        $this->createField($infoBlock, 'vendor', 'Vendor', 'text', 1, false, true, true, true);
        $this->createField($infoBlock, 'part_number', 'Part Number', 'text', 2, false, true, true, true);
        $this->createField($infoBlock, 'weight', 'Weight (kg)', 'decimal', 3, false, false, false, true);
        $this->createField($infoBlock, 'warranty_months', 'Warranty (months)', 'number', 4, false, false, false, false);

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

    private function seedDeals(Module $module, int $count, array $orgIds, array $contactIds, Pipeline $pipeline): array
    {
        $ids = [];
        $dealTypes = ['new_business', 'existing_business', 'renewal', 'expansion'];
        $leadSources = ['website', 'referral', 'partner', 'outbound', 'event', 'other'];

        // Get organization and contact names
        $organizations = ModuleRecord::whereIn('id', $orgIds)->get()->pluck('data', 'id');
        $contacts = ModuleRecord::whereIn('id', $contactIds)->get()->pluck('data', 'id');

        // Get stages from the pipeline (keyed by name for lookup)
        $stages = $pipeline->stages()->orderBy('display_order')->get();
        $stagesByName = $stages->keyBy(fn($s) => strtolower(str_replace(' ', '_', $s->name)));

        for ($i = 0; $i < $count; $i++) {
            $orgId = $this->faker->randomElement($orgIds);
            $contactId = $this->faker->randomElement($contactIds);
            $orgData = $organizations[$orgId] ?? [];
            $contactData = $contacts[$contactId] ?? [];

            // Pick a random stage
            $stage = $stages->random();
            $stageId = (string) $stage->id;
            $stageName = strtolower(str_replace(' ', '_', $stage->name));

            $amount = $this->faker->numberBetween(5000, 500000);
            $probability = $stage->probability;

            $expectedClose = $this->faker->dateTimeBetween('-1 month', '+3 months');
            $actualClose = ($stage->is_won_stage || $stage->is_lost_stage)
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
                    'stage' => $stageId, // Store stage ID for pipeline/kanban compatibility
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

    private function seedTasks(Module $module, int $count, array $contactIds, array $dealIds): void
    {
        $statuses = ['not_started', 'in_progress', 'waiting', 'completed', 'deferred'];
        $priorities = ['low', 'normal', 'high', 'urgent'];

        // Get contact and deal data
        $contacts = ModuleRecord::whereIn('id', $contactIds)->get()->pluck('data', 'id');
        $deals = ModuleRecord::whereIn('id', $dealIds)->get()->pluck('data', 'id');

        $taskSubjects = [
            'Follow up call', 'Send proposal', 'Schedule meeting', 'Review contract',
            'Prepare presentation', 'Send quote', 'Complete demo', 'Send introduction email',
            'Review requirements', 'Update CRM records', 'Research competitor', 'Prepare report',
            'Send follow-up email', 'Schedule product demo', 'Review pricing', 'Finalize agreement'
        ];

        for ($i = 0; $i < $count; $i++) {
            $contactId = $this->faker->randomElement($contactIds);
            $dealId = $this->faker->randomElement($dealIds);
            $contactData = $contacts[$contactId] ?? [];
            $dealData = $deals[$dealId] ?? [];

            $status = $this->faker->randomElement($statuses);
            $dueDate = $this->faker->dateTimeBetween('-1 week', '+2 weeks');
            $startDate = $this->faker->dateTimeBetween('-2 weeks', 'now');
            $completedDate = $status === 'completed' ? $this->faker->dateTimeBetween($startDate, 'now') : null;

            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'subject' => $this->faker->randomElement($taskSubjects),
                    'status' => $status,
                    'priority' => $this->faker->randomElement($priorities),
                    'description' => $this->faker->optional()->paragraph,
                    'due_date' => $dueDate->format('Y-m-d H:i:s'),
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'completed_date' => $completedDate?->format('Y-m-d H:i:s'),
                    'reminder_date' => $this->faker->boolean(50) ? $dueDate->modify('-1 day')->format('Y-m-d H:i:s') : null,
                    'assigned_to' => $this->faker->name,
                    'related_contact' => ($contactData['first_name'] ?? '') . ' ' . ($contactData['last_name'] ?? ''),
                    'related_deal' => $dealData['deal_name'] ?? '',
                    'related_organization' => $dealData['organization_name'] ?? '',
                ],
                'created_by' => 1,
            ]);
        }

        $this->command->info("Created {$count} tasks");
    }

    private function seedActivities(Module $module, int $count, array $contactIds, array $dealIds): void
    {
        $activityTypes = ['call', 'meeting', 'email', 'note', 'demo', 'follow-up', 'other'];
        $statuses = ['planned', 'in_progress', 'completed', 'cancelled'];
        $outcomes = ['successful', 'no_answer', 'left_voicemail', 'rescheduled', 'not_interested', 'other'];

        // Get contact and deal data
        $contacts = ModuleRecord::whereIn('id', $contactIds)->get()->pluck('data', 'id');
        $deals = ModuleRecord::whereIn('id', $dealIds)->get()->pluck('data', 'id');

        $activitySubjects = [
            'Discovery call', 'Product demo', 'Quarterly review', 'Contract negotiation',
            'Initial outreach', 'Technical discussion', 'Pricing discussion', 'Requirements gathering',
            'Follow-up meeting', 'Onboarding call', 'Support call', 'Check-in meeting'
        ];

        for ($i = 0; $i < $count; $i++) {
            $contactId = $this->faker->randomElement($contactIds);
            $dealId = $this->faker->randomElement($dealIds);
            $contactData = $contacts[$contactId] ?? [];
            $dealData = $deals[$dealId] ?? [];

            $type = $this->faker->randomElement($activityTypes);
            $status = $this->faker->randomElement($statuses);
            $startTime = $this->faker->dateTimeBetween('-1 month', '+1 week');
            $duration = $this->faker->randomElement([15, 30, 45, 60, 90, 120]);
            $endTime = (clone $startTime)->modify("+{$duration} minutes");

            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'activity_type' => $type,
                    'subject' => $this->faker->randomElement($activitySubjects),
                    'status' => $status,
                    'description' => $this->faker->optional()->paragraph,
                    'start_time' => $startTime->format('Y-m-d H:i:s'),
                    'end_time' => $endTime->format('Y-m-d H:i:s'),
                    'duration_minutes' => $duration,
                    'location' => $type === 'meeting' ? $this->faker->randomElement(['Conference Room A', 'Conference Room B', 'Zoom', 'Google Meet', 'Teams', 'On-site']) : null,
                    'related_contact' => ($contactData['first_name'] ?? '') . ' ' . ($contactData['last_name'] ?? ''),
                    'related_deal' => $dealData['deal_name'] ?? '',
                    'related_organization' => $dealData['organization_name'] ?? '',
                    'outcome' => $status === 'completed' ? $this->faker->randomElement($outcomes) : null,
                ],
                'created_by' => 1,
            ]);
        }

        $this->command->info("Created {$count} activities");
    }

    private function seedQuotes(Module $module, int $count, array $orgIds, array $dealIds): void
    {
        $statuses = ['draft', 'pending_approval', 'sent', 'accepted', 'rejected', 'expired'];

        // Get organization and deal data
        $organizations = ModuleRecord::whereIn('id', $orgIds)->get()->pluck('data', 'id');
        $deals = ModuleRecord::whereIn('id', $dealIds)->get()->pluck('data', 'id');

        for ($i = 0; $i < $count; $i++) {
            $orgId = $this->faker->randomElement($orgIds);
            $dealId = $this->faker->randomElement($dealIds);
            $orgData = $organizations[$orgId] ?? [];
            $dealData = $deals[$dealId] ?? [];

            $status = $this->faker->randomElement($statuses);
            $subtotal = $this->faker->numberBetween(5000, 100000);
            $discountPercent = $this->faker->boolean(40) ? $this->faker->randomElement([5, 10, 15, 20]) : 0;
            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            $taxRate = $this->faker->randomElement([0, 5, 10, 15, 20]);
            $taxAmount = round(($subtotal - $discountAmount) * ($taxRate / 100), 2);
            $total = $subtotal - $discountAmount + $taxAmount;

            $quoteDate = $this->faker->dateTimeBetween('-2 months', 'now');
            $validUntil = (clone $quoteDate)->modify('+30 days');
            $acceptedDate = $status === 'accepted' ? $this->faker->dateTimeBetween($quoteDate, 'now') : null;

            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'quote_number' => 'QT-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                    'quote_name' => ($orgData['name'] ?? 'Client') . ' - ' . $this->faker->words(2, true),
                    'organization_name' => $orgData['name'] ?? '',
                    'related_deal' => $dealData['deal_name'] ?? '',
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'quote_date' => $quoteDate->format('Y-m-d'),
                    'valid_until' => $validUntil->format('Y-m-d'),
                    'accepted_date' => $acceptedDate?->format('Y-m-d'),
                    'notes' => $this->faker->optional()->paragraph,
                    'terms' => 'Quote valid for 30 days. Prices subject to change.',
                ],
                'created_by' => 1,
            ]);
        }

        $this->command->info("Created {$count} quotes");
    }

    private function seedCases(Module $module, int $count, array $orgIds, array $contactIds, Pipeline $pipeline): void
    {
        $priorities = ['low', 'medium', 'high', 'critical'];
        $caseTypes = ['question', 'problem', 'feature_request', 'bug_report', 'feedback', 'other'];
        $caseSources = ['email', 'phone', 'web_form', 'chat', 'social_media', 'other'];

        // Get organization and contact data
        $organizations = ModuleRecord::whereIn('id', $orgIds)->get()->pluck('data', 'id');
        $contacts = ModuleRecord::whereIn('id', $contactIds)->get()->pluck('data', 'id');

        // Get stages from the pipeline
        $stages = $pipeline->stages()->orderBy('display_order')->get();

        $caseSubjects = [
            'Cannot login to account', 'Feature request: Export to PDF', 'Integration not working',
            'Slow performance issue', 'Billing question', 'How to configure settings',
            'Data import error', 'API authentication failing', 'Report not generating',
            'User permission issue', 'Password reset not working', 'Sync error with calendar'
        ];

        for ($i = 0; $i < $count; $i++) {
            $orgId = $this->faker->randomElement($orgIds);
            $contactId = $this->faker->randomElement($contactIds);
            $orgData = $organizations[$orgId] ?? [];
            $contactData = $contacts[$contactId] ?? [];

            // Pick a random stage
            $stage = $stages->random();
            $stageId = (string) $stage->id;

            $openedDate = $this->faker->dateTimeBetween('-3 months', 'now');
            $closedDate = $stage->is_won_stage ? $this->faker->dateTimeBetween($openedDate, 'now') : null;
            $firstResponseDate = $this->faker->dateTimeBetween($openedDate, $closedDate ?? 'now');

            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'case_number' => 'CS-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                    'subject' => $this->faker->randomElement($caseSubjects),
                    'status' => $stageId, // Store stage ID for pipeline/kanban compatibility
                    'priority' => $this->faker->randomElement($priorities),
                    'case_type' => $this->faker->randomElement($caseTypes),
                    'description' => $this->faker->paragraph(3),
                    'resolution' => $stage->is_won_stage ? $this->faker->paragraph(2) : null,
                    'contact_name' => ($contactData['first_name'] ?? '') . ' ' . ($contactData['last_name'] ?? ''),
                    'organization_name' => $orgData['name'] ?? '',
                    'contact_email' => $contactData['email'] ?? '',
                    'contact_phone' => $contactData['phone'] ?? '',
                    'case_source' => $this->faker->randomElement($caseSources),
                    'opened_date' => $openedDate->format('Y-m-d H:i:s'),
                    'closed_date' => $closedDate?->format('Y-m-d H:i:s'),
                    'assigned_to' => $this->faker->name,
                    'first_response_date' => $firstResponseDate->format('Y-m-d H:i:s'),
                ],
                'created_by' => 1,
            ]);
        }

        $this->command->info("Created {$count} cases");
    }

    private function seedProducts(Module $module, int $count): void
    {
        $categories = ['software', 'hardware', 'service', 'subscription', 'add-on', 'support', 'training', 'other'];
        $statuses = ['active', 'inactive', 'discontinued', 'coming_soon'];

        $productNames = [
            'Professional License', 'Enterprise License', 'Basic Plan', 'Premium Plan',
            'API Access', 'Custom Integration', 'Training Package', 'Support Bundle',
            'Data Migration', 'Implementation Service', 'Consulting Hours', 'Premium Support',
            'Analytics Add-on', 'Security Module', 'Backup Service', 'White Label Option'
        ];

        for ($i = 0; $i < $count; $i++) {
            $unitPrice = $this->faker->numberBetween(100, 10000);
            $cost = round($unitPrice * $this->faker->randomFloat(2, 0.3, 0.6), 2);
            $marginPercent = round((($unitPrice - $cost) / $unitPrice) * 100, 2);

            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => [
                    'product_name' => $this->faker->randomElement($productNames) . ' ' . $this->faker->word,
                    'product_code' => strtoupper($this->faker->bothify('???-####')),
                    'category' => $this->faker->randomElement($categories),
                    'status' => $this->faker->randomElement($statuses),
                    'description' => $this->faker->paragraph,
                    'unit_price' => $unitPrice,
                    'cost' => $cost,
                    'margin_percent' => $marginPercent,
                    'tax_rate' => $this->faker->randomElement([0, 5, 10, 15, 20]),
                    'quantity_in_stock' => $this->faker->numberBetween(0, 500),
                    'reorder_level' => $this->faker->numberBetween(10, 50),
                    'quantity_ordered' => $this->faker->numberBetween(0, 100),
                    'is_tracked' => $this->faker->boolean(70),
                    'vendor' => $this->faker->company,
                    'part_number' => strtoupper($this->faker->bothify('??######')),
                    'weight' => $this->faker->randomFloat(2, 0.1, 10),
                    'warranty_months' => $this->faker->randomElement([0, 12, 24, 36]),
                ],
                'created_by' => 1,
            ]);
        }

        $this->command->info("Created {$count} products");
    }

    /**
     * Create the Sales Pipeline for the Deals module.
     */
    private function createDealsPipeline(Module $deals): Pipeline
    {
        $pipeline = Pipeline::create([
            'name' => 'Sales Pipeline',
            'module_id' => $deals->id,
            'stage_field_api_name' => 'stage',
            'is_active' => true,
            'settings' => [
                'show_totals' => true,
                'value_field' => 'amount',
            ],
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Create stages that match the deal stage field options
        $stages = [
            ['name' => 'Qualification', 'color' => '#6366f1', 'probability' => 10],
            ['name' => 'Needs Analysis', 'color' => '#8b5cf6', 'probability' => 25],
            ['name' => 'Proposal', 'color' => '#f59e0b', 'probability' => 50],
            ['name' => 'Negotiation', 'color' => '#3b82f6', 'probability' => 75],
            ['name' => 'Closed Won', 'color' => '#22c55e', 'probability' => 100, 'is_won_stage' => true],
            ['name' => 'Closed Lost', 'color' => '#ef4444', 'probability' => 0, 'is_lost_stage' => true],
        ];

        foreach ($stages as $index => $stageData) {
            Stage::create([
                'pipeline_id' => $pipeline->id,
                'name' => $stageData['name'],
                'color' => $stageData['color'],
                'probability' => $stageData['probability'],
                'display_order' => $index,
                'is_won_stage' => $stageData['is_won_stage'] ?? false,
                'is_lost_stage' => $stageData['is_lost_stage'] ?? false,
                'settings' => [],
            ]);
        }

        $this->command->info("Created Sales Pipeline with " . count($stages) . " stages");

        return $pipeline;
    }

    /**
     * Create the Support Pipeline for the Cases module.
     */
    private function createCasesPipeline(Module $cases): Pipeline
    {
        $pipeline = Pipeline::create([
            'name' => 'Support Pipeline',
            'module_id' => $cases->id,
            'stage_field_api_name' => 'status',
            'is_active' => true,
            'settings' => [
                'show_totals' => false,
            ],
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Create stages that match the case status field options
        $stages = [
            ['name' => 'New', 'color' => '#6366f1', 'probability' => 0],
            ['name' => 'Open', 'color' => '#3b82f6', 'probability' => 10],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'probability' => 50],
            ['name' => 'Waiting on Customer', 'color' => '#8b5cf6', 'probability' => 60],
            ['name' => 'Escalated', 'color' => '#ef4444', 'probability' => 70],
            ['name' => 'Resolved', 'color' => '#22c55e', 'probability' => 100, 'is_won_stage' => true],
            ['name' => 'Closed', 'color' => '#6b7280', 'probability' => 100, 'is_won_stage' => true],
        ];

        foreach ($stages as $index => $stageData) {
            Stage::create([
                'pipeline_id' => $pipeline->id,
                'name' => $stageData['name'],
                'color' => $stageData['color'],
                'probability' => $stageData['probability'],
                'display_order' => $index,
                'is_won_stage' => $stageData['is_won_stage'] ?? false,
                'is_lost_stage' => $stageData['is_lost_stage'] ?? false,
                'settings' => [],
            ]);
        }

        $this->command->info("Created Support Pipeline with " . count($stages) . " stages");

        return $pipeline;
    }
}
