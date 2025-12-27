<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModuleRecordsSeeder extends Seeder
{
    private int $userId;
    private array $organizationIds = [];
    private array $contactIds = [];
    private array $dealIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding Module Records...');

        $this->userId = DB::table('users')->first()?->id ?? 1;

        // Clear existing records first
        DB::table('module_records')->delete();

        // Seed in order (organizations first for lookups)
        $this->seedOrganizations();
        $this->seedContacts();
        $this->seedDeals();
        $this->seedLeads();
        $this->seedTasks();
        $this->seedActivities();
        $this->seedNotes();
        $this->seedCases();
        $this->seedProducts();
        $this->seedInvoices();
        $this->seedEvents();
        $this->seedQuotes();
        $this->seedWorkflows();

        // Seed additional features
        $this->seedBlueprints();
        $this->seedApprovalRules();
        $this->seedCadences();
        $this->seedPlaybooks();
        $this->seedReports();
        $this->seedDashboards();
        $this->seedForecasts();
        $this->seedQuotas();
        $this->seedEmailTemplates();
        $this->seedNotifications();

        $this->command->info('  Total records created: ' . DB::table('module_records')->count());
    }

    private function seedOrganizations(): void
    {
        $module = DB::table('modules')->where('api_name', 'organizations')->first();
        if (!$module) {
            $this->command->warn('  Organizations module not found');
            return;
        }

        $organizations = [
            ['name' => 'Acme Corporation', 'website' => 'https://acme.example.com', 'industry' => 'technology', 'employee_count' => '501-1000', 'phone' => '+1-555-0100', 'email' => 'info@acme.com', 'street' => '123 Tech Lane', 'city' => 'San Francisco', 'state' => 'California', 'postal_code' => '94102', 'country' => 'united_states', 'type' => 'customer', 'annual_revenue' => 50000000, 'description' => 'Leading technology solutions provider'],
            ['name' => 'TechStart Inc', 'website' => 'https://techstart.example.com', 'industry' => 'technology', 'employee_count' => '51-200', 'phone' => '+1-555-0101', 'email' => 'hello@techstart.com', 'street' => '456 Startup Blvd', 'city' => 'Austin', 'state' => 'Texas', 'postal_code' => '78701', 'country' => 'united_states', 'type' => 'prospect', 'annual_revenue' => 5000000, 'description' => 'Fast-growing SaaS startup'],
            ['name' => 'Global Industries', 'website' => 'https://global-ind.example.com', 'industry' => 'manufacturing', 'employee_count' => '1000+', 'phone' => '+1-555-0102', 'email' => 'contact@global-ind.com', 'street' => '789 Industrial Park', 'city' => 'Detroit', 'state' => 'Michigan', 'postal_code' => '48201', 'country' => 'united_states', 'type' => 'customer', 'annual_revenue' => 250000000, 'description' => 'Global manufacturing conglomerate'],
            ['name' => 'CloudSync Solutions', 'website' => 'https://cloudsync.example.com', 'industry' => 'technology', 'employee_count' => '201-500', 'phone' => '+1-555-0103', 'email' => 'sales@cloudsync.com', 'street' => '321 Cloud Ave', 'city' => 'Seattle', 'state' => 'Washington', 'postal_code' => '98101', 'country' => 'united_states', 'type' => 'customer', 'annual_revenue' => 35000000, 'description' => 'Enterprise cloud infrastructure'],
            ['name' => 'DataFlow Analytics', 'website' => 'https://dataflow.example.com', 'industry' => 'technology', 'employee_count' => '51-200', 'phone' => '+1-555-0104', 'email' => 'info@dataflow.com', 'street' => '654 Data Drive', 'city' => 'Boston', 'state' => 'Massachusetts', 'postal_code' => '02101', 'country' => 'united_states', 'type' => 'prospect', 'annual_revenue' => 12000000, 'description' => 'Big data analytics platform'],
            ['name' => 'SecureNet Systems', 'website' => 'https://securenet.example.com', 'industry' => 'technology', 'employee_count' => '201-500', 'phone' => '+1-555-0105', 'email' => 'security@securenet.com', 'street' => '987 Security Way', 'city' => 'Washington', 'state' => 'DC', 'postal_code' => '20001', 'country' => 'united_states', 'type' => 'customer', 'annual_revenue' => 45000000, 'description' => 'Cybersecurity solutions'],
            ['name' => 'GreenTech Innovations', 'website' => 'https://greentech.example.com', 'industry' => 'energy', 'employee_count' => '51-200', 'phone' => '+1-555-0106', 'email' => 'hello@greentech.com', 'street' => '147 Green St', 'city' => 'Portland', 'state' => 'Oregon', 'postal_code' => '97201', 'country' => 'united_states', 'type' => 'prospect', 'annual_revenue' => 8000000, 'description' => 'Clean energy solutions'],
            ['name' => 'MediCore Health', 'website' => 'https://medicore.example.com', 'industry' => 'healthcare', 'employee_count' => '501-1000', 'phone' => '+1-555-0107', 'email' => 'contact@medicore.com', 'street' => '258 Health Blvd', 'city' => 'Chicago', 'state' => 'Illinois', 'postal_code' => '60601', 'country' => 'united_states', 'type' => 'customer', 'annual_revenue' => 120000000, 'description' => 'Healthcare technology provider'],
            ['name' => 'FinanceHub Ltd', 'website' => 'https://financehub.example.com', 'industry' => 'finance', 'employee_count' => '201-500', 'phone' => '+1-555-0108', 'email' => 'info@financehub.com', 'street' => '369 Wall St', 'city' => 'New York', 'state' => 'New York', 'postal_code' => '10005', 'country' => 'united_states', 'type' => 'customer', 'annual_revenue' => 85000000, 'description' => 'Financial services platform'],
            ['name' => 'RetailMax', 'website' => 'https://retailmax.example.com', 'industry' => 'retail', 'employee_count' => '1000+', 'phone' => '+1-555-0109', 'email' => 'partners@retailmax.com', 'street' => '741 Commerce Dr', 'city' => 'Dallas', 'state' => 'Texas', 'postal_code' => '75201', 'country' => 'united_states', 'type' => 'prospect', 'annual_revenue' => 500000000, 'description' => 'National retail chain'],
            ['name' => 'EduLearn Systems', 'website' => 'https://edulearn.example.com', 'industry' => 'education', 'employee_count' => '51-200', 'phone' => '+1-555-0110', 'email' => 'sales@edulearn.com', 'street' => '852 Campus Way', 'city' => 'Cambridge', 'state' => 'Massachusetts', 'postal_code' => '02138', 'country' => 'united_states', 'type' => 'customer', 'annual_revenue' => 15000000, 'description' => 'E-learning platform provider'],
            ['name' => 'LogiTrans Corp', 'website' => 'https://logitrans.example.com', 'industry' => 'transportation', 'employee_count' => '501-1000', 'phone' => '+1-555-0111', 'email' => 'logistics@logitrans.com', 'street' => '963 Freight Lane', 'city' => 'Memphis', 'state' => 'Tennessee', 'postal_code' => '38101', 'country' => 'united_states', 'type' => 'prospect', 'annual_revenue' => 200000000, 'description' => 'Supply chain and logistics'],
        ];

        foreach ($organizations as $org) {
            $recordId = DB::table('module_records')->insertGetId([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($org, ['assigned_to' => $this->userId])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->organizationIds[] = $recordId;
        }

        $this->command->info('  Created ' . count($organizations) . ' organizations');
    }

    private function seedContacts(): void
    {
        $module = DB::table('modules')->where('api_name', 'contacts')->first();
        if (!$module) {
            $this->command->warn('  Contacts module not found');
            return;
        }

        $contacts = [
            ['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@acme.example.com', 'phone' => '+1-555-1001', 'mobile' => '+1-555-1101', 'job_title' => 'CEO', 'department' => 'Executive', 'organization_id' => 0, 'status' => 'Customer', 'lead_source' => 'Referral', 'tags' => ['VIP', 'Decision Maker']],
            ['first_name' => 'Sarah', 'last_name' => 'Johnson', 'email' => 'sarah.j@techstart.example.com', 'phone' => '+1-555-1002', 'mobile' => '+1-555-1102', 'job_title' => 'CTO', 'department' => 'Technology', 'organization_id' => 1, 'status' => 'Prospect', 'lead_source' => 'Website', 'tags' => ['Technical', 'Decision Maker']],
            ['first_name' => 'Michael', 'last_name' => 'Williams', 'email' => 'm.williams@global-ind.example.com', 'phone' => '+1-555-1003', 'mobile' => '+1-555-1103', 'job_title' => 'VP of Sales', 'department' => 'Sales', 'organization_id' => 2, 'status' => 'Customer', 'lead_source' => 'Trade Show', 'tags' => ['Decision Maker']],
            ['first_name' => 'Emily', 'last_name' => 'Brown', 'email' => 'emily.b@cloudsync.example.com', 'phone' => '+1-555-1004', 'mobile' => '+1-555-1104', 'job_title' => 'Product Manager', 'department' => 'Product', 'organization_id' => 3, 'status' => 'Customer', 'lead_source' => 'Referral', 'tags' => ['Champion', 'Technical']],
            ['first_name' => 'David', 'last_name' => 'Davis', 'email' => 'david.d@dataflow.example.com', 'phone' => '+1-555-1005', 'mobile' => '+1-555-1105', 'job_title' => 'Head of Engineering', 'department' => 'Engineering', 'organization_id' => 4, 'status' => 'Prospect', 'lead_source' => 'Website', 'tags' => ['Technical']],
            ['first_name' => 'Jennifer', 'last_name' => 'Miller', 'email' => 'jen.miller@securenet.example.com', 'phone' => '+1-555-1006', 'mobile' => '+1-555-1106', 'job_title' => 'Security Director', 'department' => 'Security', 'organization_id' => 5, 'status' => 'Customer', 'lead_source' => 'Partner', 'tags' => ['VIP', 'Technical']],
            ['first_name' => 'Robert', 'last_name' => 'Wilson', 'email' => 'r.wilson@greentech.example.com', 'phone' => '+1-555-1007', 'mobile' => '+1-555-1107', 'job_title' => 'Operations Manager', 'department' => 'Operations', 'organization_id' => 6, 'status' => 'Lead', 'lead_source' => 'Cold Call', 'tags' => ['Influencer']],
            ['first_name' => 'Lisa', 'last_name' => 'Anderson', 'email' => 'lisa.a@medicore.example.com', 'phone' => '+1-555-1008', 'mobile' => '+1-555-1108', 'job_title' => 'CFO', 'department' => 'Finance', 'organization_id' => 7, 'status' => 'Customer', 'lead_source' => 'Referral', 'tags' => ['VIP', 'Decision Maker']],
            ['first_name' => 'James', 'last_name' => 'Taylor', 'email' => 'james.t@financehub.example.com', 'phone' => '+1-555-1009', 'mobile' => '+1-555-1109', 'job_title' => 'Account Manager', 'department' => 'Sales', 'organization_id' => 8, 'status' => 'Customer', 'lead_source' => 'Email Campaign', 'tags' => ['Champion']],
            ['first_name' => 'Amanda', 'last_name' => 'Thomas', 'email' => 'a.thomas@retailmax.example.com', 'phone' => '+1-555-1010', 'mobile' => '+1-555-1110', 'job_title' => 'Procurement Lead', 'department' => 'Procurement', 'organization_id' => 9, 'status' => 'Prospect', 'lead_source' => 'Trade Show', 'tags' => ['Decision Maker']],
            ['first_name' => 'Chris', 'last_name' => 'Martinez', 'email' => 'chris.m@acme.example.com', 'phone' => '+1-555-1011', 'mobile' => '+1-555-1111', 'job_title' => 'Sales Director', 'department' => 'Sales', 'organization_id' => 0, 'status' => 'Customer', 'lead_source' => 'Website', 'tags' => ['Champion']],
            ['first_name' => 'Jessica', 'last_name' => 'Garcia', 'email' => 'j.garcia@techstart.example.com', 'phone' => '+1-555-1012', 'mobile' => '+1-555-1112', 'job_title' => 'Marketing Manager', 'department' => 'Marketing', 'organization_id' => 1, 'status' => 'Prospect', 'lead_source' => 'Social Media', 'tags' => ['Influencer']],
            ['first_name' => 'Daniel', 'last_name' => 'Rodriguez', 'email' => 'd.rodriguez@global-ind.example.com', 'phone' => '+1-555-1013', 'mobile' => '+1-555-1113', 'job_title' => 'IT Director', 'department' => 'IT', 'organization_id' => 2, 'status' => 'Customer', 'lead_source' => 'Referral', 'tags' => ['Technical', 'Decision Maker']],
            ['first_name' => 'Michelle', 'last_name' => 'Lee', 'email' => 'm.lee@cloudsync.example.com', 'phone' => '+1-555-1014', 'mobile' => '+1-555-1114', 'job_title' => 'Customer Success Manager', 'department' => 'Customer Success', 'organization_id' => 3, 'status' => 'Customer', 'lead_source' => 'Website', 'tags' => ['Champion']],
            ['first_name' => 'Kevin', 'last_name' => 'White', 'email' => 'k.white@dataflow.example.com', 'phone' => '+1-555-1015', 'mobile' => '+1-555-1115', 'job_title' => 'Data Scientist', 'department' => 'Data Science', 'organization_id' => 4, 'status' => 'Prospect', 'lead_source' => 'Webinar', 'tags' => ['Technical']],
            ['first_name' => 'Rachel', 'last_name' => 'Harris', 'email' => 'r.harris@edulearn.example.com', 'phone' => '+1-555-1016', 'mobile' => '+1-555-1116', 'job_title' => 'Training Director', 'department' => 'Training', 'organization_id' => 10, 'status' => 'Customer', 'lead_source' => 'Partner', 'tags' => ['Decision Maker']],
            ['first_name' => 'Steven', 'last_name' => 'Clark', 'email' => 's.clark@logitrans.example.com', 'phone' => '+1-555-1017', 'mobile' => '+1-555-1117', 'job_title' => 'Logistics Manager', 'department' => 'Logistics', 'organization_id' => 11, 'status' => 'Prospect', 'lead_source' => 'Cold Call', 'tags' => ['Influencer']],
            ['first_name' => 'Nicole', 'last_name' => 'Lewis', 'email' => 'n.lewis@acme.example.com', 'phone' => '+1-555-1018', 'mobile' => '+1-555-1118', 'job_title' => 'VP Engineering', 'department' => 'Engineering', 'organization_id' => 0, 'status' => 'Customer', 'lead_source' => 'Referral', 'tags' => ['VIP', 'Technical']],
            ['first_name' => 'Andrew', 'last_name' => 'Walker', 'email' => 'a.walker@securenet.example.com', 'phone' => '+1-555-1019', 'mobile' => '+1-555-1119', 'job_title' => 'Security Analyst', 'department' => 'Security', 'organization_id' => 5, 'status' => 'Customer', 'lead_source' => 'Website', 'tags' => ['Technical']],
            ['first_name' => 'Stephanie', 'last_name' => 'Hall', 'email' => 's.hall@medicore.example.com', 'phone' => '+1-555-1020', 'mobile' => '+1-555-1120', 'job_title' => 'Project Manager', 'department' => 'PMO', 'organization_id' => 7, 'status' => 'Customer', 'lead_source' => 'Trade Show', 'tags' => ['Champion']],
        ];

        foreach ($contacts as $contact) {
            // Map organization_id to actual record id
            if (isset($this->organizationIds[$contact['organization_id']])) {
                $contact['organization_id'] = $this->organizationIds[$contact['organization_id']];
            } else {
                unset($contact['organization_id']);
            }

            $recordId = DB::table('module_records')->insertGetId([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($contact, [
                    'assigned_to' => $this->userId,
                    'do_not_contact' => false,
                ])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->contactIds[] = $recordId;
        }

        $this->command->info('  Created ' . count($contacts) . ' contacts');
    }

    private function seedDeals(): void
    {
        $module = DB::table('modules')->where('api_name', 'deals')->first();
        if (!$module) {
            $this->command->warn('  Deals module not found');
            return;
        }

        $deals = [
            ['name' => 'Enterprise License Agreement - Acme', 'amount' => 250000, 'probability' => 60, 'stage' => 'proposal', 'close_date' => now()->addDays(30)->format('Y-m-d'), 'source' => 'referral', 'organization_id' => 0, 'contact_id' => 0, 'description' => 'Multi-year enterprise licensing deal', 'next_step' => 'Schedule executive demo', 'tags' => ['high_value', 'strategic']],
            ['name' => 'Cloud Migration Project', 'amount' => 175000, 'probability' => 75, 'stage' => 'negotiation', 'close_date' => now()->addDays(14)->format('Y-m-d'), 'source' => 'website', 'organization_id' => 1, 'contact_id' => 1, 'description' => 'Full cloud infrastructure migration', 'next_step' => 'Final contract review', 'tags' => ['strategic']],
            ['name' => 'Security Audit Package', 'amount' => 85000, 'probability' => 30, 'stage' => 'qualification', 'close_date' => now()->addDays(60)->format('Y-m-d'), 'source' => 'partner', 'organization_id' => 5, 'contact_id' => 5, 'description' => 'Comprehensive security assessment', 'next_step' => 'Technical discovery call', 'tags' => ['quick_win']],
            ['name' => 'Analytics Platform Subscription', 'amount' => 120000, 'probability' => 45, 'stage' => 'prospecting', 'close_date' => now()->addDays(45)->format('Y-m-d'), 'source' => 'website', 'organization_id' => 4, 'contact_id' => 4, 'description' => 'Annual analytics platform license', 'next_step' => 'Product demo', 'tags' => ['renewal']],
            ['name' => 'Support Contract Renewal - Global', 'amount' => 65000, 'probability' => 100, 'stage' => 'closed_won', 'close_date' => now()->subDays(5)->format('Y-m-d'), 'source' => 'outbound', 'organization_id' => 2, 'contact_id' => 2, 'description' => '3-year support contract renewal', 'next_step' => 'Signed and closed', 'tags' => ['renewal']],
            ['name' => 'Custom Development Project', 'amount' => 320000, 'probability' => 55, 'stage' => 'proposal', 'close_date' => now()->addDays(40)->format('Y-m-d'), 'source' => 'referral', 'organization_id' => 3, 'contact_id' => 3, 'description' => 'Custom integration development', 'next_step' => 'SOW review meeting', 'tags' => ['high_value', 'strategic']],
            ['name' => 'Training Program - EduLearn', 'amount' => 45000, 'probability' => 100, 'stage' => 'closed_won', 'close_date' => now()->subDays(10)->format('Y-m-d'), 'source' => 'partner', 'organization_id' => 10, 'contact_id' => 15, 'description' => 'Enterprise training program', 'next_step' => 'Implementation kickoff', 'tags' => ['quick_win']],
            ['name' => 'Consulting Engagement Q1', 'amount' => 95000, 'probability' => 80, 'stage' => 'negotiation', 'close_date' => now()->addDays(7)->format('Y-m-d'), 'source' => 'inbound', 'organization_id' => 8, 'contact_id' => 8, 'description' => 'Q1 consulting services', 'next_step' => 'Contract signing', 'tags' => ['expansion']],
            ['name' => 'Hardware Upgrade Initiative', 'amount' => 180000, 'probability' => 25, 'stage' => 'qualification', 'close_date' => now()->addDays(90)->format('Y-m-d'), 'source' => 'event', 'organization_id' => 9, 'contact_id' => 9, 'description' => 'Retail POS hardware upgrade', 'next_step' => 'Budget approval pending', 'tags' => ['high_value']],
            ['name' => 'SaaS Implementation - MediCore', 'amount' => 150000, 'probability' => 40, 'stage' => 'prospecting', 'close_date' => now()->addDays(55)->format('Y-m-d'), 'source' => 'referral', 'organization_id' => 7, 'contact_id' => 7, 'description' => 'Healthcare SaaS platform', 'next_step' => 'Requirements workshop', 'tags' => ['strategic']],
            ['name' => 'Data Warehouse Solution', 'amount' => 280000, 'probability' => 65, 'stage' => 'proposal', 'close_date' => now()->addDays(25)->format('Y-m-d'), 'source' => 'website', 'organization_id' => 11, 'contact_id' => 16, 'description' => 'Enterprise data warehouse', 'next_step' => 'Technical architecture review', 'tags' => ['high_value', 'strategic']],
            ['name' => 'Cybersecurity Upgrade - Lost', 'amount' => 110000, 'probability' => 0, 'stage' => 'closed_lost', 'close_date' => now()->subDays(15)->format('Y-m-d'), 'source' => 'outbound', 'organization_id' => 6, 'contact_id' => 6, 'description' => 'Security infrastructure upgrade', 'next_step' => 'Lost to competitor', 'tags' => []],
            ['name' => 'Annual Maintenance Contract', 'amount' => 42000, 'probability' => 100, 'stage' => 'closed_won', 'close_date' => now()->subDays(3)->format('Y-m-d'), 'source' => 'outbound', 'organization_id' => 0, 'contact_id' => 10, 'description' => 'Annual maintenance renewal', 'next_step' => 'Completed', 'tags' => ['renewal']],
            ['name' => 'API Integration Project', 'amount' => 88000, 'probability' => 50, 'stage' => 'prospecting', 'close_date' => now()->addDays(35)->format('Y-m-d'), 'source' => 'partner', 'organization_id' => 1, 'contact_id' => 11, 'description' => 'Third-party API integrations', 'next_step' => 'Technical scoping', 'tags' => ['expansion']],
            ['name' => 'Mobile App Development', 'amount' => 135000, 'probability' => 85, 'stage' => 'negotiation', 'close_date' => now()->addDays(5)->format('Y-m-d'), 'source' => 'referral', 'organization_id' => 3, 'contact_id' => 13, 'description' => 'Mobile application development', 'next_step' => 'Final pricing discussion', 'tags' => ['strategic']],
        ];

        foreach ($deals as $deal) {
            // Map IDs
            if (isset($this->organizationIds[$deal['organization_id']])) {
                $deal['organization_id'] = $this->organizationIds[$deal['organization_id']];
            } else {
                unset($deal['organization_id']);
            }
            if (isset($this->contactIds[$deal['contact_id']])) {
                $deal['contact_id'] = $this->contactIds[$deal['contact_id']];
            } else {
                unset($deal['contact_id']);
            }

            // Calculate expected revenue
            $deal['expected_revenue'] = (int) ($deal['amount'] * $deal['probability'] / 100);

            $recordId = DB::table('module_records')->insertGetId([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($deal, ['assigned_to' => $this->userId])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->dealIds[] = $recordId;
        }

        $this->command->info('  Created ' . count($deals) . ' deals');
    }

    private function seedLeads(): void
    {
        $module = DB::table('modules')->where('api_name', 'leads')->first();
        if (!$module) {
            $this->command->warn('  Leads module not found');
            return;
        }

        $leads = [
            ['first_name' => 'Tom', 'last_name' => 'Baker', 'email' => 'tom.baker@prospect1.com', 'phone' => '+1-555-2001', 'company' => 'Prospect One LLC', 'title' => 'IT Manager', 'industry' => 'technology', 'number_of_employees' => '51-200', 'annual_revenue' => 5000000, 'source' => 'website', 'status' => 'new', 'rating' => 'hot', 'city' => 'Denver', 'state' => 'Colorado', 'country' => 'united_states'],
            ['first_name' => 'Nancy', 'last_name' => 'Drew', 'email' => 'nancy.d@newclient.com', 'phone' => '+1-555-2002', 'company' => 'New Client Corp', 'title' => 'Operations Director', 'industry' => 'finance', 'number_of_employees' => '201-500', 'annual_revenue' => 25000000, 'source' => 'referral', 'status' => 'contacted', 'rating' => 'warm', 'city' => 'Miami', 'state' => 'Florida', 'country' => 'united_states'],
            ['first_name' => 'Frank', 'last_name' => 'Castle', 'email' => 'f.castle@startup.io', 'phone' => '+1-555-2003', 'company' => 'Hot Startup', 'title' => 'Founder & CEO', 'industry' => 'technology', 'number_of_employees' => '11-50', 'annual_revenue' => 2000000, 'source' => 'trade_show', 'status' => 'qualified', 'rating' => 'hot', 'city' => 'San Jose', 'state' => 'California', 'country' => 'united_states'],
            ['first_name' => 'Diana', 'last_name' => 'Prince', 'email' => 'd.prince@enterprise.com', 'phone' => '+1-555-2004', 'company' => 'Big Enterprise', 'title' => 'VP Technology', 'industry' => 'manufacturing', 'number_of_employees' => '1001-5000', 'annual_revenue' => 500000000, 'source' => 'cold_call', 'status' => 'contacted', 'rating' => 'hot', 'city' => 'Atlanta', 'state' => 'Georgia', 'country' => 'united_states'],
            ['first_name' => 'Bruce', 'last_name' => 'Wayne', 'email' => 'b.wayne@wealthy.com', 'phone' => '+1-555-2005', 'company' => 'Wayne Industries', 'title' => 'CEO', 'industry' => 'finance', 'number_of_employees' => '5000+', 'annual_revenue' => 10000000000, 'source' => 'referral', 'status' => 'new', 'rating' => 'hot', 'city' => 'Gotham', 'state' => 'New Jersey', 'country' => 'united_states'],
            ['first_name' => 'Clark', 'last_name' => 'Kent', 'email' => 'c.kent@media.com', 'phone' => '+1-555-2006', 'company' => 'Daily Media', 'title' => 'Editor', 'industry' => 'other', 'number_of_employees' => '201-500', 'annual_revenue' => 50000000, 'source' => 'social_media', 'status' => 'unqualified', 'rating' => 'cold', 'city' => 'Metropolis', 'state' => 'New York', 'country' => 'united_states'],
            ['first_name' => 'Peter', 'last_name' => 'Parker', 'email' => 'p.parker@photo.com', 'phone' => '+1-555-2007', 'company' => 'Photo Pros', 'title' => 'Creative Director', 'industry' => 'other', 'number_of_employees' => '1-10', 'annual_revenue' => 500000, 'source' => 'website', 'status' => 'new', 'rating' => 'warm', 'city' => 'Queens', 'state' => 'New York', 'country' => 'united_states'],
            ['first_name' => 'Tony', 'last_name' => 'Stark', 'email' => 't.stark@innovation.com', 'phone' => '+1-555-2008', 'company' => 'Stark Innovations', 'title' => 'Chief Innovator', 'industry' => 'technology', 'number_of_employees' => '1001-5000', 'annual_revenue' => 5000000000, 'source' => 'trade_show', 'status' => 'qualified', 'rating' => 'hot', 'city' => 'Malibu', 'state' => 'California', 'country' => 'united_states'],
            ['first_name' => 'Steve', 'last_name' => 'Rogers', 'email' => 's.rogers@defense.com', 'phone' => '+1-555-2009', 'company' => 'Shield Defense', 'title' => 'Security Lead', 'industry' => 'government', 'number_of_employees' => '501-1000', 'annual_revenue' => 100000000, 'source' => 'partner', 'status' => 'contacted', 'rating' => 'warm', 'city' => 'Washington', 'state' => 'DC', 'country' => 'united_states'],
            ['first_name' => 'Natasha', 'last_name' => 'Romanoff', 'email' => 'n.romanoff@intel.com', 'phone' => '+1-555-2010', 'company' => 'Intel Systems', 'title' => 'Intelligence Analyst', 'industry' => 'technology', 'number_of_employees' => '201-500', 'annual_revenue' => 75000000, 'source' => 'webinar', 'status' => 'qualified', 'rating' => 'hot', 'city' => 'San Francisco', 'state' => 'California', 'country' => 'united_states'],
            ['first_name' => 'Thor', 'last_name' => 'Odinson', 'email' => 't.odinson@nordic.com', 'phone' => '+1-555-2011', 'company' => 'Nordic Tech', 'title' => 'Power Systems Lead', 'industry' => 'other', 'number_of_employees' => '51-200', 'annual_revenue' => 15000000, 'source' => 'cold_call', 'status' => 'new', 'rating' => 'cold', 'city' => 'Minneapolis', 'state' => 'Minnesota', 'country' => 'united_states'],
            ['first_name' => 'Wanda', 'last_name' => 'Maximoff', 'email' => 'w.maximoff@reality.io', 'phone' => '+1-555-2012', 'company' => 'Reality Software', 'title' => 'AR/VR Developer', 'industry' => 'technology', 'number_of_employees' => '11-50', 'annual_revenue' => 3000000, 'source' => 'website', 'status' => 'contacted', 'rating' => 'warm', 'city' => 'Los Angeles', 'state' => 'California', 'country' => 'united_states'],
        ];

        foreach ($leads as $lead) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($lead, [
                    'assigned_to' => $this->userId,
                    'tags' => $lead['rating'] === 'hot' ? ['high_priority', 'demo_requested'] : [],
                ])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($leads) . ' leads');
    }

    private function seedTasks(): void
    {
        $module = DB::table('modules')->where('api_name', 'tasks')->first();
        if (!$module) {
            $this->command->warn('  Tasks module not found');
            return;
        }

        $tasks = [
            ['subject' => 'Follow up with Acme on proposal', 'description' => 'Review their feedback and schedule next call', 'priority' => 'High', 'status' => 'In Progress', 'due_date' => now()->addDays(2)->format('Y-m-d'), 'related_to_type' => 'Deal', 'related_to_id' => $this->dealIds[0] ?? null],
            ['subject' => 'Send contract to TechStart', 'description' => 'Prepare and send final contract documents', 'priority' => 'Urgent', 'status' => 'Not Started', 'due_date' => now()->addDays(1)->format('Y-m-d'), 'related_to_type' => 'Deal', 'related_to_id' => $this->dealIds[1] ?? null],
            ['subject' => 'Prepare demo for DataFlow', 'description' => 'Customize demo for analytics use case', 'priority' => 'Normal', 'status' => 'In Progress', 'due_date' => now()->addDays(5)->format('Y-m-d'), 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[4] ?? null],
            ['subject' => 'Review security requirements', 'description' => 'Review SecureNet compliance requirements', 'priority' => 'High', 'status' => 'Not Started', 'due_date' => now()->addDays(3)->format('Y-m-d'), 'related_to_type' => 'Contact', 'related_to_id' => $this->contactIds[5] ?? null],
            ['subject' => 'Schedule QBR with Global Industries', 'description' => 'Quarterly business review meeting', 'priority' => 'Normal', 'status' => 'Completed', 'due_date' => now()->subDays(2)->format('Y-m-d'), 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[2] ?? null],
            ['subject' => 'Update CRM with meeting notes', 'description' => 'Log notes from CloudSync call', 'priority' => 'Low', 'status' => 'Completed', 'due_date' => now()->subDays(1)->format('Y-m-d'), 'related_to_type' => 'Contact', 'related_to_id' => $this->contactIds[3] ?? null],
            ['subject' => 'Send pricing proposal to RetailMax', 'description' => 'Custom pricing for hardware upgrade', 'priority' => 'High', 'status' => 'Not Started', 'due_date' => now()->addDays(4)->format('Y-m-d'), 'related_to_type' => 'Deal', 'related_to_id' => $this->dealIds[8] ?? null],
            ['subject' => 'Research MediCore competitors', 'description' => 'Competitive analysis for healthcare sector', 'priority' => 'Normal', 'status' => 'In Progress', 'due_date' => now()->addDays(7)->format('Y-m-d'), 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[7] ?? null],
            ['subject' => 'Follow up on lost deal analysis', 'description' => 'Understand why GreenTech deal was lost', 'priority' => 'Low', 'status' => 'Waiting', 'due_date' => now()->addDays(10)->format('Y-m-d'), 'related_to_type' => 'Deal', 'related_to_id' => $this->dealIds[11] ?? null],
            ['subject' => 'Renew certification training', 'description' => 'Complete annual product certification', 'priority' => 'Normal', 'status' => 'Deferred', 'due_date' => now()->addDays(30)->format('Y-m-d'), 'related_to_type' => null, 'related_to_id' => null],
        ];

        foreach ($tasks as $task) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($task, [
                    'assigned_to' => $this->userId,
                    'tags' => $task['priority'] === 'Urgent' ? ['Urgent', 'Follow-up'] : ['Follow-up'],
                ])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($tasks) . ' tasks');
    }

    private function seedActivities(): void
    {
        $module = DB::table('modules')->where('api_name', 'activities')->first();
        if (!$module) {
            $this->command->warn('  Activities module not found');
            return;
        }

        $activities = [
            ['subject' => 'Discovery call with John Smith', 'type' => 'Call', 'status' => 'Completed', 'start_time' => now()->subDays(5)->setHour(10)->format('Y-m-d H:i:s'), 'end_time' => now()->subDays(5)->setHour(10)->addMinutes(30)->format('Y-m-d H:i:s'), 'duration' => 30, 'outcome' => 'Positive', 'description' => 'Discussed their current challenges and pain points. Very interested in our solution.', 'related_to_type' => 'Contact', 'related_to_id' => $this->contactIds[0] ?? null],
            ['subject' => 'Product demo for TechStart', 'type' => 'Meeting', 'status' => 'Completed', 'start_time' => now()->subDays(3)->setHour(14)->format('Y-m-d H:i:s'), 'end_time' => now()->subDays(3)->setHour(15)->format('Y-m-d H:i:s'), 'duration' => 60, 'outcome' => 'Positive', 'description' => 'Full platform demo. CTO was impressed with integration capabilities.', 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[1] ?? null],
            ['subject' => 'Follow-up email to Emily Brown', 'type' => 'Email', 'status' => 'Completed', 'start_time' => now()->subDays(2)->setHour(9)->format('Y-m-d H:i:s'), 'end_time' => now()->subDays(2)->setHour(9)->addMinutes(15)->format('Y-m-d H:i:s'), 'duration' => 15, 'outcome' => 'Neutral', 'description' => 'Sent proposal document and pricing details. Awaiting response.', 'related_to_type' => 'Contact', 'related_to_id' => $this->contactIds[3] ?? null],
            ['subject' => 'Security requirements discussion', 'type' => 'Call', 'status' => 'Completed', 'start_time' => now()->subDays(1)->setHour(11)->format('Y-m-d H:i:s'), 'end_time' => now()->subDays(1)->setHour(11)->addMinutes(45)->format('Y-m-d H:i:s'), 'duration' => 45, 'outcome' => 'Positive', 'description' => 'Reviewed compliance requirements. They need SOC2 certification documentation.', 'related_to_type' => 'Contact', 'related_to_id' => $this->contactIds[5] ?? null],
            ['subject' => 'Contract negotiation call', 'type' => 'Call', 'status' => 'Completed', 'start_time' => now()->subHours(4)->format('Y-m-d H:i:s'), 'end_time' => now()->subHours(3)->format('Y-m-d H:i:s'), 'duration' => 60, 'outcome' => 'Positive', 'description' => 'Finalized pricing and terms. Ready to sign next week.', 'related_to_type' => 'Deal', 'related_to_id' => $this->dealIds[1] ?? null],
            ['subject' => 'Quarterly review with Global Industries', 'type' => 'Meeting', 'status' => 'Completed', 'start_time' => now()->subDays(7)->setHour(10)->format('Y-m-d H:i:s'), 'end_time' => now()->subDays(7)->setHour(12)->format('Y-m-d H:i:s'), 'duration' => 120, 'outcome' => 'Positive', 'description' => 'Reviewed platform usage, discussed expansion opportunities. Renewal confirmed.', 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[2] ?? null],
            ['subject' => 'Cold call to GreenTech', 'type' => 'Call', 'status' => 'Completed', 'start_time' => now()->subDays(10)->setHour(14)->format('Y-m-d H:i:s'), 'end_time' => now()->subDays(10)->setHour(14)->addMinutes(10)->format('Y-m-d H:i:s'), 'duration' => 10, 'outcome' => 'Negative', 'description' => 'Not interested at this time. Follow up in 6 months.', 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[6] ?? null],
            ['subject' => 'Upcoming: Technical deep dive with DataFlow', 'type' => 'Meeting', 'status' => 'Scheduled', 'start_time' => now()->addDays(2)->setHour(14)->format('Y-m-d H:i:s'), 'end_time' => now()->addDays(2)->setHour(16)->format('Y-m-d H:i:s'), 'duration' => 120, 'outcome' => null, 'description' => 'Technical architecture review with engineering team.', 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[4] ?? null],
            ['subject' => 'Upcoming: Executive presentation to RetailMax', 'type' => 'Meeting', 'status' => 'Scheduled', 'start_time' => now()->addDays(5)->setHour(10)->format('Y-m-d H:i:s'), 'end_time' => now()->addDays(5)->setHour(11)->format('Y-m-d H:i:s'), 'duration' => 60, 'outcome' => null, 'description' => 'C-level presentation for hardware upgrade initiative.', 'related_to_type' => 'Deal', 'related_to_id' => $this->dealIds[8] ?? null],
            ['subject' => 'LinkedIn outreach to prospect', 'type' => 'Social', 'status' => 'Completed', 'start_time' => now()->subDays(4)->setHour(16)->format('Y-m-d H:i:s'), 'end_time' => now()->subDays(4)->setHour(16)->addMinutes(5)->format('Y-m-d H:i:s'), 'duration' => 5, 'outcome' => 'Neutral', 'description' => 'Connected and sent introductory message. No response yet.', 'related_to_type' => 'Contact', 'related_to_id' => $this->contactIds[16] ?? null],
        ];

        foreach ($activities as $activity) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($activity, ['assigned_to' => $this->userId])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($activities) . ' activities');
    }

    private function seedNotes(): void
    {
        $module = DB::table('modules')->where('api_name', 'notes')->first();
        if (!$module) {
            $this->command->warn('  Notes module not found');
            return;
        }

        $notes = [
            ['title' => 'Acme Corporation - Key Decision Makers', 'content' => "John Smith (CEO) - Final approval authority\nChris Martinez (Sales Dir) - Day-to-day contact\nNicole Lewis (VP Eng) - Technical validation\n\nBudget cycle: Q4\nProcurement process: 2-3 weeks", 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[0] ?? null],
            ['title' => 'TechStart Technical Requirements', 'content' => "- Need SSO integration with Okta\n- API rate limits: minimum 10k/hour\n- Data residency: US only\n- SLA: 99.9% uptime required\n- Support: 24/7 preferred", 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[1] ?? null],
            ['title' => 'Meeting Notes: CloudSync Demo', 'content' => "Attendees: Emily Brown, Michelle Lee\n\nKey points discussed:\n1. Current pain points with existing solution\n2. Integration timeline expectations\n3. Training requirements\n\nNext steps: Send proposal by Friday", 'related_to_type' => 'Deal', 'related_to_id' => $this->dealIds[5] ?? null],
            ['title' => 'Competitor Intel: SecureNet Deal', 'content' => "Main competitor: CyberGuard Solutions\nTheir strengths: Lower price point, faster implementation\nOur advantages: Better support, more features, stronger roadmap\n\nStrategy: Emphasize ROI and total cost of ownership", 'related_to_type' => 'Deal', 'related_to_id' => $this->dealIds[2] ?? null],
            ['title' => 'MediCore Compliance Requirements', 'content' => "HIPAA compliance mandatory\nNeed BAA signed before implementation\nData encryption at rest and in transit\nAudit logging for all PHI access\n\nContact legal for BAA template", 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[7] ?? null],
        ];

        foreach ($notes as $note) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode($note),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($notes) . ' notes');
    }

    private function seedCases(): void
    {
        $module = DB::table('modules')->where('api_name', 'cases')->first();
        if (!$module) {
            $this->command->warn('  Cases module not found');
            return;
        }

        $cases = [
            ['subject' => 'Cannot access dashboard after login', 'description' => 'User reports blank screen after successful login. Cleared cache but issue persists.', 'status' => 'open', 'priority' => 'high', 'type' => 'bug', 'category' => 'Technical Support', 'contact_id' => $this->contactIds[0] ?? null, 'organization_id' => $this->organizationIds[0] ?? null],
            ['subject' => 'Feature request: Export to PDF', 'description' => 'Customer requesting ability to export reports directly to PDF format.', 'status' => 'in_progress', 'priority' => 'medium', 'type' => 'feature_request', 'category' => 'Product Feedback', 'contact_id' => $this->contactIds[1] ?? null, 'organization_id' => $this->organizationIds[1] ?? null],
            ['subject' => 'Billing discrepancy on invoice #1234', 'description' => 'Customer claims they were double charged for the monthly subscription.', 'status' => 'open', 'priority' => 'high', 'type' => 'billing', 'category' => 'Billing', 'contact_id' => $this->contactIds[2] ?? null, 'organization_id' => $this->organizationIds[2] ?? null],
            ['subject' => 'Integration with Slack not working', 'description' => 'Slack notifications stopped working after recent update. No error messages displayed.', 'status' => 'pending', 'priority' => 'medium', 'type' => 'bug', 'category' => 'Integrations', 'contact_id' => $this->contactIds[3] ?? null, 'organization_id' => $this->organizationIds[3] ?? null],
            ['subject' => 'How to set up custom workflows?', 'description' => 'New customer needs guidance on configuring automated workflows for their sales process.', 'status' => 'resolved', 'priority' => 'low', 'type' => 'question', 'category' => 'Training', 'contact_id' => $this->contactIds[4] ?? null, 'organization_id' => $this->organizationIds[4] ?? null],
            ['subject' => 'Data import failed with error', 'description' => 'CSV import showing "Invalid format" error. File attached for review.', 'status' => 'open', 'priority' => 'medium', 'type' => 'bug', 'category' => 'Technical Support', 'contact_id' => $this->contactIds[5] ?? null, 'organization_id' => $this->organizationIds[5] ?? null],
            ['subject' => 'Request for API documentation', 'description' => 'Developer team needs updated API docs for v2 endpoints.', 'status' => 'resolved', 'priority' => 'low', 'type' => 'question', 'category' => 'Documentation', 'contact_id' => $this->contactIds[6] ?? null, 'organization_id' => $this->organizationIds[6] ?? null],
            ['subject' => 'Performance issues on large datasets', 'description' => 'Reports taking over 30 seconds to load with 100k+ records.', 'status' => 'in_progress', 'priority' => 'high', 'type' => 'bug', 'category' => 'Performance', 'contact_id' => $this->contactIds[7] ?? null, 'organization_id' => $this->organizationIds[7] ?? null],
        ];

        foreach ($cases as $case) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($case, ['assigned_to' => $this->userId])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($cases) . ' cases');
    }

    private function seedProducts(): void
    {
        $module = DB::table('modules')->where('api_name', 'products')->first();
        if (!$module) {
            $this->command->warn('  Products module not found');
            return;
        }

        $products = [
            ['name' => 'Enterprise CRM License', 'sku' => 'CRM-ENT-001', 'description' => 'Full enterprise CRM license with unlimited users', 'price' => 999.00, 'cost' => 200.00, 'category' => 'Software', 'type' => 'subscription', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'Professional CRM License', 'sku' => 'CRM-PRO-001', 'description' => 'Professional CRM license for up to 50 users', 'price' => 499.00, 'cost' => 100.00, 'category' => 'Software', 'type' => 'subscription', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'Starter CRM License', 'sku' => 'CRM-STR-001', 'description' => 'Starter CRM license for small teams up to 10 users', 'price' => 99.00, 'cost' => 20.00, 'category' => 'Software', 'type' => 'subscription', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'Implementation Services', 'sku' => 'SVC-IMP-001', 'description' => 'Professional implementation and onboarding services', 'price' => 5000.00, 'cost' => 2000.00, 'category' => 'Services', 'type' => 'one_time', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'Training Package - Basic', 'sku' => 'SVC-TRN-001', 'description' => '4-hour virtual training session for up to 10 users', 'price' => 500.00, 'cost' => 150.00, 'category' => 'Services', 'type' => 'one_time', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'Training Package - Advanced', 'sku' => 'SVC-TRN-002', 'description' => 'Full-day on-site training for unlimited users', 'price' => 2500.00, 'cost' => 800.00, 'category' => 'Services', 'type' => 'one_time', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'Premium Support Add-on', 'sku' => 'SUP-PRM-001', 'description' => '24/7 priority support with dedicated account manager', 'price' => 299.00, 'cost' => 50.00, 'category' => 'Support', 'type' => 'subscription', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'API Access Add-on', 'sku' => 'ADD-API-001', 'description' => 'Extended API access with higher rate limits', 'price' => 199.00, 'cost' => 25.00, 'category' => 'Add-ons', 'type' => 'subscription', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'Data Migration Service', 'sku' => 'SVC-MIG-001', 'description' => 'Full data migration from legacy CRM systems', 'price' => 3000.00, 'cost' => 1000.00, 'category' => 'Services', 'type' => 'one_time', 'status' => 'active', 'tax_rate' => 0],
            ['name' => 'Custom Integration', 'sku' => 'SVC-INT-001', 'description' => 'Custom integration development (per integration)', 'price' => 2000.00, 'cost' => 600.00, 'category' => 'Services', 'type' => 'one_time', 'status' => 'active', 'tax_rate' => 0],
        ];

        foreach ($products as $product) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode($product),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($products) . ' products');
    }

    private function seedInvoices(): void
    {
        $module = DB::table('modules')->where('api_name', 'invoices')->first();
        if (!$module) {
            $this->command->warn('  Invoices module not found');
            return;
        }

        $invoices = [
            ['invoice_number' => 'INV-2024-001', 'subject' => 'Enterprise License - Annual', 'status' => 'paid', 'issue_date' => now()->subDays(30)->format('Y-m-d'), 'due_date' => now()->subDays(15)->format('Y-m-d'), 'subtotal' => 11988.00, 'tax_amount' => 0, 'total' => 11988.00, 'amount_paid' => 11988.00, 'balance_due' => 0, 'organization_id' => $this->organizationIds[0] ?? null, 'contact_id' => $this->contactIds[0] ?? null],
            ['invoice_number' => 'INV-2024-002', 'subject' => 'Implementation Services', 'status' => 'paid', 'issue_date' => now()->subDays(25)->format('Y-m-d'), 'due_date' => now()->subDays(10)->format('Y-m-d'), 'subtotal' => 5000.00, 'tax_amount' => 0, 'total' => 5000.00, 'amount_paid' => 5000.00, 'balance_due' => 0, 'organization_id' => $this->organizationIds[1] ?? null, 'contact_id' => $this->contactIds[1] ?? null],
            ['invoice_number' => 'INV-2024-003', 'subject' => 'Professional License - Monthly', 'status' => 'sent', 'issue_date' => now()->subDays(5)->format('Y-m-d'), 'due_date' => now()->addDays(25)->format('Y-m-d'), 'subtotal' => 499.00, 'tax_amount' => 0, 'total' => 499.00, 'amount_paid' => 0, 'balance_due' => 499.00, 'organization_id' => $this->organizationIds[2] ?? null, 'contact_id' => $this->contactIds[2] ?? null],
            ['invoice_number' => 'INV-2024-004', 'subject' => 'Training Package - Advanced', 'status' => 'overdue', 'issue_date' => now()->subDays(45)->format('Y-m-d'), 'due_date' => now()->subDays(15)->format('Y-m-d'), 'subtotal' => 2500.00, 'tax_amount' => 0, 'total' => 2500.00, 'amount_paid' => 0, 'balance_due' => 2500.00, 'organization_id' => $this->organizationIds[3] ?? null, 'contact_id' => $this->contactIds[3] ?? null],
            ['invoice_number' => 'INV-2024-005', 'subject' => 'Starter License + Support', 'status' => 'draft', 'issue_date' => now()->format('Y-m-d'), 'due_date' => now()->addDays(30)->format('Y-m-d'), 'subtotal' => 398.00, 'tax_amount' => 0, 'total' => 398.00, 'amount_paid' => 0, 'balance_due' => 398.00, 'organization_id' => $this->organizationIds[4] ?? null, 'contact_id' => $this->contactIds[4] ?? null],
            ['invoice_number' => 'INV-2024-006', 'subject' => 'Data Migration Service', 'status' => 'partial', 'issue_date' => now()->subDays(20)->format('Y-m-d'), 'due_date' => now()->addDays(10)->format('Y-m-d'), 'subtotal' => 3000.00, 'tax_amount' => 0, 'total' => 3000.00, 'amount_paid' => 1500.00, 'balance_due' => 1500.00, 'organization_id' => $this->organizationIds[5] ?? null, 'contact_id' => $this->contactIds[5] ?? null],
            ['invoice_number' => 'INV-2024-007', 'subject' => 'Custom Integration x2', 'status' => 'sent', 'issue_date' => now()->subDays(3)->format('Y-m-d'), 'due_date' => now()->addDays(27)->format('Y-m-d'), 'subtotal' => 4000.00, 'tax_amount' => 0, 'total' => 4000.00, 'amount_paid' => 0, 'balance_due' => 4000.00, 'organization_id' => $this->organizationIds[6] ?? null, 'contact_id' => $this->contactIds[6] ?? null],
            ['invoice_number' => 'INV-2024-008', 'subject' => 'Enterprise License Renewal', 'status' => 'paid', 'issue_date' => now()->subDays(60)->format('Y-m-d'), 'due_date' => now()->subDays(30)->format('Y-m-d'), 'subtotal' => 11988.00, 'tax_amount' => 0, 'total' => 11988.00, 'amount_paid' => 11988.00, 'balance_due' => 0, 'organization_id' => $this->organizationIds[7] ?? null, 'contact_id' => $this->contactIds[7] ?? null],
        ];

        foreach ($invoices as $invoice) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($invoice, ['assigned_to' => $this->userId])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($invoices) . ' invoices');
    }

    private function seedEvents(): void
    {
        $module = DB::table('modules')->where('api_name', 'events')->first();
        if (!$module) {
            $this->command->warn('  Events module not found');
            return;
        }

        $events = [
            ['title' => 'Q4 Sales Kickoff', 'description' => 'Quarterly sales team meeting to review targets and strategies', 'type' => 'meeting', 'status' => 'scheduled', 'start_date' => now()->addDays(7)->setHour(9)->format('Y-m-d H:i:s'), 'end_date' => now()->addDays(7)->setHour(12)->format('Y-m-d H:i:s'), 'location' => 'Main Conference Room', 'is_all_day' => false],
            ['title' => 'Product Demo - TechStart', 'description' => 'Live product demonstration for potential enterprise client', 'type' => 'demo', 'status' => 'scheduled', 'start_date' => now()->addDays(3)->setHour(14)->format('Y-m-d H:i:s'), 'end_date' => now()->addDays(3)->setHour(15)->format('Y-m-d H:i:s'), 'location' => 'Zoom Meeting', 'is_all_day' => false, 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[1] ?? null],
            ['title' => 'Annual Customer Conference', 'description' => 'Yearly customer appreciation and product roadmap event', 'type' => 'conference', 'status' => 'scheduled', 'start_date' => now()->addDays(45)->format('Y-m-d'), 'end_date' => now()->addDays(47)->format('Y-m-d'), 'location' => 'Convention Center', 'is_all_day' => true],
            ['title' => 'Training: New Feature Rollout', 'description' => 'Internal training on upcoming feature release', 'type' => 'training', 'status' => 'scheduled', 'start_date' => now()->addDays(14)->setHour(10)->format('Y-m-d H:i:s'), 'end_date' => now()->addDays(14)->setHour(16)->format('Y-m-d H:i:s'), 'location' => 'Training Room B', 'is_all_day' => false],
            ['title' => 'Contract Negotiation - Global Industries', 'description' => 'Final contract terms discussion', 'type' => 'meeting', 'status' => 'scheduled', 'start_date' => now()->addDays(2)->setHour(11)->format('Y-m-d H:i:s'), 'end_date' => now()->addDays(2)->setHour(12)->format('Y-m-d H:i:s'), 'location' => 'Video Call', 'is_all_day' => false, 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[2] ?? null],
            ['title' => 'Trade Show - SaaS Connect', 'description' => 'Industry trade show booth and presentations', 'type' => 'trade_show', 'status' => 'scheduled', 'start_date' => now()->addDays(30)->format('Y-m-d'), 'end_date' => now()->addDays(32)->format('Y-m-d'), 'location' => 'Las Vegas Convention Center', 'is_all_day' => true],
            ['title' => 'Weekly Team Standup', 'description' => 'Regular team sync meeting', 'type' => 'meeting', 'status' => 'completed', 'start_date' => now()->subDays(1)->setHour(9)->format('Y-m-d H:i:s'), 'end_date' => now()->subDays(1)->setHour(9)->addMinutes(30)->format('Y-m-d H:i:s'), 'location' => 'Slack Huddle', 'is_all_day' => false],
            ['title' => 'Webinar: Best Practices for CRM', 'description' => 'Public webinar on CRM implementation best practices', 'type' => 'webinar', 'status' => 'scheduled', 'start_date' => now()->addDays(21)->setHour(13)->format('Y-m-d H:i:s'), 'end_date' => now()->addDays(21)->setHour(14)->format('Y-m-d H:i:s'), 'location' => 'Online', 'is_all_day' => false],
            ['title' => 'Customer Success Review - Acme Corp', 'description' => 'Quarterly business review with key customer', 'type' => 'meeting', 'status' => 'scheduled', 'start_date' => now()->addDays(10)->setHour(15)->format('Y-m-d H:i:s'), 'end_date' => now()->addDays(10)->setHour(16)->format('Y-m-d H:i:s'), 'location' => 'Customer Office', 'is_all_day' => false, 'related_to_type' => 'Organization', 'related_to_id' => $this->organizationIds[0] ?? null],
            ['title' => 'Holiday Office Closure', 'description' => 'Office closed for winter holidays', 'type' => 'holiday', 'status' => 'scheduled', 'start_date' => now()->addDays(60)->format('Y-m-d'), 'end_date' => now()->addDays(62)->format('Y-m-d'), 'location' => 'All Offices', 'is_all_day' => true],
        ];

        foreach ($events as $event) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($event, ['assigned_to' => $this->userId])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($events) . ' events');
    }

    private function seedQuotes(): void
    {
        $module = DB::table('modules')->where('api_name', 'quotes')->first();
        if (!$module) {
            $this->command->warn('  Quotes module not found');
            return;
        }

        $quotes = [
            ['quote_number' => 'QT-2024-001', 'title' => 'Enterprise Software Bundle', 'status' => 'accepted', 'valid_until' => now()->addDays(30)->format('Y-m-d'), 'subtotal' => 15988.00, 'discount_percent' => 10, 'discount_amount' => 1598.80, 'tax_amount' => 0, 'total' => 14389.20, 'organization_id' => $this->organizationIds[0] ?? null, 'contact_id' => $this->contactIds[0] ?? null, 'deal_id' => $this->dealIds[0] ?? null],
            ['quote_number' => 'QT-2024-002', 'title' => 'Professional Services Package', 'status' => 'sent', 'valid_until' => now()->addDays(14)->format('Y-m-d'), 'subtotal' => 7500.00, 'discount_percent' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 7500.00, 'organization_id' => $this->organizationIds[1] ?? null, 'contact_id' => $this->contactIds[1] ?? null, 'deal_id' => $this->dealIds[1] ?? null],
            ['quote_number' => 'QT-2024-003', 'title' => 'Starter Plan - Annual', 'status' => 'draft', 'valid_until' => now()->addDays(30)->format('Y-m-d'), 'subtotal' => 1188.00, 'discount_percent' => 15, 'discount_amount' => 178.20, 'tax_amount' => 0, 'total' => 1009.80, 'organization_id' => $this->organizationIds[2] ?? null, 'contact_id' => $this->contactIds[2] ?? null, 'deal_id' => $this->dealIds[2] ?? null],
            ['quote_number' => 'QT-2024-004', 'title' => 'Custom Integration Project', 'status' => 'sent', 'valid_until' => now()->addDays(21)->format('Y-m-d'), 'subtotal' => 12000.00, 'discount_percent' => 5, 'discount_amount' => 600.00, 'tax_amount' => 0, 'total' => 11400.00, 'organization_id' => $this->organizationIds[3] ?? null, 'contact_id' => $this->contactIds[3] ?? null, 'deal_id' => $this->dealIds[3] ?? null],
            ['quote_number' => 'QT-2024-005', 'title' => 'Data Migration + Training', 'status' => 'expired', 'valid_until' => now()->subDays(7)->format('Y-m-d'), 'subtotal' => 5500.00, 'discount_percent' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 5500.00, 'organization_id' => $this->organizationIds[4] ?? null, 'contact_id' => $this->contactIds[4] ?? null, 'deal_id' => $this->dealIds[4] ?? null],
            ['quote_number' => 'QT-2024-006', 'title' => 'Enterprise Renewal', 'status' => 'accepted', 'valid_until' => now()->subDays(30)->format('Y-m-d'), 'subtotal' => 11988.00, 'discount_percent' => 20, 'discount_amount' => 2397.60, 'tax_amount' => 0, 'total' => 9590.40, 'organization_id' => $this->organizationIds[5] ?? null, 'contact_id' => $this->contactIds[5] ?? null, 'deal_id' => $this->dealIds[5] ?? null],
            ['quote_number' => 'QT-2024-007', 'title' => 'Support Upgrade', 'status' => 'rejected', 'valid_until' => now()->subDays(14)->format('Y-m-d'), 'subtotal' => 3588.00, 'discount_percent' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 3588.00, 'organization_id' => $this->organizationIds[6] ?? null, 'contact_id' => $this->contactIds[6] ?? null, 'deal_id' => $this->dealIds[6] ?? null],
            ['quote_number' => 'QT-2024-008', 'title' => 'Full Platform License', 'status' => 'sent', 'valid_until' => now()->addDays(7)->format('Y-m-d'), 'subtotal' => 24988.00, 'discount_percent' => 12, 'discount_amount' => 2998.56, 'tax_amount' => 0, 'total' => 21989.44, 'organization_id' => $this->organizationIds[7] ?? null, 'contact_id' => $this->contactIds[7] ?? null, 'deal_id' => $this->dealIds[7] ?? null],
        ];

        foreach ($quotes as $quote) {
            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode(array_merge($quote, ['assigned_to' => $this->userId])),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  Created ' . count($quotes) . ' quotes');
    }

    private function seedWorkflows(): void
    {
        if (!Schema::hasTable('workflows')) {
            $this->command->warn('  Workflows table not found');
            return;
        }

        // Clear existing
        DB::table('workflows')->delete();

        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $leadsModule = DB::table('modules')->where('api_name', 'leads')->first();
        $contactsModule = DB::table('modules')->where('api_name', 'contacts')->first();

        $workflows = [];

        if ($dealsModule) {
            $workflows[] = [
                'name' => 'New Deal Notification',
                'description' => 'Sends notification when a new high-value deal is created',
                'module_id' => $dealsModule->id,
                'trigger_type' => 'record_created',
                'conditions' => json_encode([['field' => 'amount', 'operator' => '>=', 'value' => 100000]]),
                'is_active' => true,
                'created_by' => $this->userId,
                'steps' => [
                    ['type' => 'send_email', 'name' => 'Notify Sales Manager', 'config' => ['to' => 'sales-manager@company.com', 'subject' => 'New High-Value Deal Created', 'template' => 'new_deal_notification']],
                    ['type' => 'create_task', 'name' => 'Schedule Discovery Call', 'config' => ['subject' => 'Schedule discovery call for new deal', 'due_days' => 1, 'priority' => 'High']],
                ]
            ];

            $workflows[] = [
                'name' => 'Deal Stage Change Follow-up',
                'description' => 'Creates follow-up tasks when deal moves to negotiation',
                'module_id' => $dealsModule->id,
                'trigger_type' => 'field_changed',
                'watched_fields' => json_encode(['stage']),
                'conditions' => json_encode([['field' => 'stage', 'operator' => '=', 'value' => 'Negotiation']]),
                'is_active' => true,
                'created_by' => $this->userId,
                'steps' => [
                    ['type' => 'create_task', 'name' => 'Prepare Contract', 'config' => ['subject' => 'Prepare contract documents', 'due_days' => 2, 'priority' => 'High']],
                    ['type' => 'send_email', 'name' => 'Notify Legal', 'config' => ['to' => 'legal@company.com', 'subject' => 'Deal Moving to Negotiation', 'template' => 'deal_negotiation']],
                ]
            ];

            $workflows[] = [
                'name' => 'Stale Deal Alert',
                'description' => 'Alerts when deal has not been updated in 14 days',
                'module_id' => $dealsModule->id,
                'trigger_type' => 'time_based',
                'schedule_cron' => '0 9 * * *',
                'conditions' => json_encode([
                    ['field' => 'updated_at', 'operator' => '<', 'value' => '-14 days'],
                    ['field' => 'stage', 'operator' => 'not_in', 'value' => ['Closed Won', 'Closed Lost']]
                ]),
                'is_active' => true,
                'created_by' => $this->userId,
                'steps' => [
                    ['type' => 'send_email', 'name' => 'Alert Deal Owner', 'config' => ['to' => '{{assigned_to.email}}', 'subject' => 'Stale Deal Alert: {{name}}', 'template' => 'stale_deal_alert']],
                ]
            ];
        }

        if ($leadsModule) {
            $workflows[] = [
                'name' => 'Hot Lead Assignment',
                'description' => 'Assigns hot leads to senior sales reps',
                'module_id' => $leadsModule->id,
                'trigger_type' => 'record_created',
                'conditions' => json_encode([['field' => 'rating', 'operator' => '=', 'value' => 'Hot']]),
                'is_active' => true,
                'created_by' => $this->userId,
                'steps' => [
                    ['type' => 'update_field', 'name' => 'Set Priority Tag', 'config' => ['field' => 'tags', 'value' => ['High Priority']]],
                    ['type' => 'send_email', 'name' => 'Notify Team', 'config' => ['to' => 'sales-team@company.com', 'subject' => 'New Hot Lead: {{first_name}} {{last_name}}', 'template' => 'hot_lead_notification']],
                    ['type' => 'create_task', 'name' => 'Immediate Follow-up', 'config' => ['subject' => 'Call hot lead: {{first_name}} {{last_name}}', 'due_days' => 0, 'priority' => 'Urgent']],
                ]
            ];

            $workflows[] = [
                'name' => 'Lead Qualification Process',
                'description' => 'Triggers when lead status changes to Qualified',
                'module_id' => $leadsModule->id,
                'trigger_type' => 'field_changed',
                'watched_fields' => json_encode(['status']),
                'conditions' => json_encode([['field' => 'status', 'operator' => '=', 'value' => 'Qualified']]),
                'is_active' => true,
                'created_by' => $this->userId,
                'steps' => [
                    ['type' => 'send_email', 'name' => 'Send Welcome Email', 'config' => ['to' => '{{email}}', 'subject' => 'Thank you for your interest', 'template' => 'qualified_lead_welcome']],
                    ['type' => 'create_task', 'name' => 'Convert to Contact', 'config' => ['subject' => 'Convert qualified lead to contact', 'due_days' => 1, 'priority' => 'Normal']],
                ]
            ];
        }

        if ($contactsModule) {
            $workflows[] = [
                'name' => 'VIP Contact Welcome',
                'description' => 'Sends personalized welcome for VIP contacts',
                'module_id' => $contactsModule->id,
                'trigger_type' => 'record_created',
                'conditions' => json_encode([['field' => 'tags', 'operator' => 'contains', 'value' => 'VIP']]),
                'is_active' => true,
                'created_by' => $this->userId,
                'steps' => [
                    ['type' => 'send_email', 'name' => 'VIP Welcome', 'config' => ['to' => '{{email}}', 'subject' => 'Welcome to our VIP program', 'template' => 'vip_welcome']],
                    ['type' => 'create_task', 'name' => 'Schedule Intro Call', 'config' => ['subject' => 'Schedule VIP introduction call with {{first_name}}', 'due_days' => 2, 'priority' => 'High']],
                ]
            ];
        }

        foreach ($workflows as $workflowData) {
            $steps = $workflowData['steps'] ?? [];
            unset($workflowData['steps']);

            $workflowId = DB::table('workflows')->insertGetId($workflowData);

            if (Schema::hasTable('workflow_steps')) {
                foreach ($steps as $order => $step) {
                    DB::table('workflow_steps')->insert([
                        'workflow_id' => $workflowId,
                        'name' => $step['name'],
                        'action_type' => $step['type'],
                        'action_config' => json_encode($step['config']),
                        'order' => $order + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('  Created ' . count($workflows) . ' workflows');
    }

    private function seedBlueprints(): void
    {
        if (!Schema::hasTable('blueprints')) {
            return;
        }

        // Clear existing
        DB::table('blueprints')->delete();

        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $leadsModule = DB::table('modules')->where('api_name', 'leads')->first();

        if ($dealsModule) {
            $stageField = DB::table('fields')->where('module_id', $dealsModule->id)
                ->where('api_name', 'stage')
                ->first();

            if ($stageField) {
                $blueprintId = DB::table('blueprints')->insertGetId([
                    'name' => 'Deal Pipeline',
                    'module_id' => $dealsModule->id,
                    'field_id' => $stageField->id,
                    'description' => 'Standard sales pipeline workflow',
                    'is_active' => true,
                    'layout_data' => json_encode(['direction' => 'horizontal']),
                ]);

                // Create states
                $stateIds = [];
                $stageData = [
                    ['name' => 'Discovery', 'color' => '#6366f1', 'is_initial' => true, 'position_x' => 0],
                    ['name' => 'Qualification', 'color' => '#8b5cf6', 'is_initial' => false, 'position_x' => 200],
                    ['name' => 'Proposal', 'color' => '#a855f7', 'is_initial' => false, 'position_x' => 400],
                    ['name' => 'Negotiation', 'color' => '#d946ef', 'is_initial' => false, 'position_x' => 600],
                    ['name' => 'Closed Won', 'color' => '#22c55e', 'is_initial' => false, 'is_terminal' => true, 'position_x' => 800],
                    ['name' => 'Closed Lost', 'color' => '#ef4444', 'is_initial' => false, 'is_terminal' => true, 'position_x' => 800],
                ];

                foreach ($stageData as $index => $stage) {
                    $stateIds[$stage['name']] = DB::table('blueprint_states')->insertGetId([
                        'blueprint_id' => $blueprintId,
                        'name' => $stage['name'],
                        'field_option_value' => $stage['name'],
                        'color' => $stage['color'],
                        'is_initial' => $stage['is_initial'] ?? false,
                        'is_terminal' => $stage['is_terminal'] ?? false,
                        'position_x' => $stage['position_x'],
                        'position_y' => 100,
                    ]);
                }

                // Create transitions
                $transitions = [
                    ['from' => 'Discovery', 'to' => 'Qualification', 'name' => 'Qualify Lead', 'button_label' => 'Qualify'],
                    ['from' => 'Qualification', 'to' => 'Proposal', 'name' => 'Send Proposal', 'button_label' => 'Propose'],
                    ['from' => 'Proposal', 'to' => 'Negotiation', 'name' => 'Enter Negotiation', 'button_label' => 'Negotiate'],
                    ['from' => 'Negotiation', 'to' => 'Closed Won', 'name' => 'Close Deal', 'button_label' => 'Win'],
                    ['from' => 'Negotiation', 'to' => 'Closed Lost', 'name' => 'Lose Deal', 'button_label' => 'Lost'],
                    ['from' => 'Qualification', 'to' => 'Closed Lost', 'name' => 'Disqualify', 'button_label' => 'Disqualify'],
                ];

                foreach ($transitions as $order => $trans) {
                    DB::table('blueprint_transitions')->insert([
                        'blueprint_id' => $blueprintId,
                        'from_state_id' => $stateIds[$trans['from']],
                        'to_state_id' => $stateIds[$trans['to']],
                        'name' => $trans['name'],
                        'button_label' => $trans['button_label'],
                        'display_order' => $order,
                        'is_active' => true,
                    ]);
                }
            }
        }

        if ($leadsModule) {
            $statusField = DB::table('fields')->where('module_id', $leadsModule->id)
                ->where('api_name', 'status')
                ->first();

            if ($statusField) {
                $blueprintId = DB::table('blueprints')->insertGetId([
                    'name' => 'Lead Qualification',
                    'module_id' => $leadsModule->id,
                    'field_id' => $statusField->id,
                    'description' => 'Lead qualification workflow',
                    'is_active' => true,
                ]);

                $stateIds = [];
                $statusData = [
                    ['name' => 'New', 'color' => '#3b82f6', 'is_initial' => true],
                    ['name' => 'Contacted', 'color' => '#6366f1'],
                    ['name' => 'Qualified', 'color' => '#22c55e'],
                    ['name' => 'Converted', 'color' => '#10b981', 'is_terminal' => true],
                    ['name' => 'Lost', 'color' => '#ef4444', 'is_terminal' => true],
                ];

                foreach ($statusData as $index => $status) {
                    $stateIds[$status['name']] = DB::table('blueprint_states')->insertGetId([
                        'blueprint_id' => $blueprintId,
                        'name' => $status['name'],
                        'field_option_value' => $status['name'],
                        'color' => $status['color'],
                        'is_initial' => $status['is_initial'] ?? false,
                        'is_terminal' => $status['is_terminal'] ?? false,
                        'position_x' => $index * 180,
                        'position_y' => 100,
                    ]);
                }

                $transitions = [
                    ['from' => 'New', 'to' => 'Contacted', 'name' => 'Contact Lead'],
                    ['from' => 'Contacted', 'to' => 'Qualified', 'name' => 'Qualify Lead'],
                    ['from' => 'Qualified', 'to' => 'Converted', 'name' => 'Convert to Contact'],
                    ['from' => 'Contacted', 'to' => 'Lost', 'name' => 'Mark as Lost'],
                    ['from' => 'New', 'to' => 'Lost', 'name' => 'Disqualify'],
                ];

                foreach ($transitions as $order => $trans) {
                    DB::table('blueprint_transitions')->insert([
                        'blueprint_id' => $blueprintId,
                        'from_state_id' => $stateIds[$trans['from']],
                        'to_state_id' => $stateIds[$trans['to']],
                        'name' => $trans['name'],
                        'display_order' => $order,
                        'is_active' => true,
                    ]);
                }
            }
        }

        $this->command->info('  Created ' . DB::table('blueprints')->count() . ' blueprints');
    }

    private function seedApprovalRules(): void
    {
        if (!Schema::hasTable('approval_rules')) {
            return;
        }

        DB::table('approval_rules')->delete();

        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $users = DB::table('users')->take(3)->get();

        $rules = [
            [
                'name' => 'High-Value Deal Approval',
                'description' => 'Requires manager approval for deals over $100,000',
                'entity_type' => 'module_record',
                'module_id' => $dealsModule?->id,
                'conditions' => json_encode([
                    ['field' => 'amount', 'operator' => '>=', 'value' => 100000]
                ]),
                'approver_chain' => json_encode([
                    ['type' => 'manager', 'level' => 1],
                    ['type' => 'user', 'user_id' => $users->first()?->id]
                ]),
                'approval_type' => 'sequential',
                'sla_hours' => 24,
                'is_active' => true,
                'created_by' => $this->userId,
            ],
            [
                'name' => 'Discount Approval',
                'description' => 'Requires approval for discounts over 20%',
                'entity_type' => 'module_record',
                'module_id' => $dealsModule?->id,
                'conditions' => json_encode([
                    ['field' => 'discount_percent', 'operator' => '>', 'value' => 20]
                ]),
                'approver_chain' => json_encode([
                    ['type' => 'role', 'role' => 'sales_manager']
                ]),
                'approval_type' => 'any',
                'require_comments' => true,
                'sla_hours' => 48,
                'is_active' => true,
                'created_by' => $this->userId,
            ],
            [
                'name' => 'Enterprise Deal Review',
                'description' => 'Multi-level approval for enterprise deals',
                'entity_type' => 'module_record',
                'module_id' => $dealsModule?->id,
                'conditions' => json_encode([
                    ['field' => 'deal_type', 'operator' => '=', 'value' => 'Enterprise']
                ]),
                'approver_chain' => json_encode([
                    ['type' => 'user', 'user_id' => $users->skip(1)->first()?->id],
                    ['type' => 'user', 'user_id' => $users->skip(2)->first()?->id]
                ]),
                'approval_type' => 'sequential',
                'sla_hours' => 72,
                'is_active' => true,
                'created_by' => $this->userId,
            ],
        ];

        foreach ($rules as $rule) {
            DB::table('approval_rules')->insertGetId($rule);
        }

        $this->command->info('  Created ' . count($rules) . ' approval rules');
    }

    private function seedCadences(): void
    {
        if (!Schema::hasTable('cadences')) {
            return;
        }

        DB::table('cadences')->delete();

        $leadsModule = DB::table('modules')->where('api_name', 'leads')->first();
        $contactsModule = DB::table('modules')->where('api_name', 'contacts')->first();

        $cadences = [
            [
                'name' => 'New Lead Outreach',
                'description' => 'Initial outreach sequence for new inbound leads',
                'module_id' => $leadsModule?->id,
                'status' => 'active',
                'auto_enroll' => true,
                'entry_criteria' => json_encode(['status' => 'New', 'source' => 'Website']),
                'settings' => json_encode(['skip_weekends' => true, 'timezone' => 'America/New_York']),
                'created_by' => $this->userId,
                'owner_id' => $this->userId,
                'steps' => [
                    ['name' => 'Welcome Email', 'channel' => 'email', 'delay_type' => 'immediate', 'delay_value' => 0, 'subject' => 'Thanks for your interest!', 'content' => 'Hi {{first_name}}, thank you for reaching out...'],
                    ['name' => 'Follow-up Call', 'channel' => 'call', 'delay_type' => 'days', 'delay_value' => 1],
                    ['name' => 'Value Prop Email', 'channel' => 'email', 'delay_type' => 'days', 'delay_value' => 3, 'subject' => 'How we can help {{company}}'],
                    ['name' => 'LinkedIn Connect', 'channel' => 'linkedin', 'delay_type' => 'days', 'delay_value' => 5, 'linkedin_action' => 'connection_request'],
                    ['name' => 'Final Follow-up', 'channel' => 'email', 'delay_type' => 'days', 'delay_value' => 7, 'subject' => 'Still interested?'],
                ],
            ],
            [
                'name' => 'Renewal Outreach',
                'description' => 'Proactive renewal engagement 90 days before expiry',
                'module_id' => $contactsModule?->id,
                'status' => 'active',
                'auto_enroll' => false,
                'settings' => json_encode(['skip_weekends' => true]),
                'created_by' => $this->userId,
                'owner_id' => $this->userId,
                'steps' => [
                    ['name' => 'Renewal Notice', 'channel' => 'email', 'delay_type' => 'immediate', 'delay_value' => 0, 'subject' => 'Your subscription renewal is coming up'],
                    ['name' => 'Check-in Call', 'channel' => 'call', 'delay_type' => 'days', 'delay_value' => 14],
                    ['name' => 'Renewal Reminder', 'channel' => 'email', 'delay_type' => 'days', 'delay_value' => 30, 'subject' => '30 days until renewal'],
                    ['name' => 'Final Reminder', 'channel' => 'email', 'delay_type' => 'days', 'delay_value' => 14, 'subject' => 'Action required: Renewal'],
                ],
            ],
            [
                'name' => 'Re-engagement Campaign',
                'description' => 'Win back dormant leads',
                'module_id' => $leadsModule?->id,
                'status' => 'active',
                'auto_enroll' => false,
                'settings' => json_encode(['skip_weekends' => true]),
                'created_by' => $this->userId,
                'owner_id' => $this->userId,
                'steps' => [
                    ['name' => 'Re-introduction', 'channel' => 'email', 'delay_type' => 'immediate', 'delay_value' => 0, 'subject' => 'It\'s been a while, {{first_name}}'],
                    ['name' => 'New Features', 'channel' => 'email', 'delay_type' => 'days', 'delay_value' => 5, 'subject' => 'See what\'s new'],
                    ['name' => 'Special Offer', 'channel' => 'email', 'delay_type' => 'days', 'delay_value' => 10, 'subject' => 'Exclusive offer for you'],
                ],
            ],
        ];

        foreach ($cadences as $cadenceData) {
            $steps = $cadenceData['steps'] ?? [];
            unset($cadenceData['steps']);

            $cadenceId = DB::table('cadences')->insertGetId($cadenceData);

            foreach ($steps as $order => $step) {
                DB::table('cadence_steps')->insert([
                    'cadence_id' => $cadenceId,
                    'step_order' => $order + 1,
                    'name' => $step['name'],
                    'channel' => $step['channel'],
                    'delay_type' => $step['delay_type'],
                    'delay_value' => $step['delay_value'],
                    'subject' => $step['subject'] ?? null,
                    'content' => $step['content'] ?? null,
                    'linkedin_action' => $step['linkedin_action'] ?? null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('  Created ' . count($cadences) . ' cadences');
    }

    private function seedPlaybooks(): void
    {
        if (!Schema::hasTable('playbooks')) {
            return;
        }

        DB::table('playbooks')->delete();

        $playbooks = [
            [
                'name' => 'Enterprise Sales Playbook',
                'slug' => 'enterprise-sales',
                'description' => 'Structured approach for closing enterprise deals',
                'trigger_module' => 'deals',
                'trigger_condition' => 'deal_type_enterprise',
                'estimated_days' => 90,
                'is_active' => true,
                'auto_assign' => true,
                'tags' => json_encode(['enterprise', 'sales', 'high-value']),
                'created_by' => $this->userId,
                'phases' => [
                    ['name' => 'Discovery', 'order' => 1, 'tasks' => [
                        'Identify key stakeholders',
                        'Understand business challenges',
                        'Map decision-making process',
                        'Conduct needs assessment call',
                    ]],
                    ['name' => 'Solution Design', 'order' => 2, 'tasks' => [
                        'Create custom solution proposal',
                        'Prepare ROI analysis',
                        'Technical architecture review',
                        'Security assessment',
                    ]],
                    ['name' => 'Proposal & Negotiation', 'order' => 3, 'tasks' => [
                        'Present proposal to stakeholders',
                        'Address objections',
                        'Negotiate terms',
                        'Legal review',
                    ]],
                    ['name' => 'Close', 'order' => 4, 'tasks' => [
                        'Final contract review',
                        'Obtain signatures',
                        'Hand off to implementation',
                        'Send welcome package',
                    ]],
                ],
            ],
            [
                'name' => 'New Customer Onboarding',
                'slug' => 'new-customer-onboarding',
                'description' => 'Standard onboarding process for new customers',
                'trigger_module' => 'deals',
                'trigger_condition' => 'stage_closed_won',
                'estimated_days' => 30,
                'is_active' => true,
                'tags' => json_encode(['onboarding', 'customer-success']),
                'created_by' => $this->userId,
                'phases' => [
                    ['name' => 'Welcome', 'order' => 1, 'tasks' => [
                        'Send welcome email',
                        'Schedule kickoff call',
                        'Create customer folder',
                        'Assign CSM',
                    ]],
                    ['name' => 'Setup', 'order' => 2, 'tasks' => [
                        'Account provisioning',
                        'Data migration',
                        'Integration setup',
                        'User training',
                    ]],
                    ['name' => 'Go-Live', 'order' => 3, 'tasks' => [
                        'Final testing',
                        'Go-live support',
                        '30-day check-in',
                    ]],
                ],
            ],
            [
                'name' => 'Renewal Playbook',
                'slug' => 'renewal-playbook',
                'description' => 'Proactive renewal management process',
                'trigger_module' => 'contacts',
                'estimated_days' => 60,
                'is_active' => true,
                'tags' => json_encode(['renewal', 'retention']),
                'created_by' => $this->userId,
                'phases' => [
                    ['name' => 'Assessment', 'order' => 1, 'tasks' => [
                        'Review usage metrics',
                        'Identify expansion opportunities',
                        'Check support ticket history',
                    ]],
                    ['name' => 'Engagement', 'order' => 2, 'tasks' => [
                        'Schedule renewal call',
                        'Present value delivered',
                        'Discuss future needs',
                    ]],
                    ['name' => 'Close', 'order' => 3, 'tasks' => [
                        'Send renewal proposal',
                        'Process renewal',
                        'Update records',
                    ]],
                ],
            ],
        ];

        foreach ($playbooks as $playbookData) {
            $phases = $playbookData['phases'] ?? [];
            unset($playbookData['phases']);

            $playbookId = DB::table('playbooks')->insertGetId($playbookData);

            foreach ($phases as $phaseData) {
                $tasks = $phaseData['tasks'] ?? [];
                unset($phaseData['tasks']);

                $phaseId = DB::table('playbook_phases')->insertGetId([
                    'playbook_id' => $playbookId,
                    'name' => $phaseData['name'],
                    'display_order' => $phaseData['order'],
                ]);

                foreach ($tasks as $order => $taskName) {
                    DB::table('playbook_tasks')->insert([
                        'playbook_id' => $playbookId,
                        'phase_id' => $phaseId,
                        'title' => $taskName,
                        'display_order' => $order + 1,
                        'is_required' => true,
                    ]);
                }
            }
        }

        $this->command->info('  Created ' . count($playbooks) . ' playbooks');
    }

    private function seedReports(): void
    {
        if (!Schema::hasTable('reports')) {
            return;
        }

        DB::table('reports')->delete();

        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $leadsModule = DB::table('modules')->where('api_name', 'leads')->first();
        $contactsModule = DB::table('modules')->where('api_name', 'contacts')->first();

        $reports = [
            [
                'name' => 'Sales Pipeline Overview',
                'description' => 'Current deals by stage with amounts',
                'module_id' => $dealsModule?->id,
                'user_id' => $this->userId,
                'type' => 'chart',
                'chart_type' => 'bar',
                'is_public' => true,
                'config' => json_encode(['groupBy' => 'stage', 'metric' => 'sum', 'field' => 'amount']),
                'filters' => json_encode([['field' => 'stage', 'operator' => 'not_in', 'value' => ['Closed Won', 'Closed Lost']]]),
                'grouping' => json_encode([['field' => 'stage']]),
                'aggregations' => json_encode([['field' => 'amount', 'function' => 'sum']]),
            ],
            [
                'name' => 'Monthly Revenue',
                'description' => 'Closed won deals by month',
                'module_id' => $dealsModule?->id,
                'user_id' => $this->userId,
                'type' => 'chart',
                'chart_type' => 'line',
                'is_public' => true,
                'config' => json_encode(['groupBy' => 'close_date', 'metric' => 'sum', 'field' => 'amount', 'period' => 'month']),
                'filters' => json_encode([['field' => 'stage', 'operator' => '=', 'value' => 'Closed Won']]),
                'date_range' => json_encode(['type' => 'last_12_months']),
            ],
            [
                'name' => 'Lead Source Analysis',
                'description' => 'Lead count and conversion by source',
                'module_id' => $leadsModule?->id,
                'user_id' => $this->userId,
                'type' => 'chart',
                'chart_type' => 'pie',
                'is_public' => true,
                'config' => json_encode(['groupBy' => 'source', 'metric' => 'count']),
                'grouping' => json_encode([['field' => 'source']]),
            ],
            [
                'name' => 'Top Deals',
                'description' => 'Largest deals in pipeline',
                'module_id' => $dealsModule?->id,
                'user_id' => $this->userId,
                'type' => 'table',
                'is_public' => true,
                'config' => json_encode(['columns' => ['name', 'amount', 'stage', 'close_date', 'assigned_to']]),
                'sorting' => json_encode([['field' => 'amount', 'direction' => 'desc']]),
                'filters' => json_encode([['field' => 'stage', 'operator' => 'not_in', 'value' => ['Closed Won', 'Closed Lost']]]),
            ],
            [
                'name' => 'Sales Rep Performance',
                'description' => 'Deals won by sales rep',
                'module_id' => $dealsModule?->id,
                'user_id' => $this->userId,
                'type' => 'chart',
                'chart_type' => 'bar',
                'is_public' => true,
                'config' => json_encode(['groupBy' => 'assigned_to', 'metric' => 'sum', 'field' => 'amount']),
                'filters' => json_encode([['field' => 'stage', 'operator' => '=', 'value' => 'Closed Won']]),
                'date_range' => json_encode(['type' => 'this_quarter']),
            ],
            [
                'name' => 'Contact Growth',
                'description' => 'New contacts over time',
                'module_id' => $contactsModule?->id,
                'user_id' => $this->userId,
                'type' => 'chart',
                'chart_type' => 'area',
                'is_public' => false,
                'config' => json_encode(['groupBy' => 'created_at', 'metric' => 'count', 'period' => 'week']),
                'date_range' => json_encode(['type' => 'last_90_days']),
            ],
        ];

        foreach ($reports as $report) {
            DB::table('reports')->insertGetId($report);
        }

        $this->command->info('  Created ' . count($reports) . ' reports');
    }

    private function seedDashboards(): void
    {
        if (!Schema::hasTable('dashboards')) {
            return;
        }

        DB::table('dashboards')->delete();
        DB::table('dashboard_widgets')->delete();

        $reports = DB::table('reports')->get()->keyBy('name');

        $dashboards = [
            [
                'name' => 'Sales Dashboard',
                'description' => 'Overview of sales performance',
                'user_id' => $this->userId,
                'is_default' => true,
                'is_public' => true,
                'refresh_interval' => 300,
                'widgets' => [
                    ['title' => 'Pipeline Overview', 'type' => 'chart', 'report' => 'Sales Pipeline Overview', 'position' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]],
                    ['title' => 'Revenue Trend', 'type' => 'chart', 'report' => 'Monthly Revenue', 'position' => ['x' => 6, 'y' => 0, 'w' => 6, 'h' => 4]],
                    ['title' => 'Top Deals', 'type' => 'table', 'report' => 'Top Deals', 'position' => ['x' => 0, 'y' => 4, 'w' => 8, 'h' => 4]],
                    ['title' => 'Quick Stats', 'type' => 'metric', 'config' => ['metrics' => ['total_deals', 'total_value', 'avg_deal_size']], 'position' => ['x' => 8, 'y' => 4, 'w' => 4, 'h' => 4]],
                ],
            ],
            [
                'name' => 'Lead Dashboard',
                'description' => 'Lead management overview',
                'user_id' => $this->userId,
                'is_default' => false,
                'is_public' => true,
                'widgets' => [
                    ['title' => 'Lead Sources', 'type' => 'chart', 'report' => 'Lead Source Analysis', 'position' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]],
                    ['title' => 'Lead Metrics', 'type' => 'metric', 'config' => ['metrics' => ['new_leads_today', 'conversion_rate', 'avg_response_time']], 'position' => ['x' => 6, 'y' => 0, 'w' => 6, 'h' => 4]],
                ],
            ],
            [
                'name' => 'Manager Dashboard',
                'description' => 'Team performance overview',
                'user_id' => $this->userId,
                'is_default' => false,
                'is_public' => false,
                'widgets' => [
                    ['title' => 'Team Performance', 'type' => 'chart', 'report' => 'Sales Rep Performance', 'position' => ['x' => 0, 'y' => 0, 'w' => 8, 'h' => 4]],
                    ['title' => 'Pipeline', 'type' => 'chart', 'report' => 'Sales Pipeline Overview', 'position' => ['x' => 8, 'y' => 0, 'w' => 4, 'h' => 4]],
                ],
            ],
        ];

        foreach ($dashboards as $dashboardData) {
            $widgets = $dashboardData['widgets'] ?? [];
            unset($dashboardData['widgets']);

            $dashboardId = DB::table('dashboards')->insertGetId($dashboardData);

            foreach ($widgets as $widgetData) {
                $reportId = null;
                if (isset($widgetData['report']) && isset($reports[$widgetData['report']])) {
                    $reportId = $reports[$widgetData['report']]->id;
                }
                unset($widgetData['report']);

                DB::table('dashboard_widgets')->insert([
                    'dashboard_id' => $dashboardId,
                    'report_id' => $reportId,
                    'title' => $widgetData['title'],
                    'type' => $widgetData['type'],
                    'config' => json_encode($widgetData['config'] ?? []),
                    'grid_position' => json_encode($widgetData['position']),
                ]);
            }
        }

        $this->command->info('  Created ' . count($dashboards) . ' dashboards');
    }

    private function seedForecasts(): void
    {
        if (!Schema::hasTable('forecast_scenarios')) {
            return;
        }

        DB::table('forecast_scenarios')->delete();

        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $now = now();

        $forecasts = [
            [
                'name' => 'Q4 2024 Baseline',
                'description' => 'Official Q4 forecast',
                'user_id' => $this->userId,
                'module_id' => $dealsModule?->id,
                'period_start' => $now->copy()->startOfQuarter()->toDateString(),
                'period_end' => $now->copy()->endOfQuarter()->toDateString(),
                'scenario_type' => 'baseline',
                'is_baseline' => true,
                'is_shared' => true,
                'total_weighted' => 850000,
                'total_unweighted' => 1200000,
                'target_amount' => 1000000,
                'deal_count' => 45,
            ],
            [
                'name' => 'Q4 Best Case',
                'description' => 'Optimistic scenario',
                'user_id' => $this->userId,
                'module_id' => $dealsModule?->id,
                'period_start' => $now->copy()->startOfQuarter()->toDateString(),
                'period_end' => $now->copy()->endOfQuarter()->toDateString(),
                'scenario_type' => 'best_case',
                'is_baseline' => false,
                'is_shared' => true,
                'total_weighted' => 1100000,
                'total_unweighted' => 1500000,
                'target_amount' => 1000000,
                'deal_count' => 52,
            ],
            [
                'name' => 'Q4 Worst Case',
                'description' => 'Conservative scenario',
                'user_id' => $this->userId,
                'module_id' => $dealsModule?->id,
                'period_start' => $now->copy()->startOfQuarter()->toDateString(),
                'period_end' => $now->copy()->endOfQuarter()->toDateString(),
                'scenario_type' => 'worst_case',
                'is_baseline' => false,
                'is_shared' => true,
                'total_weighted' => 650000,
                'total_unweighted' => 900000,
                'target_amount' => 1000000,
                'deal_count' => 38,
            ],
            [
                'name' => 'Q1 2025 Planning',
                'description' => 'Next quarter forecast',
                'user_id' => $this->userId,
                'module_id' => $dealsModule?->id,
                'period_start' => $now->copy()->addQuarter()->startOfQuarter()->toDateString(),
                'period_end' => $now->copy()->addQuarter()->endOfQuarter()->toDateString(),
                'scenario_type' => 'custom',
                'is_baseline' => false,
                'is_shared' => false,
                'total_weighted' => 400000,
                'total_unweighted' => 600000,
                'target_amount' => 1200000,
                'deal_count' => 28,
            ],
        ];

        foreach ($forecasts as $forecast) {
            DB::table('forecast_scenarios')->insertGetId($forecast);
        }

        $this->command->info('  Created ' . count($forecasts) . ' forecasts');
    }

    private function seedQuotas(): void
    {
        if (!Schema::hasTable('quota_periods') || !Schema::hasTable('quotas')) {
            return;
        }

        DB::table('quota_periods')->delete();
        DB::table('quotas')->delete();

        $now = now();
        $users = DB::table('users')->take(5)->get();

        // Create quota periods
        $periods = [
            [
                'name' => 'Q4 2024',
                'period_type' => 'quarterly',
                'start_date' => $now->copy()->startOfQuarter()->toDateString(),
                'end_date' => $now->copy()->endOfQuarter()->toDateString(),
                'is_active' => true,
            ],
            [
                'name' => 'December 2024',
                'period_type' => 'monthly',
                'start_date' => $now->copy()->startOfMonth()->toDateString(),
                'end_date' => $now->copy()->endOfMonth()->toDateString(),
                'is_active' => true,
            ],
            [
                'name' => '2024',
                'period_type' => 'annual',
                'start_date' => $now->copy()->startOfYear()->toDateString(),
                'end_date' => $now->copy()->endOfYear()->toDateString(),
                'is_active' => true,
            ],
        ];

        $createdPeriodIds = [];
        foreach ($periods as $period) {
            $createdPeriodIds[] = DB::table('quota_periods')->insertGetId($period);
        }

        // Create quotas for each user
        foreach ($users as $index => $user) {
            // Quarterly revenue quota
            DB::table('quotas')->insert([
                'period_id' => $createdPeriodIds[0],
                'user_id' => $user->id,
                'metric_type' => 'revenue',
                'metric_field' => 'amount',
                'module_api_name' => 'deals',
                'target_value' => 250000 + ($index * 50000),
                'current_value' => rand(100000, 300000),
                'attainment_percent' => rand(40, 120),
                'currency' => 'USD',
                'created_by' => $this->userId,
            ]);

            // Monthly deal count quota
            DB::table('quotas')->insert([
                'period_id' => $createdPeriodIds[1],
                'user_id' => $user->id,
                'metric_type' => 'count',
                'module_api_name' => 'deals',
                'target_value' => 10 + $index,
                'current_value' => rand(5, 15),
                'attainment_percent' => rand(50, 150),
                'currency' => 'USD',
                'created_by' => $this->userId,
            ]);
        }

        $this->command->info('  Created ' . count($periods) . ' quota periods and ' . DB::table('quotas')->count() . ' quotas');
    }

    private function seedEmailTemplates(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')->delete();

        $leadsModule = DB::table('modules')->where('api_name', 'leads')->first();
        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $contactsModule = DB::table('modules')->where('api_name', 'contacts')->first();

        $templates = [
            [
                'name' => 'Welcome Email',
                'description' => 'Initial welcome email for new leads',
                'type' => 'user',
                'module_id' => $leadsModule?->id,
                'subject' => 'Welcome {{first_name}}! Let\'s get started',
                'body_html' => '<h1>Welcome, {{first_name}}!</h1><p>Thank you for your interest in our services. We\'re excited to help {{company}} achieve its goals.</p><p>Best regards,<br>{{sender_name}}</p>',
                'body_text' => 'Welcome, {{first_name}}! Thank you for your interest in our services.',
                'variables' => json_encode(['first_name', 'company', 'sender_name']),
                'is_active' => true,
                'category' => 'outreach',
                'tags' => json_encode(['welcome', 'new-lead']),
                'created_by' => $this->userId,
            ],
            [
                'name' => 'Follow-up Email',
                'description' => 'Generic follow-up after meeting',
                'type' => 'user',
                'module_id' => $contactsModule?->id,
                'subject' => 'Great speaking with you, {{first_name}}',
                'body_html' => '<p>Hi {{first_name}},</p><p>Thank you for taking the time to speak with me today. As discussed, here are the next steps:</p><ul><li>{{next_step_1}}</li><li>{{next_step_2}}</li></ul><p>Looking forward to continuing our conversation.</p>',
                'body_text' => 'Hi {{first_name}}, Thank you for taking the time to speak with me today.',
                'variables' => json_encode(['first_name', 'next_step_1', 'next_step_2']),
                'is_active' => true,
                'category' => 'follow-up',
                'tags' => json_encode(['follow-up', 'meeting']),
                'created_by' => $this->userId,
            ],
            [
                'name' => 'Proposal Email',
                'description' => 'Send proposal to prospect',
                'type' => 'user',
                'module_id' => $dealsModule?->id,
                'subject' => 'Your Custom Proposal - {{deal_name}}',
                'body_html' => '<p>Dear {{first_name}},</p><p>Please find attached our proposal for {{deal_name}}.</p><p>The total investment is <strong>{{amount}}</strong>.</p><p>This proposal is valid until {{valid_until}}.</p><p>Let me know if you have any questions.</p>',
                'body_text' => 'Dear {{first_name}}, Please find attached our proposal for {{deal_name}}.',
                'variables' => json_encode(['first_name', 'deal_name', 'amount', 'valid_until']),
                'is_active' => true,
                'category' => 'proposal',
                'tags' => json_encode(['proposal', 'sales']),
                'created_by' => $this->userId,
            ],
            [
                'name' => 'Deal Won Thank You',
                'description' => 'Congratulations email when deal is won',
                'type' => 'system',
                'module_id' => $dealsModule?->id,
                'subject' => 'Welcome aboard, {{company}}!',
                'body_html' => '<h1>Thank you for choosing us!</h1><p>We\'re thrilled to welcome {{company}} as a customer.</p><p>Your Customer Success Manager will reach out within 24 hours to begin onboarding.</p>',
                'body_text' => 'Thank you for choosing us! We\'re thrilled to welcome {{company}} as a customer.',
                'variables' => json_encode(['company']),
                'is_active' => true,
                'is_default' => true,
                'category' => 'notification',
                'tags' => json_encode(['won', 'welcome', 'automation']),
                'created_by' => $this->userId,
            ],
            [
                'name' => 'Meeting Request',
                'description' => 'Request for a meeting',
                'type' => 'user',
                'module_id' => $leadsModule?->id,
                'subject' => 'Can we schedule a quick call, {{first_name}}?',
                'body_html' => '<p>Hi {{first_name}},</p><p>I\'d love to learn more about {{company}} and discuss how we might be able to help.</p><p>Do you have 15-20 minutes this week for a quick call?</p><p><a href="{{calendar_link}}">Book a time here</a></p>',
                'body_text' => 'Hi {{first_name}}, I\'d love to learn more about {{company}}. Do you have time for a quick call?',
                'variables' => json_encode(['first_name', 'company', 'calendar_link']),
                'is_active' => true,
                'category' => 'outreach',
                'tags' => json_encode(['meeting', 'scheduling']),
                'created_by' => $this->userId,
            ],
            [
                'name' => 'Re-engagement',
                'description' => 'Re-engage with cold leads',
                'type' => 'user',
                'module_id' => $leadsModule?->id,
                'subject' => 'Still interested in {{topic}}, {{first_name}}?',
                'body_html' => '<p>Hi {{first_name}},</p><p>It\'s been a while since we last connected. I wanted to check in and see if {{company}} is still looking for solutions in {{topic}}.</p><p>A lot has changed since we last spoke - would you be open to a quick catch-up?</p>',
                'body_text' => 'Hi {{first_name}}, It\'s been a while. Are you still interested in {{topic}}?',
                'variables' => json_encode(['first_name', 'company', 'topic']),
                'is_active' => true,
                'category' => 'outreach',
                'tags' => json_encode(['re-engagement', 'cold-leads']),
                'created_by' => $this->userId,
            ],
        ];

        foreach ($templates as $template) {
            DB::table('email_templates')->insertGetId($template);
        }

        $this->command->info('  Created ' . count($templates) . ' email templates');
    }

    private function seedNotifications(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')->delete();

        $users = DB::table('users')->take(3)->get();
        if ($users->isEmpty()) {
            return;
        }

        $notifications = [
            // Approval notifications
            [
                'type' => 'approval.pending',
                'category' => 'approvals',
                'title' => 'Approval Required: High-Value Deal',
                'body' => 'Deal "Enterprise License Agreement - Acme" ($250,000) requires your approval.',
                'icon' => 'check-circle',
                'icon_color' => 'warning',
                'action_url' => '/deals',
                'action_label' => 'Review Deal',
            ],
            [
                'type' => 'approval.approved',
                'category' => 'approvals',
                'title' => 'Deal Approved',
                'body' => 'Your deal "Cloud Migration Project" has been approved by the manager.',
                'icon' => 'check-circle',
                'icon_color' => 'success',
                'action_url' => '/deals',
                'action_label' => 'View Deal',
                'read_at' => now()->subHours(2),
            ],

            // Assignment notifications
            [
                'type' => 'assignment.new',
                'category' => 'assignments',
                'title' => 'New Lead Assigned',
                'body' => 'You have been assigned a new hot lead: Tony Stark from Stark Innovations.',
                'icon' => 'user-plus',
                'icon_color' => 'primary',
                'action_url' => '/records/leads',
                'action_label' => 'View Lead',
            ],
            [
                'type' => 'assignment.new',
                'category' => 'assignments',
                'title' => 'Deal Reassigned to You',
                'body' => 'The deal "Data Warehouse Solution" has been reassigned to you.',
                'icon' => 'arrow-right-circle',
                'icon_color' => 'info',
                'action_url' => '/deals',
                'action_label' => 'View Deal',
            ],

            // Mention notifications
            [
                'type' => 'mention.comment',
                'category' => 'mentions',
                'title' => 'You were mentioned in a comment',
                'body' => 'Sarah Johnson mentioned you: "@user Can you review the proposal before tomorrow?"',
                'icon' => 'at-sign',
                'icon_color' => 'primary',
                'action_url' => '/deals',
                'action_label' => 'View Comment',
            ],

            // Deal notifications
            [
                'type' => 'deal.won',
                'category' => 'deals',
                'title' => 'Deal Won! 🎉',
                'body' => 'Congratulations! The deal "Training Program - EduLearn" ($45,000) has been closed won.',
                'icon' => 'trophy',
                'icon_color' => 'success',
                'action_url' => '/deals',
                'action_label' => 'View Deal',
            ],
            [
                'type' => 'deal.stage_changed',
                'category' => 'deals',
                'title' => 'Deal Stage Updated',
                'body' => 'Deal "Mobile App Development" moved from Proposal to Negotiation.',
                'icon' => 'trending-up',
                'icon_color' => 'info',
                'action_url' => '/deals',
                'action_label' => 'View Deal',
                'read_at' => now()->subHours(5),
            ],

            // Task notifications
            [
                'type' => 'task.assigned',
                'category' => 'tasks',
                'title' => 'New Task Assigned',
                'body' => 'Task: "Follow up with Acme on proposal" is due in 2 days.',
                'icon' => 'clipboard-list',
                'icon_color' => 'primary',
                'action_url' => '/records/tasks',
                'action_label' => 'View Task',
            ],
            [
                'type' => 'task.overdue',
                'category' => 'tasks',
                'title' => 'Task Overdue',
                'body' => 'Task "Send contract to TechStart" is now overdue.',
                'icon' => 'alert-triangle',
                'icon_color' => 'error',
                'action_url' => '/records/tasks',
                'action_label' => 'View Task',
            ],

            // Reminder notifications
            [
                'type' => 'reminder.followup',
                'category' => 'reminders',
                'title' => 'Follow-up Reminder',
                'body' => 'Reminder: Follow up with Bruce Wayne (Wayne Industries) - marked as hot lead 3 days ago.',
                'icon' => 'bell',
                'icon_color' => 'warning',
                'action_url' => '/records/leads',
                'action_label' => 'View Lead',
            ],
            [
                'type' => 'reminder.activity',
                'category' => 'reminders',
                'title' => 'Meeting in 30 minutes',
                'body' => 'Upcoming: Discovery call with DataFlow Analytics at 2:00 PM.',
                'icon' => 'calendar',
                'icon_color' => 'info',
                'action_url' => '/records/activities',
                'action_label' => 'View Activity',
            ],

            // System notifications
            [
                'type' => 'system.announcement',
                'category' => 'system',
                'title' => 'New Feature: Workflow Automation',
                'body' => 'Workflow automation is now available! Create custom triggers and actions for your sales processes.',
                'icon' => 'sparkles',
                'icon_color' => 'primary',
                'action_url' => '/workflows',
                'action_label' => 'Explore Workflows',
                'read_at' => now()->subDays(1),
            ],

            // Updates
            [
                'type' => 'record.updated',
                'category' => 'updates',
                'title' => 'Contact Information Updated',
                'body' => 'John Smith\'s contact details have been updated by the system.',
                'icon' => 'refresh-cw',
                'icon_color' => 'muted',
                'action_url' => '/records/contacts',
                'action_label' => 'View Contact',
                'read_at' => now()->subHours(12),
            ],
        ];

        foreach ($users as $userIndex => $user) {
            foreach ($notifications as $index => $notification) {
                // Distribute notifications across users, give first user more
                if ($userIndex === 0 || $index % 3 === $userIndex) {
                    DB::table('notifications')->insert(array_merge($notification, [
                        'user_id' => $user->id,
                        'created_at' => now()->subMinutes(rand(5, 2880)), // Random time in last 2 days
                        'updated_at' => now(),
                    ]));
                }
            }
        }

        $this->command->info('  Created ' . DB::table('notifications')->count() . ' notifications');
    }
}
