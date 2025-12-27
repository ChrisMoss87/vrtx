<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Modules\Entities\Block;
use App\Domain\Modules\Entities\Field;
use App\Domain\Modules\Entities\FieldOption;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\FieldOptionRepositoryInterface;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\BlockType;
use App\Domain\Modules\ValueObjects\FieldSettings;
use App\Domain\Modules\ValueObjects\FieldType;
use App\Domain\Modules\ValueObjects\ModuleSettings;
use App\Domain\Modules\ValueObjects\ValidationRules;
use Illuminate\Database\Seeder;

/**
 * Seeds the default modules for a new tenant.
 * Creates the core CRM modules without sample data.
 */
class DefaultModulesSeeder extends Seeder
{
    public function __construct(
        private readonly ModuleRepositoryInterface $moduleRepository,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly FieldRepositoryInterface $fieldRepository,
        private readonly FieldOptionRepositoryInterface $fieldOptionRepository,
    ) {}

    public function run(): void
    {
        $this->command->info('Creating default modules...');

        $this->createContactsModule();
        $this->createOrganizationsModule();
        $this->createDealsModule();
        $this->createLeadsModule();
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
        $module = $this->findOrCreateModule(
            'contacts',
            'Contacts',
            'Contact',
            'users',
            'People you interact with - leads, customers, partners',
            1,
            'first_name'
        );

        // Personal Information Block
        $personalBlock = $this->createBlock($module, 'Personal Information', 1);
        $this->createField($personalBlock, 'first_name', 'First Name', FieldType::TEXT, 1, true, true, true, true);
        $this->createField($personalBlock, 'last_name', 'Last Name', FieldType::TEXT, 2, true, true, true, true);
        $this->createField($personalBlock, 'email', 'Email', FieldType::EMAIL, 3, false, true, true, true, true);
        $this->createField($personalBlock, 'phone', 'Phone', FieldType::PHONE, 4, false, true, false, true);
        $this->createField($personalBlock, 'mobile', 'Mobile', FieldType::PHONE, 5, false, true, false, true);
        $this->createField($personalBlock, 'date_of_birth', 'Date of Birth', FieldType::DATE, 6, false, false, false, true);

        // Work Information Block
        $workBlock = $this->createBlock($module, 'Work Information', 2);
        $this->createField($workBlock, 'organization_id', 'Organization', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($workBlock, 'job_title', 'Job Title', FieldType::TEXT, 2, false, true, true, true);
        $this->createField($workBlock, 'department', 'Department', FieldType::TEXT, 3, false, true, true, true);
        $this->createField($workBlock, 'linkedin_url', 'LinkedIn', FieldType::URL, 4, false, false, false, true);
        $this->createField($workBlock, 'twitter_handle', 'Twitter', FieldType::TEXT, 5, false, false, false, true);

        // Address Block
        $addressBlock = $this->createBlock($module, 'Address', 3);
        $this->createField($addressBlock, 'street', 'Street', FieldType::TEXT, 1, false, false, false, true);
        $this->createField($addressBlock, 'city', 'City', FieldType::TEXT, 2, false, true, true, true);
        $this->createField($addressBlock, 'state', 'State/Province', FieldType::TEXT, 3, false, true, true, true);
        $this->createField($addressBlock, 'postal_code', 'Postal Code', FieldType::TEXT, 4, false, true, false, true);
        $countryField = $this->createField($addressBlock, 'country', 'Country', FieldType::SELECT, 5, false, true, true, true);
        $this->createCountryOptions($countryField);

        // Status & Source Block
        $statusBlock = $this->createBlock($module, 'Status & Source', 4);
        $statusField = $this->createField($statusBlock, 'status', 'Status', FieldType::SELECT, 1, false, true, false, true);
        $this->createFieldOptions($statusField, ['Lead', 'Prospect', 'Customer', 'Partner', 'Inactive']);
        $sourceField = $this->createField($statusBlock, 'lead_source', 'Lead Source', FieldType::SELECT, 2, false, true, false, true);
        $this->createFieldOptions($sourceField, ['Website', 'Referral', 'Social Media', 'Email Campaign', 'Cold Call', 'Trade Show', 'Partner', 'Other']);
        $this->createField($statusBlock, 'assigned_to', 'Assigned To', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'users']);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 5);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', FieldType::MULTISELECT, 1, false, true, false, true);
        $this->createFieldOptions($tagsField, ['VIP', 'Decision Maker', 'Technical', 'Influencer', 'Champion']);
        $this->createField($additionalBlock, 'do_not_contact', 'Do Not Contact', FieldType::CHECKBOX, 2, false, true, false, true);
        $this->createField($additionalBlock, 'notes', 'Notes', FieldType::TEXTAREA, 3, false, false, false, false);

        $this->command->info('  - Created Contacts module');
        return $module;
    }

    private function createOrganizationsModule(): Module
    {
        $module = $this->findOrCreateModule(
            'organizations',
            'Organizations',
            'Organization',
            'building-2',
            'Companies, businesses, and entities',
            2,
            'name'
        );

        // Basic Information Block
        $basicBlock = $this->createBlock($module, 'Basic Information', 1);
        $this->createField($basicBlock, 'name', 'Organization Name', FieldType::TEXT, 1, true, true, true, true);
        $this->createField($basicBlock, 'website', 'Website', FieldType::URL, 2, false, true, false, true);
        $industryField = $this->createField($basicBlock, 'industry', 'Industry', FieldType::SELECT, 3, false, true, false, true);
        $this->createFieldOptions($industryField, [
            'Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing',
            'Education', 'Real Estate', 'Consulting', 'Media', 'Transportation',
            'Energy', 'Agriculture', 'Hospitality', 'Legal', 'Non-Profit', 'Government', 'Other'
        ]);
        $sizeField = $this->createField($basicBlock, 'employee_count', 'Employee Count', FieldType::SELECT, 4, false, true, false, true);
        $this->createFieldOptions($sizeField, ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+']);

        // Contact Details Block
        $contactBlock = $this->createBlock($module, 'Contact Details', 2);
        $this->createField($contactBlock, 'phone', 'Phone', FieldType::PHONE, 1, false, true, false, true);
        $this->createField($contactBlock, 'email', 'Email', FieldType::EMAIL, 2, false, true, true, true);
        $this->createField($contactBlock, 'fax', 'Fax', FieldType::PHONE, 3, false, false, false, true);

        // Address Block
        $addressBlock = $this->createBlock($module, 'Address', 3);
        $this->createField($addressBlock, 'street', 'Street', FieldType::TEXT, 1, false, false, false, true);
        $this->createField($addressBlock, 'city', 'City', FieldType::TEXT, 2, false, true, true, true);
        $this->createField($addressBlock, 'state', 'State/Province', FieldType::TEXT, 3, false, true, true, true);
        $this->createField($addressBlock, 'postal_code', 'Postal Code', FieldType::TEXT, 4, false, true, false, true);
        $countryField = $this->createField($addressBlock, 'country', 'Country', FieldType::SELECT, 5, false, true, true, true);
        $this->createCountryOptions($countryField);

        // Business Details Block
        $businessBlock = $this->createBlock($module, 'Business Details', 4);
        $typeField = $this->createField($businessBlock, 'type', 'Type', FieldType::SELECT, 1, false, true, false, true);
        $this->createFieldOptions($typeField, ['Customer', 'Prospect', 'Partner', 'Vendor', 'Competitor']);
        $this->createField($businessBlock, 'annual_revenue', 'Annual Revenue', FieldType::CURRENCY, 2, false, true, false, true);
        $this->createField($businessBlock, 'assigned_to', 'Assigned To', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'users']);
        $this->createField($businessBlock, 'parent_organization_id', 'Parent Organization', FieldType::LOOKUP, 4, false, true, false, true, false, ['target_module' => 'organizations']);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 5);
        $this->createField($additionalBlock, 'description', 'Description', FieldType::TEXTAREA, 1, false, false, false, false);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', FieldType::MULTISELECT, 2, false, true, false, true);
        $this->createFieldOptions($tagsField, ['Enterprise', 'SMB', 'Startup', 'Strategic', 'At Risk']);

        $this->command->info('  - Created Organizations module');
        return $module;
    }

    private function createDealsModule(): Module
    {
        $module = $this->findOrCreateModule(
            'deals',
            'Deals',
            'Deal',
            'handshake',
            'Sales opportunities and revenue tracking',
            3,
            'name'
        );

        // Deal Information Block
        $dealBlock = $this->createBlock($module, 'Deal Information', 1);
        $this->createField($dealBlock, 'name', 'Deal Name', FieldType::TEXT, 1, true, true, true, true);
        $this->createField($dealBlock, 'amount', 'Amount', FieldType::CURRENCY, 2, false, true, false, true);
        $this->createField($dealBlock, 'probability', 'Probability', FieldType::PERCENT, 3, false, true, false, true);
        $this->createField($dealBlock, 'expected_revenue', 'Expected Revenue', FieldType::CURRENCY, 4, false, true, false, true, false, [
            'formula' => 'amount * probability / 100',
            'is_formula' => true
        ]);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 2);
        $this->createField($relatedBlock, 'organization_id', 'Organization', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'contact_id', 'Primary Contact', FieldType::LOOKUP, 2, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'assigned_to', 'Assigned To', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'users']);

        // Stage & Timeline Block
        $stageBlock = $this->createBlock($module, 'Stage & Timeline', 3);
        $stageField = $this->createField($stageBlock, 'stage', 'Stage', FieldType::SELECT, 1, true, true, false, true);
        $this->createFieldOptions($stageField, ['Prospecting', 'Qualification', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost']);
        $this->createField($stageBlock, 'close_date', 'Expected Close Date', FieldType::DATE, 2, false, true, false, true);
        $sourceField = $this->createField($stageBlock, 'source', 'Source', FieldType::SELECT, 3, false, true, false, true);
        $this->createFieldOptions($sourceField, ['Website', 'Referral', 'Partner', 'Outbound', 'Inbound', 'Event', 'Other']);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 4);
        $this->createField($additionalBlock, 'description', 'Description', FieldType::TEXTAREA, 1, false, false, false, false);
        $this->createField($additionalBlock, 'next_step', 'Next Step', FieldType::TEXT, 2, false, false, false, false);
        $this->createField($additionalBlock, 'competitors', 'Competitors', FieldType::TEXT, 3, false, false, false, false);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', FieldType::MULTISELECT, 4, false, true, false, true);
        $this->createFieldOptions($tagsField, ['High Value', 'Strategic', 'Quick Win', 'Renewal', 'Expansion']);

        $this->command->info('  - Created Deals module');
        return $module;
    }

    private function createLeadsModule(): Module
    {
        $module = $this->findOrCreateModule(
            'leads',
            'Leads',
            'Lead',
            'user-plus',
            'Potential customers and sales opportunities',
            0,
            'first_name'
        );

        // Lead Information Block
        $infoBlock = $this->createBlock($module, 'Lead Information', 1);
        $this->createField($infoBlock, 'first_name', 'First Name', FieldType::TEXT, 1, true, true, true, true);
        $this->createField($infoBlock, 'last_name', 'Last Name', FieldType::TEXT, 2, true, true, true, true);
        $this->createField($infoBlock, 'email', 'Email', FieldType::EMAIL, 3, false, true, true, true, true);
        $this->createField($infoBlock, 'phone', 'Phone', FieldType::PHONE, 4, false, true, false, true);
        $this->createField($infoBlock, 'mobile', 'Mobile', FieldType::PHONE, 5, false, true, false, true);

        // Company Information Block
        $companyBlock = $this->createBlock($module, 'Company Information', 2);
        $this->createField($companyBlock, 'company', 'Company', FieldType::TEXT, 1, false, true, true, true);
        $this->createField($companyBlock, 'title', 'Title', FieldType::TEXT, 2, false, true, true, true);
        $this->createField($companyBlock, 'website', 'Website', FieldType::URL, 3, false, false, false, true);
        $industryField = $this->createField($companyBlock, 'industry', 'Industry', FieldType::SELECT, 4, false, true, true, true);
        $this->createFieldOptions($industryField, ['Technology', 'Finance', 'Healthcare', 'Manufacturing', 'Retail', 'Education', 'Government', 'Other']);
        $employeeField = $this->createField($companyBlock, 'number_of_employees', 'Number of Employees', FieldType::SELECT, 5, false, true, false, true);
        $this->createFieldOptions($employeeField, ['1-10', '11-50', '51-200', '201-500', '501-1000', '1001-5000', '5000+']);
        $this->createField($companyBlock, 'annual_revenue', 'Annual Revenue', FieldType::CURRENCY, 6, false, true, false, true);

        // Lead Status Block
        $statusBlock = $this->createBlock($module, 'Lead Status', 3);
        $statusField = $this->createField($statusBlock, 'status', 'Lead Status', FieldType::SELECT, 1, true, true, false, true);
        $this->createFieldOptions($statusField, ['New', 'Contacted', 'Qualified', 'Unqualified', 'Converted']);
        $sourceField = $this->createField($statusBlock, 'source', 'Lead Source', FieldType::SELECT, 2, false, true, false, true);
        $this->createFieldOptions($sourceField, ['Website', 'Referral', 'Trade Show', 'Cold Call', 'Social Media', 'Advertisement', 'Webinar', 'Partner', 'Other']);
        $ratingField = $this->createField($statusBlock, 'rating', 'Rating', FieldType::SELECT, 3, false, true, false, true);
        $this->createFieldOptions($ratingField, ['Hot', 'Warm', 'Cold']);
        $this->createField($statusBlock, 'assigned_to', 'Assigned To', FieldType::LOOKUP, 4, false, true, true, true, false, ['target_module' => 'users']);

        // Address Block
        $addressBlock = $this->createBlock($module, 'Address', 4);
        $this->createField($addressBlock, 'street', 'Street', FieldType::TEXT, 1, false, false, false, true);
        $this->createField($addressBlock, 'city', 'City', FieldType::TEXT, 2, false, true, true, true);
        $this->createField($addressBlock, 'state', 'State/Province', FieldType::TEXT, 3, false, true, true, true);
        $this->createField($addressBlock, 'postal_code', 'Postal Code', FieldType::TEXT, 4, false, true, false, true);
        $countryField = $this->createField($addressBlock, 'country', 'Country', FieldType::SELECT, 5, false, true, true, true);
        $this->createCountryOptions($countryField);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 5);
        $this->createField($additionalBlock, 'description', 'Description', FieldType::TEXTAREA, 1, false, false, false, false);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', FieldType::MULTISELECT, 2, false, true, false, true);
        $this->createFieldOptions($tagsField, ['High Priority', 'Follow Up', 'Demo Requested', 'Budget Approved', 'Decision Maker']);

        $this->command->info('  - Created Leads module');
        return $module;
    }

    private function createTasksModule(): Module
    {
        $module = $this->findOrCreateModule(
            'tasks',
            'Tasks',
            'Task',
            'check-square',
            'To-do items and action items',
            4,
            'subject'
        );

        // Task Details Block
        $detailsBlock = $this->createBlock($module, 'Task Details', 1);
        $this->createField($detailsBlock, 'subject', 'Subject', FieldType::TEXT, 1, true, true, true, true);
        $this->createField($detailsBlock, 'description', 'Description', FieldType::TEXTAREA, 2, false, false, false, false);
        $priorityField = $this->createField($detailsBlock, 'priority', 'Priority', FieldType::SELECT, 3, false, true, false, true);
        $this->createFieldOptions($priorityField, ['Low', 'Normal', 'High', 'Urgent']);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', FieldType::SELECT, 4, true, true, false, true);
        $this->createFieldOptions($statusField, ['Not Started', 'In Progress', 'Completed', 'Waiting', 'Deferred']);

        // Dates & Assignment Block
        $datesBlock = $this->createBlock($module, 'Dates & Assignment', 2);
        $this->createField($datesBlock, 'due_date', 'Due Date', FieldType::DATE, 1, false, true, false, true);
        $this->createField($datesBlock, 'due_time', 'Due Time', FieldType::TIME, 2, false, false, false, true);
        $this->createField($datesBlock, 'reminder_date', 'Reminder', FieldType::DATETIME, 3, false, false, false, true);
        $this->createField($datesBlock, 'assigned_to', 'Assigned To', FieldType::LOOKUP, 4, false, true, true, true, false, ['target_module' => 'users']);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 3);
        $relatedTypeField = $this->createField($relatedBlock, 'related_to_type', 'Related To Type', FieldType::SELECT, 1, false, true, false, true);
        $this->createFieldOptions($relatedTypeField, ['Contact', 'Organization', 'Deal', 'Case']);
        $this->createField($relatedBlock, 'related_to_id', 'Related To', FieldType::NUMBER, 2, false, true, false, true);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 4);
        $this->createField($additionalBlock, 'is_recurring', 'Recurring', FieldType::CHECKBOX, 1, false, true, false, true);
        $recurrenceField = $this->createField($additionalBlock, 'recurrence_pattern', 'Recurrence Pattern', FieldType::SELECT, 2, false, false, false, true);
        $this->createFieldOptions($recurrenceField, ['Daily', 'Weekly', 'Monthly', 'Yearly']);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', FieldType::MULTISELECT, 3, false, true, false, true);
        $this->createFieldOptions($tagsField, ['Follow-up', 'Meeting Prep', 'Research', 'Admin', 'Urgent']);

        $this->command->info('  - Created Tasks module');
        return $module;
    }

    private function createActivitiesModule(): Module
    {
        $module = $this->findOrCreateModule(
            'activities',
            'Activities',
            'Activity',
            'activity',
            'Calls, meetings, emails, and interactions',
            5,
            'subject'
        );

        // Activity Details Block
        $detailsBlock = $this->createBlock($module, 'Activity Details', 1);
        $this->createField($detailsBlock, 'subject', 'Subject', FieldType::TEXT, 1, true, true, true, true);
        $typeField = $this->createField($detailsBlock, 'type', 'Type', FieldType::SELECT, 2, true, true, false, true);
        $this->createFieldOptions($typeField, ['Call', 'Meeting', 'Email', 'Note', 'Demo', 'Lunch', 'Other']);
        $this->createField($detailsBlock, 'description', 'Description', FieldType::TEXTAREA, 3, false, false, false, false);

        // Timing Block
        $timingBlock = $this->createBlock($module, 'Timing', 2);
        $this->createField($timingBlock, 'start_datetime', 'Start', FieldType::DATETIME, 1, false, true, false, true);
        $this->createField($timingBlock, 'end_datetime', 'End', FieldType::DATETIME, 2, false, true, false, true);
        $this->createField($timingBlock, 'duration_minutes', 'Duration (minutes)', FieldType::NUMBER, 3, false, true, false, true);
        $this->createField($timingBlock, 'all_day', 'All Day', FieldType::CHECKBOX, 4, false, false, false, true);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 3);
        $this->createField($relatedBlock, 'contact_id', 'Contact', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'organization_id', 'Organization', FieldType::LOOKUP, 2, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'deal_id', 'Deal', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'deals']);

        // Outcome Block
        $outcomeBlock = $this->createBlock($module, 'Outcome', 4);
        $outcomeField = $this->createField($outcomeBlock, 'outcome', 'Outcome', FieldType::SELECT, 1, false, true, false, true);
        $this->createFieldOptions($outcomeField, ['Completed', 'No Answer', 'Left Message', 'Rescheduled', 'Cancelled']);
        $this->createField($outcomeBlock, 'next_action', 'Next Action', FieldType::TEXT, 2, false, false, false, false);
        $this->createField($outcomeBlock, 'assigned_to', 'Assigned To', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'users']);

        $this->command->info('  - Created Activities module');
        return $module;
    }

    private function createNotesModule(): Module
    {
        $module = $this->findOrCreateModule(
            'notes',
            'Notes',
            'Note',
            'file-text',
            'Internal notes and documentation',
            6,
            'title'
        );

        // Note Content Block
        $contentBlock = $this->createBlock($module, 'Note Content', 1);
        $this->createField($contentBlock, 'title', 'Title', FieldType::TEXT, 1, true, true, true, true);
        $this->createField($contentBlock, 'content', 'Content', FieldType::TEXTAREA, 2, true, false, false, false);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 2);
        $relatedTypeField = $this->createField($relatedBlock, 'related_to_type', 'Related To Type', FieldType::SELECT, 1, false, true, false, true);
        $this->createFieldOptions($relatedTypeField, ['Contact', 'Organization', 'Deal', 'Case', 'Task']);
        $this->createField($relatedBlock, 'related_to_id', 'Related To', FieldType::NUMBER, 2, false, true, false, true);

        // Metadata Block
        $metadataBlock = $this->createBlock($module, 'Metadata', 3);
        $this->createField($metadataBlock, 'is_pinned', 'Pinned', FieldType::CHECKBOX, 1, false, true, false, true);
        $tagsField = $this->createField($metadataBlock, 'tags', 'Tags', FieldType::MULTISELECT, 2, false, true, false, true);
        $this->createFieldOptions($tagsField, ['Important', 'Action Required', 'Reference', 'Meeting Notes', 'Strategy']);
        $visibilityField = $this->createField($metadataBlock, 'visibility', 'Visibility', FieldType::SELECT, 3, false, true, false, true);
        $this->createFieldOptions($visibilityField, ['Everyone', 'Team Only', 'Private']);

        $this->command->info('  - Created Notes module');
        return $module;
    }

    private function createCasesModule(): Module
    {
        $module = $this->findOrCreateModule(
            'cases',
            'Cases',
            'Case',
            'headset',
            'Customer support tickets and issues',
            7,
            'case_number'
        );

        // Case Details Block
        $detailsBlock = $this->createBlock($module, 'Case Details', 1);
        $this->createField($detailsBlock, 'case_number', 'Case Number', FieldType::TEXT, 1, true, true, true, true, true);
        $this->createField($detailsBlock, 'subject', 'Subject', FieldType::TEXT, 2, true, true, true, true);
        $this->createField($detailsBlock, 'description', 'Description', FieldType::TEXTAREA, 3, false, false, false, false);
        $typeField = $this->createField($detailsBlock, 'type', 'Type', FieldType::SELECT, 4, false, true, false, true);
        $this->createFieldOptions($typeField, ['Question', 'Problem', 'Feature Request', 'Bug']);

        // Classification Block
        $classificationBlock = $this->createBlock($module, 'Classification', 2);
        $statusField = $this->createField($classificationBlock, 'status', 'Status', FieldType::SELECT, 1, true, true, false, true);
        $this->createFieldOptions($statusField, ['New', 'Open', 'In Progress', 'Waiting on Customer', 'Resolved', 'Closed']);
        $priorityField = $this->createField($classificationBlock, 'priority', 'Priority', FieldType::SELECT, 2, false, true, false, true);
        $this->createFieldOptions($priorityField, ['Low', 'Medium', 'High', 'Critical']);
        $severityField = $this->createField($classificationBlock, 'severity', 'Severity', FieldType::SELECT, 3, false, true, false, true);
        $this->createFieldOptions($severityField, ['Minor', 'Major', 'Critical', 'Blocker']);

        // Customer Information Block
        $customerBlock = $this->createBlock($module, 'Customer Information', 3);
        $this->createField($customerBlock, 'contact_id', 'Contact', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($customerBlock, 'organization_id', 'Organization', FieldType::LOOKUP, 2, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($customerBlock, 'email', 'Email', FieldType::EMAIL, 3, false, true, true, true);
        $this->createField($customerBlock, 'phone', 'Phone', FieldType::PHONE, 4, false, true, false, true);

        // Assignment & Resolution Block
        $assignmentBlock = $this->createBlock($module, 'Assignment & Resolution', 4);
        $this->createField($assignmentBlock, 'assigned_to', 'Assigned To', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'users']);
        $teamField = $this->createField($assignmentBlock, 'team', 'Team', FieldType::SELECT, 2, false, true, false, true);
        $this->createFieldOptions($teamField, ['Support', 'Engineering', 'Sales', 'Billing']);
        $this->createField($assignmentBlock, 'resolution', 'Resolution', FieldType::TEXTAREA, 3, false, false, false, false);
        $this->createField($assignmentBlock, 'resolution_date', 'Resolution Date', FieldType::DATETIME, 4, false, true, false, true);
        $this->createField($assignmentBlock, 'first_response_date', 'First Response', FieldType::DATETIME, 5, false, true, false, true);

        // SLA Tracking Block
        $slaBlock = $this->createBlock($module, 'SLA Tracking', 5);
        $this->createField($slaBlock, 'sla_due_date', 'SLA Due Date', FieldType::DATETIME, 1, false, true, false, true);
        $this->createField($slaBlock, 'escalated', 'Escalated', FieldType::CHECKBOX, 2, false, true, false, true);
        $this->createField($slaBlock, 'escalation_date', 'Escalation Date', FieldType::DATETIME, 3, false, true, false, true);

        $this->command->info('  - Created Cases module');
        return $module;
    }

    private function createProductsModule(): Module
    {
        $module = $this->findOrCreateModule(
            'products',
            'Products',
            'Product',
            'package',
            'Products and services catalog',
            8,
            'name'
        );

        // Product Information Block
        $infoBlock = $this->createBlock($module, 'Product Information', 1);
        $this->createField($infoBlock, 'name', 'Product Name', FieldType::TEXT, 1, true, true, true, true);
        $this->createField($infoBlock, 'sku', 'SKU', FieldType::TEXT, 2, false, true, true, true, true);
        $this->createField($infoBlock, 'description', 'Description', FieldType::TEXTAREA, 3, false, false, false, false);
        $categoryField = $this->createField($infoBlock, 'category', 'Category', FieldType::SELECT, 4, false, true, false, true);
        $this->createFieldOptions($categoryField, ['Software', 'Hardware', 'Services', 'Consulting', 'Training', 'Support', 'Subscription', 'Add-on']);

        // Pricing Block
        $pricingBlock = $this->createBlock($module, 'Pricing', 2);
        $this->createField($pricingBlock, 'unit_price', 'Unit Price', FieldType::CURRENCY, 1, true, true, false, true);
        $this->createField($pricingBlock, 'cost', 'Cost', FieldType::CURRENCY, 2, false, true, false, true);
        $this->createField($pricingBlock, 'margin', 'Margin (%)', FieldType::PERCENT, 3, false, true, false, true);
        $this->createField($pricingBlock, 'tax_rate', 'Tax Rate (%)', FieldType::PERCENT, 4, false, true, false, true);

        // Inventory Block
        $inventoryBlock = $this->createBlock($module, 'Inventory', 3);
        $this->createField($inventoryBlock, 'quantity_in_stock', 'Quantity in Stock', FieldType::NUMBER, 1, false, true, false, true);
        $this->createField($inventoryBlock, 'reorder_level', 'Reorder Level', FieldType::NUMBER, 2, false, true, false, true);
        $this->createField($inventoryBlock, 'is_active', 'Active', FieldType::CHECKBOX, 3, false, true, false, true);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 4);
        $this->createField($additionalBlock, 'vendor', 'Vendor', FieldType::TEXT, 1, false, true, true, true);
        $this->createField($additionalBlock, 'weight', 'Weight (kg)', FieldType::DECIMAL, 2, false, false, false, true);
        $this->createField($additionalBlock, 'dimensions', 'Dimensions', FieldType::TEXT, 3, false, false, false, true);
        $tagsField = $this->createField($additionalBlock, 'tags', 'Tags', FieldType::MULTISELECT, 4, false, true, false, true);
        $this->createFieldOptions($tagsField, ['Featured', 'New', 'Bestseller', 'Clearance', 'Limited']);

        $this->command->info('  - Created Products module');
        return $module;
    }

    private function createInvoicesModule(): Module
    {
        $module = $this->findOrCreateModule(
            'invoices',
            'Invoices',
            'Invoice',
            'file-text',
            'Customer invoices and billing',
            9,
            'invoice_number'
        );

        // Invoice Details Block
        $detailsBlock = $this->createBlock($module, 'Invoice Details', 1);
        $this->createField($detailsBlock, 'invoice_number', 'Invoice Number', FieldType::TEXT, 1, true, true, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', FieldType::SELECT, 2, true, true, false, true);
        $this->createFieldOptions($statusField, ['Draft', 'Sent', 'Paid', 'Overdue', 'Cancelled', 'Refunded']);

        // Amounts Block
        $amountsBlock = $this->createBlock($module, 'Amounts', 2);
        $this->createField($amountsBlock, 'subtotal', 'Subtotal', FieldType::CURRENCY, 1, false, true, false, true);
        $this->createField($amountsBlock, 'tax_amount', 'Tax Amount', FieldType::CURRENCY, 2, false, true, false, true);
        $this->createField($amountsBlock, 'discount_amount', 'Discount', FieldType::CURRENCY, 3, false, true, false, true);
        $this->createField($amountsBlock, 'total', 'Total', FieldType::CURRENCY, 4, true, true, false, true);
        $this->createField($amountsBlock, 'amount_paid', 'Amount Paid', FieldType::CURRENCY, 5, false, true, false, true);
        $this->createField($amountsBlock, 'balance_due', 'Balance Due', FieldType::CURRENCY, 6, false, true, false, true);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 3);
        $this->createField($relatedBlock, 'organization_id', 'Organization', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'contact_id', 'Contact', FieldType::LOOKUP, 2, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'deal_id', 'Deal', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'deals']);

        // Dates Block
        $datesBlock = $this->createBlock($module, 'Dates', 4);
        $this->createField($datesBlock, 'invoice_date', 'Invoice Date', FieldType::DATE, 1, true, true, false, true);
        $this->createField($datesBlock, 'due_date', 'Due Date', FieldType::DATE, 2, true, true, false, true);
        $this->createField($datesBlock, 'payment_date', 'Payment Date', FieldType::DATE, 3, false, true, false, true);

        // Payment Block
        $paymentBlock = $this->createBlock($module, 'Payment', 5);
        $termsField = $this->createField($paymentBlock, 'payment_terms', 'Payment Terms', FieldType::SELECT, 1, false, true, false, true);
        $this->createFieldOptions($termsField, ['Due on Receipt', 'Net 15', 'Net 30', 'Net 45', 'Net 60']);
        $methodField = $this->createField($paymentBlock, 'payment_method', 'Payment Method', FieldType::SELECT, 2, false, true, false, true);
        $this->createFieldOptions($methodField, ['Bank Transfer', 'Credit Card', 'Check', 'Cash', 'PayPal', 'Other']);

        // Notes Block
        $notesBlock = $this->createBlock($module, 'Notes', 6);
        $this->createField($notesBlock, 'notes', 'Notes', FieldType::TEXTAREA, 1, false, false, false, false);
        $this->createField($notesBlock, 'terms', 'Terms & Conditions', FieldType::TEXTAREA, 2, false, false, false, false);

        $this->command->info('  - Created Invoices module');
        return $module;
    }

    private function createQuotesModule(): Module
    {
        $module = $this->findOrCreateModule(
            'quotes',
            'Quotes',
            'Quote',
            'file-signature',
            'Sales quotes and proposals',
            10,
            'quote_number'
        );

        // Quote Details Block
        $detailsBlock = $this->createBlock($module, 'Quote Details', 1);
        $this->createField($detailsBlock, 'quote_number', 'Quote Number', FieldType::TEXT, 1, true, true, true, true, true);
        $this->createField($detailsBlock, 'subject', 'Subject', FieldType::TEXT, 2, true, true, true, true);
        $statusField = $this->createField($detailsBlock, 'status', 'Status', FieldType::SELECT, 3, true, true, false, true);
        $this->createFieldOptions($statusField, ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired']);

        // Amounts Block
        $amountsBlock = $this->createBlock($module, 'Amounts', 2);
        $this->createField($amountsBlock, 'subtotal', 'Subtotal', FieldType::CURRENCY, 1, false, true, false, true);
        $this->createField($amountsBlock, 'tax_amount', 'Tax Amount', FieldType::CURRENCY, 2, false, true, false, true);
        $this->createField($amountsBlock, 'discount_percent', 'Discount (%)', FieldType::PERCENT, 3, false, true, false, true);
        $this->createField($amountsBlock, 'discount_amount', 'Discount Amount', FieldType::CURRENCY, 4, false, true, false, true);
        $this->createField($amountsBlock, 'total', 'Total', FieldType::CURRENCY, 5, true, true, false, true);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 3);
        $this->createField($relatedBlock, 'organization_id', 'Organization', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'contact_id', 'Contact', FieldType::LOOKUP, 2, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'deal_id', 'Deal', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'deals']);

        // Dates Block
        $datesBlock = $this->createBlock($module, 'Dates', 4);
        $this->createField($datesBlock, 'quote_date', 'Quote Date', FieldType::DATE, 1, false, true, false, true);
        $this->createField($datesBlock, 'valid_until', 'Valid Until', FieldType::DATE, 2, false, true, false, true);
        $this->createField($datesBlock, 'accepted_date', 'Accepted Date', FieldType::DATE, 3, false, true, false, true);

        // Additional Block
        $additionalBlock = $this->createBlock($module, 'Additional', 5);
        $this->createField($additionalBlock, 'terms', 'Terms & Conditions', FieldType::TEXTAREA, 1, false, false, false, false);
        $this->createField($additionalBlock, 'notes', 'Notes', FieldType::TEXTAREA, 2, false, false, false, false);
        $this->createField($additionalBlock, 'assigned_to', 'Assigned To', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'users']);

        $this->command->info('  - Created Quotes module');
        return $module;
    }

    private function createEventsModule(): Module
    {
        $module = $this->findOrCreateModule(
            'events',
            'Events',
            'Event',
            'calendar',
            'Calendar events and scheduling',
            11,
            'title'
        );

        // Event Details Block
        $detailsBlock = $this->createBlock($module, 'Event Details', 1);
        $this->createField($detailsBlock, 'title', 'Title', FieldType::TEXT, 1, true, true, true, true);
        $this->createField($detailsBlock, 'description', 'Description', FieldType::TEXTAREA, 2, false, false, false, false);
        $this->createField($detailsBlock, 'location', 'Location', FieldType::TEXT, 3, false, true, true, true);
        $typeField = $this->createField($detailsBlock, 'event_type', 'Event Type', FieldType::SELECT, 4, false, true, false, true);
        $this->createFieldOptions($typeField, ['Meeting', 'Call', 'Webinar', 'Conference', 'Personal', 'Other']);

        // Timing Block
        $timingBlock = $this->createBlock($module, 'Timing', 2);
        $this->createField($timingBlock, 'start_datetime', 'Start', FieldType::DATETIME, 1, true, true, false, true);
        $this->createField($timingBlock, 'end_datetime', 'End', FieldType::DATETIME, 2, true, true, false, true);
        $this->createField($timingBlock, 'all_day', 'All Day', FieldType::CHECKBOX, 3, false, false, false, true);
        $timezoneField = $this->createField($timingBlock, 'timezone', 'Timezone', FieldType::SELECT, 4, false, false, false, true);
        $this->createTimezoneOptions($timezoneField);

        // Recurrence Block
        $recurrenceBlock = $this->createBlock($module, 'Recurrence', 3);
        $this->createField($recurrenceBlock, 'is_recurring', 'Recurring', FieldType::CHECKBOX, 1, false, false, false, true);
        $this->createField($recurrenceBlock, 'recurrence_rule', 'Recurrence Rule', FieldType::TEXT, 2, false, false, false, true);
        $this->createField($recurrenceBlock, 'recurrence_end_date', 'Repeat Until', FieldType::DATE, 3, false, false, false, true);

        // Attendees Block
        $attendeesBlock = $this->createBlock($module, 'Attendees', 4);
        $this->createField($attendeesBlock, 'organizer_id', 'Organizer', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'users']);
        $this->createField($attendeesBlock, 'external_attendees', 'External Attendees', FieldType::TEXTAREA, 2, false, false, false, false);

        // Related Records Block
        $relatedBlock = $this->createBlock($module, 'Related Records', 5);
        $this->createField($relatedBlock, 'contact_id', 'Contact', FieldType::LOOKUP, 1, false, true, true, true, false, ['target_module' => 'contacts']);
        $this->createField($relatedBlock, 'organization_id', 'Organization', FieldType::LOOKUP, 2, false, true, true, true, false, ['target_module' => 'organizations']);
        $this->createField($relatedBlock, 'deal_id', 'Deal', FieldType::LOOKUP, 3, false, true, true, true, false, ['target_module' => 'deals']);

        // Reminders Block
        $remindersBlock = $this->createBlock($module, 'Reminders', 6);
        $reminderField = $this->createField($remindersBlock, 'reminder_minutes', 'Reminder', FieldType::SELECT, 1, false, false, false, true);
        $this->createFieldOptions($reminderField, ['None', '5 minutes', '10 minutes', '15 minutes', '30 minutes', '1 hour', '1 day']);
        $this->createField($remindersBlock, 'reminder_sent', 'Reminder Sent', FieldType::CHECKBOX, 2, false, false, false, true);

        $this->command->info('  - Created Events module');
        return $module;
    }

    // Helper methods

    private function defaultModuleSettings(string $recordNameField): ModuleSettings
    {
        return new ModuleSettings(
            hasImport: true,
            hasExport: true,
            hasMassActions: true,
            hasComments: true,
            hasAttachments: true,
            hasActivityLog: true,
            hasCustomViews: true,
            recordNameField: $recordNameField,
            additionalSettings: [],
        );
    }

    private function findOrCreateModule(
        string $apiName,
        string $name,
        string $singularName,
        string $icon,
        string $description,
        int $displayOrder,
        string $recordNameField
    ): Module {
        $existing = $this->moduleRepository->findByApiName($apiName);
        if ($existing !== null) {
            return $existing;
        }

        $module = Module::create(
            name: $name,
            singularName: $singularName,
            icon: $icon,
            description: $description,
            settings: $this->defaultModuleSettings($recordNameField),
            displayOrder: $displayOrder,
            apiName: $apiName
        );

        return $this->moduleRepository->save($module);
    }

    private function createBlock(Module $module, string $name, int $order): Block
    {
        $existingBlocks = $this->blockRepository->findByModuleId($module->getId());
        foreach ($existingBlocks as $block) {
            if ($block->name() === $name) {
                return $block;
            }
        }

        $block = Block::create(
            moduleId: $module->getId(),
            name: $name,
            type: BlockType::SECTION,
            displayOrder: $order,
            settings: []
        );

        return $this->blockRepository->save($block);
    }

    private function createField(
        Block $block,
        string $apiName,
        string $label,
        FieldType $type,
        int $order,
        bool $required = false,
        bool $filterable = true,
        bool $searchable = true,
        bool $sortable = true,
        bool $unique = false,
        array $settings = []
    ): Field {
        $existingFields = $this->fieldRepository->findByBlockId($block->getId());
        foreach ($existingFields as $field) {
            if ($field->apiName() === $apiName) {
                return $field;
            }
        }

        $field = new Field(
            id: null,
            moduleId: $block->moduleId(),
            blockId: $block->getId(),
            label: $label,
            apiName: $apiName,
            type: $type,
            description: null,
            helpText: null,
            isRequired: $required,
            isUnique: $unique,
            isSearchable: $searchable,
            isFilterable: $filterable,
            isSortable: $sortable,
            validationRules: ValidationRules::empty(),
            settings: FieldSettings::fromArray(array_merge(['additional_settings' => []], $settings)),
            defaultValue: null,
            displayOrder: $order,
            width: 100,
            createdAt: new \DateTimeImmutable(),
        );

        return $this->fieldRepository->save($field);
    }

    private function createFieldOptions(Field $field, array $options): void
    {
        foreach ($options as $index => $option) {
            $value = strtolower(str_replace([' ', '&', '/'], ['_', '', '_'], $option));

            // Check if option already exists
            $existingOptions = $this->fieldOptionRepository->findByFieldId($field->getId());
            $exists = false;
            foreach ($existingOptions as $existing) {
                if ($existing->value() === $value) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $fieldOption = FieldOption::create(
                    fieldId: $field->getId(),
                    label: $option,
                    value: $value,
                    color: null,
                    displayOrder: $index + 1
                );
                $this->fieldOptionRepository->save($fieldOption);
            }
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
