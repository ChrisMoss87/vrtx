<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Field;
use App\Models\FieldOption;
use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Seeds the default modules for a new tenant.
 * Creates the core CRM modules without sample data.
 */
class DefaultModulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating default modules...');

        $this->createContactsModule();
        $this->createOrganizationsModule();
        $this->createDealsModule();
        $this->createTasksModule();
        $this->createActivitiesModule();
        $this->createNotesModule();
        $this->createCasesModule();
        $this->createProductsModule();
        $this->createInvoicesModule();
        $this->createQuotesModule();
        $this->createEventsModule();

        $this->command->info('Default modules created successfully!');
    }

    private function createContactsModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'contacts'],
            [
                'name' => 'Contacts',
                'singular_name' => 'Contact',
                'icon' => 'users',
                'description' => 'People you interact with - leads, customers, partners',
                'is_active' => true,
                'display_order' => 1,
                'settings' => $this->defaultModuleSettings('first_name'),
            ]
        );

        // Personal Information Block
        $personalBlock = $this->createBlock($module, 'Personal Information', 1);
        $this->createField($personalBlock, 'first_name', 'First Name', 'text', 1, true, true, true, true);
        $this->createField($personalBlock, 'last_name', 'Last Name', 'text', 2, true, true, true, true);
        $this->createField($personalBlock, 'email', 'Email', 'email', 3, false, true, true, true, true);
        $this->createField($personalBlock, 'phone', 'Phone', 'phone', 4, false, true, false, true);
        $this->createField($personalBlock, 'mobile', 'Mobile', 'phone', 5, false, true, false, true);
        $this->createField($personalBlock, 'date_of_birth', 'Date of Birth', 'date', 6, false, false, false, true);

        // Work Information Block
        $workBlock = $this->createBlock($module, 'Work Information', 2);
        $this->createField($workBlock, 'organization_id', 'Organization', 'lookup', 1, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($workBlock, 'job_title', 'Job Title', 'text', 2, false, true, true, true);
        $this->createField($workBlock, 'department', 'Department', 'text', 3, false, true, true, true);
        $this->createField($workBlock, 'linkedin_url', 'LinkedIn', 'url', 4, false, false, false, true);
        $this->createField($workBlock, 'twitter_handle', 'Twitter', 'text', 5, false, false, false, true);

        // Address Block
        $addressBlock = $this->createBlock($module, 'Address', 3);
        $this->createField($addressBlock, 'street', 'Street', 'text', 1, false, false, false, true);
        $this->createField($addressBlock, 'city', 'City', 'text', 2, false, true, true, true);
        $this->createField($addressBlock, 'state', 'State/Province', 'text', 3, false, true, true, true);
        $this->createField($addressBlock, 'postal_code', 'Postal Code', 'text', 4, false, true, false, true);
        $countryField = $this->createField($addressBlock, 'country', 'Country', 'select', 5, false, true, true, true);
        $this->createCountryOptions($countryField);

        // Status & Source Block
        $statusBlock = $this->createBlock($module, 'Status & Source', 4);
        $statusField = $this->createField($statusBlock, 'status', 'Status', 'select', 1, false, true, false, true);
        $this->createFieldOptions($statusField, ['Lead', 'Prospect', 'Customer', 'Partner', 'Inactive']);
        $sourceField = $this->createField($statusBlock, 'lead_source', 'Lead Source', 'select', 2, false, true, false, true);
        $this->createFieldOptions($sourceField, ['Website', 'Referral', 'Social Media', 'Email Campaign', 'Cold Call', 'Trade Show', 'Partner', 'Other']);
        $this->createField($statusBlock, 'assigned_to', 'Assigned To', 'lookup', 3, false, true, true, true, false, ['target_module' => 'users']);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 5);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', 'multiselect', 1, false, true, false, true);
        $this->createFieldOptions($tagsField, ['VIP', 'Decision Maker', 'Technical', 'Influencer', 'Champion']);
        $this->createField($additionalBlock, 'do_not_contact', 'Do Not Contact', 'checkbox', 2, false, true, false, true);
        $this->createField($additionalBlock, 'notes', 'Notes', 'textarea', 3, false, false, false, false);

        $this->command->info('  - Created Contacts module');
        return $module;
    }

    private function createOrganizationsModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'organizations'],
            [
                'name' => 'Organizations',
                'singular_name' => 'Organization',
                'icon' => 'building-2',
                'description' => 'Companies, businesses, and entities',
                'is_active' => true,
                'display_order' => 2,
                'settings' => $this->defaultModuleSettings('name'),
            ]
        );

        // Basic Information Block
        $basicBlock = $this->createBlock($module, 'Basic Information', 1);
        $this->createField($basicBlock, 'name', 'Organization Name', 'text', 1, true, true, true, true);
        $this->createField($basicBlock, 'website', 'Website', 'url', 2, false, true, false, true);
        $industryField = $this->createField($basicBlock, 'industry', 'Industry', 'select', 3, false, true, false, true);
        $this->createFieldOptions($industryField, [
            'Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing',
            'Education', 'Real Estate', 'Consulting', 'Media', 'Transportation',
            'Energy', 'Agriculture', 'Hospitality', 'Legal', 'Non-Profit', 'Government', 'Other'
        ]);
        $sizeField = $this->createField($basicBlock, 'employee_count', 'Employee Count', 'select', 4, false, true, false, true);
        $this->createFieldOptions($sizeField, ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+']);

        // Contact Details Block
        $contactBlock = $this->createBlock($module, 'Contact Details', 2);
        $this->createField($contactBlock, 'phone', 'Phone', 'phone', 1, false, true, false, true);
        $this->createField($contactBlock, 'email', 'Email', 'email', 2, false, true, true, true);
        $this->createField($contactBlock, 'fax', 'Fax', 'phone', 3, false, false, false, true);

        // Address Block
        $addressBlock = $this->createBlock($module, 'Address', 3);
        $this->createField($addressBlock, 'street', 'Street', 'text', 1, false, false, false, true);
        $this->createField($addressBlock, 'city', 'City', 'text', 2, false, true, true, true);
        $this->createField($addressBlock, 'state', 'State/Province', 'text', 3, false, true, true, true);
        $this->createField($addressBlock, 'postal_code', 'Postal Code', 'text', 4, false, true, false, true);
        $countryField = $this->createField($addressBlock, 'country', 'Country', 'select', 5, false, true, true, true);
        $this->createCountryOptions($countryField);

        // Business Details Block
        $businessBlock = $this->createBlock($module, 'Business Details', 4);
        $typeField = $this->createField($businessBlock, 'type', 'Type', 'select', 1, false, true, false, true);
        $this->createFieldOptions($typeField, ['Customer', 'Prospect', 'Partner', 'Vendor', 'Competitor']);
        $this->createField($businessBlock, 'annual_revenue', 'Annual Revenue', 'currency', 2, false, true, false, true);
        $this->createField($businessBlock, 'assigned_to', 'Assigned To', 'lookup', 3, false, true, true, true, false, ['target_module' => 'users']);
        $this->createField($businessBlock, 'parent_organization_id', 'Parent Organization', 'lookup', 4, false, true, false, true, false, ['target_module' => 'organizations']);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 5);
        $this->createField($additionalBlock, 'description', 'Description', 'textarea', 1, false, false, false, false);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', 'multiselect', 2, false, true, false, true);
        $this->createFieldOptions($tagsField, ['Enterprise', 'SMB', 'Startup', 'Strategic', 'At Risk']);

        $this->command->info('  - Created Organizations module');
        return $module;
    }

    private function createDealsModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'deals'],
            [
                'name' => 'Deals',
                'singular_name' => 'Deal',
                'icon' => 'handshake',
                'description' => 'Sales opportunities and revenue tracking',
                'is_active' => true,
                'display_order' => 3,
                'settings' => $this->defaultModuleSettings('name'),
            ]
        );

        // Deal Information Block
        $dealBlock = $this->createBlock($module, 'Deal Information', 1);
        $this->createField($dealBlock, 'name', 'Deal Name', 'text', 1, true, true, true, true);
        $this->createField($dealBlock, 'amount', 'Amount', 'currency', 2, false, true, false, true);
        $this->createField($dealBlock, 'probability', 'Probability', 'percent', 3, false, true, false, true);
        $this->createField($dealBlock, 'expected_revenue', 'Expected Revenue', 'currency', 4, false, true, false, true, false, [
            'formula' => 'amount * probability / 100',
            'is_formula' => true
        ]);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 2);
        $this->createField($relatedBlock, 'organization_id', 'Organization', 'lookup', 1, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'contact_id', 'Primary Contact', 'lookup', 2, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'assigned_to', 'Assigned To', 'lookup', 3, false, true, true, true, false, ['target_module' => 'users']);

        // Stage & Timeline Block
        $stageBlock = $this->createBlock($module, 'Stage & Timeline', 3);
        $stageField = $this->createField($stageBlock, 'stage', 'Stage', 'select', 1, true, true, false, true);
        $this->createFieldOptions($stageField, ['Prospecting', 'Qualification', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost']);
        $this->createField($stageBlock, 'close_date', 'Expected Close Date', 'date', 2, false, true, false, true);
        $sourceField = $this->createField($stageBlock, 'source', 'Source', 'select', 3, false, true, false, true);
        $this->createFieldOptions($sourceField, ['Website', 'Referral', 'Partner', 'Outbound', 'Inbound', 'Event', 'Other']);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 4);
        $this->createField($additionalBlock, 'description', 'Description', 'textarea', 1, false, false, false, false);
        $this->createField($additionalBlock, 'next_step', 'Next Step', 'text', 2, false, false, false, false);
        $this->createField($additionalBlock, 'competitors', 'Competitors', 'text', 3, false, false, false, false);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', 'multiselect', 4, false, true, false, true);
        $this->createFieldOptions($tagsField, ['High Value', 'Strategic', 'Quick Win', 'Renewal', 'Expansion']);

        $this->command->info('  - Created Deals module');
        return $module;
    }

    private function createTasksModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'tasks'],
            [
                'name' => 'Tasks',
                'singular_name' => 'Task',
                'icon' => 'check-square',
                'description' => 'To-do items and action items',
                'is_active' => true,
                'display_order' => 4,
                'settings' => $this->defaultModuleSettings('subject'),
            ]
        );

        // Task Details Block
        $detailsBlock = $this->createBlock($module, 'Task Details', 1);
        $this->createField($detailsBlock, 'subject', 'Subject', 'text', 1, true, true, true, true);
        $this->createField($detailsBlock, 'description', 'Description', 'textarea', 2, false, false, false, false);
        $priorityField = $this->createField($detailsBlock, 'priority', 'Priority', 'select', 3, false, true, false, true);
        $this->createFieldOptions($priorityField, ['Low', 'Normal', 'High', 'Urgent']);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 4, true, true, false, true);
        $this->createFieldOptions($statusField, ['Not Started', 'In Progress', 'Completed', 'Waiting', 'Deferred']);

        // Dates & Assignment Block
        $datesBlock = $this->createBlock($module, 'Dates & Assignment', 2);
        $this->createField($datesBlock, 'due_date', 'Due Date', 'date', 1, false, true, false, true);
        $this->createField($datesBlock, 'due_time', 'Due Time', 'time', 2, false, false, false, true);
        $this->createField($datesBlock, 'reminder_date', 'Reminder', 'datetime', 3, false, false, false, true);
        $this->createField($datesBlock, 'assigned_to', 'Assigned To', 'lookup', 4, false, true, true, true, false, ['target_module' => 'users']);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 3);
        $relatedTypeField = $this->createField($relatedBlock, 'related_to_type', 'Related To Type', 'select', 1, false, true, false, true);
        $this->createFieldOptions($relatedTypeField, ['Contact', 'Organization', 'Deal', 'Case']);
        $this->createField($relatedBlock, 'related_to_id', 'Related To', 'number', 2, false, true, false, true);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 4);
        $this->createField($additionalBlock, 'is_recurring', 'Recurring', 'checkbox', 1, false, true, false, true);
        $recurrenceField = $this->createField($additionalBlock, 'recurrence_pattern', 'Recurrence Pattern', 'select', 2, false, false, false, true);
        $this->createFieldOptions($recurrenceField, ['Daily', 'Weekly', 'Monthly', 'Yearly']);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', 'multiselect', 3, false, true, false, true);
        $this->createFieldOptions($tagsField, ['Follow-up', 'Meeting Prep', 'Research', 'Admin', 'Urgent']);

        $this->command->info('  - Created Tasks module');
        return $module;
    }

    private function createActivitiesModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'activities'],
            [
                'name' => 'Activities',
                'singular_name' => 'Activity',
                'icon' => 'activity',
                'description' => 'Calls, meetings, emails, and interactions',
                'is_active' => true,
                'display_order' => 5,
                'settings' => $this->defaultModuleSettings('subject'),
            ]
        );

        // Activity Details Block
        $detailsBlock = $this->createBlock($module, 'Activity Details', 1);
        $this->createField($detailsBlock, 'subject', 'Subject', 'text', 1, true, true, true, true);
        $typeField = $this->createField($detailsBlock, 'type', 'Type', 'select', 2, true, true, false, true);
        $this->createFieldOptions($typeField, ['Call', 'Meeting', 'Email', 'Note', 'Demo', 'Lunch', 'Other']);
        $this->createField($detailsBlock, 'description', 'Description', 'textarea', 3, false, false, false, false);

        // Timing Block
        $timingBlock = $this->createBlock($module, 'Timing', 2);
        $this->createField($timingBlock, 'start_datetime', 'Start', 'datetime', 1, false, true, false, true);
        $this->createField($timingBlock, 'end_datetime', 'End', 'datetime', 2, false, true, false, true);
        $this->createField($timingBlock, 'duration_minutes', 'Duration (minutes)', 'number', 3, false, true, false, true);
        $this->createField($timingBlock, 'all_day', 'All Day', 'checkbox', 4, false, false, false, true);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 3);
        $this->createField($relatedBlock, 'contact_id', 'Contact', 'lookup', 1, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'organization_id', 'Organization', 'lookup', 2, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'deal_id', 'Deal', 'lookup', 3, false, true, true, true, false, ['target_module' => 'deals']);

        // Outcome Block
        $outcomeBlock = $this->createBlock($module, 'Outcome', 4);
        $outcomeField = $this->createField($outcomeBlock, 'outcome', 'Outcome', 'select', 1, false, true, false, true);
        $this->createFieldOptions($outcomeField, ['Completed', 'No Answer', 'Left Message', 'Rescheduled', 'Cancelled']);
        $this->createField($outcomeBlock, 'next_action', 'Next Action', 'text', 2, false, false, false, false);
        $this->createField($outcomeBlock, 'assigned_to', 'Assigned To', 'lookup', 3, false, true, true, true, false, ['target_module' => 'users']);

        $this->command->info('  - Created Activities module');
        return $module;
    }

    private function createNotesModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'notes'],
            [
                'name' => 'Notes',
                'singular_name' => 'Note',
                'icon' => 'file-text',
                'description' => 'Internal notes and documentation',
                'is_active' => true,
                'display_order' => 6,
                'settings' => $this->defaultModuleSettings('title'),
            ]
        );

        // Note Content Block
        $contentBlock = $this->createBlock($module, 'Note Content', 1);
        $this->createField($contentBlock, 'title', 'Title', 'text', 1, true, true, true, true);
        $this->createField($contentBlock, 'content', 'Content', 'textarea', 2, true, false, false, false);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 2);
        $relatedTypeField = $this->createField($relatedBlock, 'related_to_type', 'Related To Type', 'select', 1, false, true, false, true);
        $this->createFieldOptions($relatedTypeField, ['Contact', 'Organization', 'Deal', 'Case', 'Task']);
        $this->createField($relatedBlock, 'related_to_id', 'Related To', 'number', 2, false, true, false, true);

        // Metadata Block
        $metadataBlock = $this->createBlock($module, 'Metadata', 3);
        $this->createField($metadataBlock, 'is_pinned', 'Pinned', 'checkbox', 1, false, true, false, true);
        $tagsField = $this->createField($metadataBlock, 'tags', 'Tags', 'multiselect', 2, false, true, false, true);
        $this->createFieldOptions($tagsField, ['Important', 'Action Required', 'Reference', 'Meeting Notes', 'Strategy']);
        $visibilityField = $this->createField($metadataBlock, 'visibility', 'Visibility', 'select', 3, false, true, false, true);
        $this->createFieldOptions($visibilityField, ['Everyone', 'Team Only', 'Private']);

        $this->command->info('  - Created Notes module');
        return $module;
    }

    private function createCasesModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'cases'],
            [
                'name' => 'Cases',
                'singular_name' => 'Case',
                'icon' => 'headset',
                'description' => 'Customer support tickets and issues',
                'is_active' => true,
                'display_order' => 7,
                'settings' => $this->defaultModuleSettings('case_number'),
            ]
        );

        // Case Details Block
        $detailsBlock = $this->createBlock($module, 'Case Details', 1);
        $this->createField($detailsBlock, 'case_number', 'Case Number', 'text', 1, true, true, true, true, true);
        $this->createField($detailsBlock, 'subject', 'Subject', 'text', 2, true, true, true, true);
        $this->createField($detailsBlock, 'description', 'Description', 'textarea', 3, false, false, false, false);
        $typeField = $this->createField($detailsBlock, 'type', 'Type', 'select', 4, false, true, false, true);
        $this->createFieldOptions($typeField, ['Question', 'Problem', 'Feature Request', 'Bug']);

        // Classification Block
        $classificationBlock = $this->createBlock($module, 'Classification', 2);
        $statusField = $this->createField($classificationBlock, 'status', 'Status', 'select', 1, true, true, false, true);
        $this->createFieldOptions($statusField, ['New', 'Open', 'In Progress', 'Waiting on Customer', 'Resolved', 'Closed']);
        $priorityField = $this->createField($classificationBlock, 'priority', 'Priority', 'select', 2, false, true, false, true);
        $this->createFieldOptions($priorityField, ['Low', 'Medium', 'High', 'Critical']);
        $severityField = $this->createField($classificationBlock, 'severity', 'Severity', 'select', 3, false, true, false, true);
        $this->createFieldOptions($severityField, ['Minor', 'Major', 'Critical', 'Blocker']);

        // Customer Information Block
        $customerBlock = $this->createBlock($module, 'Customer Information', 3);
        $this->createField($customerBlock, 'contact_id', 'Contact', 'lookup', 1, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($customerBlock, 'organization_id', 'Organization', 'lookup', 2, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($customerBlock, 'email', 'Email', 'email', 3, false, true, true, true);
        $this->createField($customerBlock, 'phone', 'Phone', 'phone', 4, false, true, false, true);

        // Assignment & Resolution Block
        $assignmentBlock = $this->createBlock($module, 'Assignment & Resolution', 4);
        $this->createField($assignmentBlock, 'assigned_to', 'Assigned To', 'lookup', 1, false, true, true, true, false, ['target_module' => 'users']);
        $teamField = $this->createField($assignmentBlock, 'team', 'Team', 'select', 2, false, true, false, true);
        $this->createFieldOptions($teamField, ['Support', 'Engineering', 'Sales', 'Billing']);
        $this->createField($assignmentBlock, 'resolution', 'Resolution', 'textarea', 3, false, false, false, false);
        $this->createField($assignmentBlock, 'resolution_date', 'Resolution Date', 'datetime', 4, false, true, false, true);
        $this->createField($assignmentBlock, 'first_response_date', 'First Response', 'datetime', 5, false, true, false, true);

        // SLA Tracking Block
        $slaBlock = $this->createBlock($module, 'SLA Tracking', 5);
        $this->createField($slaBlock, 'sla_due_date', 'SLA Due Date', 'datetime', 1, false, true, false, true);
        $this->createField($slaBlock, 'escalated', 'Escalated', 'checkbox', 2, false, true, false, true);
        $this->createField($slaBlock, 'escalation_date', 'Escalation Date', 'datetime', 3, false, true, false, true);

        $this->command->info('  - Created Cases module');
        return $module;
    }

    private function createProductsModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'products'],
            [
                'name' => 'Products',
                'singular_name' => 'Product',
                'icon' => 'package',
                'description' => 'Products and services catalog',
                'is_active' => true,
                'display_order' => 8,
                'settings' => $this->defaultModuleSettings('name'),
            ]
        );

        // Product Information Block
        $infoBlock = $this->createBlock($module, 'Product Information', 1);
        $this->createField($infoBlock, 'name', 'Product Name', 'text', 1, true, true, true, true);
        $this->createField($infoBlock, 'sku', 'SKU', 'text', 2, false, true, true, true, true);
        $this->createField($infoBlock, 'description', 'Description', 'textarea', 3, false, false, false, false);
        $categoryField = $this->createField($infoBlock, 'category', 'Category', 'select', 4, false, true, false, true);
        $this->createFieldOptions($categoryField, ['Software', 'Hardware', 'Services', 'Consulting', 'Training', 'Support', 'Subscription', 'Add-on']);

        // Pricing Block
        $pricingBlock = $this->createBlock($module, 'Pricing', 2);
        $this->createField($pricingBlock, 'unit_price', 'Unit Price', 'currency', 1, true, true, false, true);
        $this->createField($pricingBlock, 'cost', 'Cost', 'currency', 2, false, true, false, true);
        $this->createField($pricingBlock, 'margin', 'Margin (%)', 'percent', 3, false, true, false, true);
        $this->createField($pricingBlock, 'tax_rate', 'Tax Rate (%)', 'percent', 4, false, true, false, true);

        // Inventory Block
        $inventoryBlock = $this->createBlock($module, 'Inventory', 3);
        $this->createField($inventoryBlock, 'quantity_in_stock', 'Quantity in Stock', 'number', 1, false, true, false, true);
        $this->createField($inventoryBlock, 'reorder_level', 'Reorder Level', 'number', 2, false, true, false, true);
        $this->createField($inventoryBlock, 'is_active', 'Active', 'checkbox', 3, false, true, false, true);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 4);
        $this->createField($additionalBlock, 'vendor', 'Vendor', 'text', 1, false, true, true, true);
        $this->createField($additionalBlock, 'weight', 'Weight (kg)', 'decimal', 2, false, false, false, true);
        $this->createField($additionalBlock, 'dimensions', 'Dimensions', 'text', 3, false, false, false, true);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', 'multiselect', 4, false, true, false, true);
        $this->createFieldOptions($tagsField, ['Featured', 'New', 'Bestseller', 'Clearance', 'Limited']);

        $this->command->info('  - Created Products module');
        return $module;
    }

    private function createInvoicesModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'invoices'],
            [
                'name' => 'Invoices',
                'singular_name' => 'Invoice',
                'icon' => 'file-text',
                'description' => 'Customer invoices and billing',
                'is_active' => true,
                'display_order' => 9,
                'settings' => $this->defaultModuleSettings('invoice_number'),
            ]
        );

        // Invoice Details Block
        $detailsBlock = $this->createBlock($module, 'Invoice Details', 1);
        $this->createField($detailsBlock, 'invoice_number', 'Invoice Number', 'text', 1, true, true, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 2, true, true, false, true);
        $this->createFieldOptions($statusField, ['Draft', 'Sent', 'Paid', 'Overdue', 'Cancelled', 'Refunded']);

        // Amounts Block
        $amountsBlock = $this->createBlock($module, 'Amounts', 2);
        $this->createField($amountsBlock, 'subtotal', 'Subtotal', 'currency', 1, false, true, false, true);
        $this->createField($amountsBlock, 'tax_amount', 'Tax Amount', 'currency', 2, false, true, false, true);
        $this->createField($amountsBlock, 'discount_amount', 'Discount', 'currency', 3, false, true, false, true);
        $this->createField($amountsBlock, 'total', 'Total', 'currency', 4, true, true, false, true);
        $this->createField($amountsBlock, 'amount_paid', 'Amount Paid', 'currency', 5, false, true, false, true);
        $this->createField($amountsBlock, 'balance_due', 'Balance Due', 'currency', 6, false, true, false, true);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 3);
        $this->createField($relatedBlock, 'organization_id', 'Organization', 'lookup', 1, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'contact_id', 'Contact', 'lookup', 2, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'deal_id', 'Deal', 'lookup', 3, false, true, true, true, false, ['target_module' => 'deals']);

        // Dates Block
        $datesBlock = $this->createBlock($module, 'Dates', 4);
        $this->createField($datesBlock, 'invoice_date', 'Invoice Date', 'date', 1, true, true, false, true);
        $this->createField($datesBlock, 'due_date', 'Due Date', 'date', 2, true, true, false, true);
        $this->createField($datesBlock, 'payment_date', 'Payment Date', 'date', 3, false, true, false, true);

        // Payment Block
        $paymentBlock = $this->createBlock($module, 'Payment', 5);
        $termsField = $this->createField($paymentBlock, 'payment_terms', 'Payment Terms', 'select', 1, false, true, false, true);
        $this->createFieldOptions($termsField, ['Due on Receipt', 'Net 15', 'Net 30', 'Net 45', 'Net 60']);
        $methodField = $this->createField($paymentBlock, 'payment_method', 'Payment Method', 'select', 2, false, true, false, true);
        $this->createFieldOptions($methodField, ['Bank Transfer', 'Credit Card', 'Check', 'Cash', 'PayPal', 'Other']);

        // Notes Block
        $notesBlock = $this->createBlock($module, 'Notes', 6);
        $this->createField($notesBlock, 'notes', 'Notes', 'textarea', 1, false, false, false, false);
        $this->createField($notesBlock, 'terms', 'Terms & Conditions', 'textarea', 2, false, false, false, false);

        $this->command->info('  - Created Invoices module');
        return $module;
    }

    private function createQuotesModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'quotes'],
            [
                'name' => 'Quotes',
                'singular_name' => 'Quote',
                'icon' => 'file-signature',
                'description' => 'Sales quotes and proposals',
                'is_active' => true,
                'display_order' => 10,
                'settings' => $this->defaultModuleSettings('quote_number'),
            ]
        );

        // Quote Details Block
        $detailsBlock = $this->createBlock($module, 'Quote Details', 1);
        $this->createField($detailsBlock, 'quote_number', 'Quote Number', 'text', 1, true, true, true, true, true);
        $this->createField($detailsBlock, 'subject', 'Subject', 'text', 2, true, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', 'select', 3, true, true, false, true);
        $this->createFieldOptions($statusField, ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired']);

        // Amounts Block
        $amountsBlock = $this->createBlock($module, 'Amounts', 2);
        $this->createField($amountsBlock, 'subtotal', 'Subtotal', 'currency', 1, false, true, false, true);
        $this->createField($amountsBlock, 'tax_amount', 'Tax Amount', 'currency', 2, false, true, false, true);
        $this->createField($amountsBlock, 'discount_percent', 'Discount (%)', 'percent', 3, false, true, false, true);
        $this->createField($amountsBlock, 'discount_amount', 'Discount Amount', 'currency', 4, false, true, false, true);
        $this->createField($amountsBlock, 'total', 'Total', 'currency', 5, true, true, false, true);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 3);
        $this->createField($relatedBlock, 'organization_id', 'Organization', 'lookup', 1, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'contact_id', 'Contact', 'lookup', 2, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'deal_id', 'Deal', 'lookup', 3, false, true, true, true, false, ['target_module' => 'deals']);

        // Dates Block
        $datesBlock = $this->createBlock($module, 'Dates', 4);
        $this->createField($datesBlock, 'quote_date', 'Quote Date', 'date', 1, false, true, false, true);
        $this->createField($datesBlock, 'valid_until', 'Valid Until', 'date', 2, false, true, false, true);
        $this->createField($datesBlock, 'accepted_date', 'Accepted Date', 'date', 3, false, true, false, true);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 5);
        $this->createField($additionalBlock, 'terms', 'Terms & Conditions', 'textarea', 1, false, false, false, false);
        $this->createField($additionalBlock, 'notes', 'Notes', 'textarea', 2, false, false, false, false);
        $this->createField($additionalBlock, 'assigned_to', 'Assigned To', 'lookup', 3, false, true, true, true, false, ['target_module' => 'users']);

        $this->command->info('  - Created Quotes module');
        return $module;
    }

    private function createEventsModule(): Module
    {
        $module = Module::updateOrCreate(
            ['api_name' => 'events'],
            [
                'name' => 'Events',
                'singular_name' => 'Event',
                'icon' => 'calendar',
                'description' => 'Calendar events and scheduling',
                'is_active' => true,
                'display_order' => 11,
                'settings' => $this->defaultModuleSettings('title'),
            ]
        );

        // Event Details Block
        $detailsBlock = $this->createBlock($module, 'Event Details', 1);
        $this->createField($detailsBlock, 'title', 'Title', 'text', 1, true, true, true, true);
        $this->createField($detailsBlock, 'description', 'Description', 'textarea', 2, false, false, false, false);
        $this->createField($detailsBlock, 'location', 'Location', 'text', 3, false, true, true, true);
        $typeField = $this->createField($detailsBlock, 'event_type', 'Event Type', 'select', 4, false, true, false, true);
        $this->createFieldOptions($typeField, ['Meeting', 'Call', 'Webinar', 'Conference', 'Personal', 'Other']);

        // Timing Block
        $timingBlock = $this->createBlock($module, 'Timing', 2);
        $this->createField($timingBlock, 'start_datetime', 'Start', 'datetime', 1, true, true, false, true);
        $this->createField($timingBlock, 'end_datetime', 'End', 'datetime', 2, true, true, false, true);
        $this->createField($timingBlock, 'all_day', 'All Day', 'checkbox', 3, false, false, false, true);
        $timezoneField = $this->createField($timingBlock, 'timezone', 'Timezone', 'select', 4, false, false, false, true);
        $this->createTimezoneOptions($timezoneField);

        // Recurrence Block
        $recurrenceBlock = $this->createBlock($module, 'Recurrence', 3);
        $this->createField($recurrenceBlock, 'is_recurring', 'Recurring', 'checkbox', 1, false, false, false, true);
        $this->createField($recurrenceBlock, 'recurrence_rule', 'Recurrence Rule', 'text', 2, false, false, false, true);
        $this->createField($recurrenceBlock, 'recurrence_end_date', 'Repeat Until', 'date', 3, false, false, false, true);

        // Attendees Block
        $attendeesBlock = $this->createBlock($module, 'Attendees', 4);
        $this->createField($attendeesBlock, 'organizer_id', 'Organizer', 'lookup', 1, false, true, true, true, false, ['target_module' => 'users']);
        $this->createField($attendeesBlock, 'external_attendees', 'External Attendees', 'textarea', 2, false, false, false, false);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 5);
        $this->createField($relatedBlock, 'contact_id', 'Contact', 'lookup', 1, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'organization_id', 'Organization', 'lookup', 2, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'deal_id', 'Deal', 'lookup', 3, false, true, true, true, false, ['target_module' => 'deals']);

        // Reminders Block
        $remindersBlock = $this->createBlock($module, 'Reminders', 6);
        $reminderField = $this->createField($remindersBlock, 'reminder_minutes', 'Reminder', 'select', 1, false, false, false, true);
        $this->createFieldOptions($reminderField, ['None', '5 minutes', '10 minutes', '15 minutes', '30 minutes', '1 hour', '1 day']);
        $this->createField($remindersBlock, 'reminder_sent', 'Reminder Sent', 'checkbox', 2, false, false, false, true);

        $this->command->info('  - Created Events module');
        return $module;
    }

    // Helper methods

    private function defaultModuleSettings(string $recordNameField): array
    {
        return [
            'has_import' => true,
            'has_export' => true,
            'has_mass_actions' => true,
            'has_comments' => true,
            'has_attachments' => true,
            'has_activity_log' => true,
            'has_custom_views' => true,
            'record_name_field' => $recordNameField,
            'additional_settings' => [],
        ];
    }

    private function createBlock(Module $module, string $name, int $order): Block
    {
        return Block::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => $name,
            ],
            [
                'type' => 'section',
                'display_order' => $order,
                'settings' => [],
            ]
        );
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
        bool $sortable = true,
        bool $unique = false,
        array $settings = []
    ): Field {
        return Field::firstOrCreate(
            [
                'module_id' => $block->module_id,
                'api_name' => $apiName,
            ],
            [
                'block_id' => $block->id,
                'label' => $label,
                'type' => $type,
                'description' => null,
                'help_text' => null,
                'placeholder' => null,
                'is_required' => $required,
                'is_unique' => $unique,
                'is_searchable' => $searchable,
                'is_filterable' => $filterable,
                'is_sortable' => $sortable,
                'default_value' => null,
                'display_order' => $order,
                'width' => 100,
                'validation_rules' => [],
                'settings' => array_merge(['additional_settings' => []], $settings),
                'conditional_visibility' => null,
                'field_dependency' => null,
                'formula_definition' => null,
            ]
        );
    }

    private function createFieldOptions(Field $field, array $options): void
    {
        foreach ($options as $index => $option) {
            FieldOption::firstOrCreate(
                [
                    'field_id' => $field->id,
                    'value' => strtolower(str_replace([' ', '&', '/'], ['_', '', '_'], $option)),
                ],
                [
                    'label' => $option,
                    'is_active' => true,
                    'display_order' => $index + 1,
                ]
            );
        }
    }

    private function createCountryOptions(Field $field): void
    {
        $countries = [
            'United States', 'United Kingdom', 'Canada', 'Australia', 'Germany',
            'France', 'Japan', 'China', 'India', 'Brazil', 'Mexico', 'Spain',
            'Italy', 'Netherlands', 'Sweden', 'Switzerland', 'Singapore',
            'South Korea', 'New Zealand', 'Ireland'
        ];
        $this->createFieldOptions($field, $countries);
    }

    private function createTimezoneOptions(Field $field): void
    {
        $timezones = [
            'UTC', 'America/New_York', 'America/Chicago', 'America/Denver',
            'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Europe/Berlin',
            'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Singapore', 'Australia/Sydney'
        ];
        $this->createFieldOptions($field, $timezones);
    }
}
