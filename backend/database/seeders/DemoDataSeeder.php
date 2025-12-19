<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertHistory;
use App\Models\AnalyticsAlertSubscription;
use App\Models\Call;
use App\Models\CallProvider;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatVisitor;
use App\Models\ChatWidget;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\CustomerHealthScore;
use App\Models\DocumentSendLog;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVariable;
use App\Models\ForecastAdjustment;
use App\Models\ForecastScenario;
use App\Models\ForecastSnapshot;
use App\Models\GeneratedDocument;
use App\Models\Goal;
use App\Models\GoalMilestone;
use App\Models\GoalProgressLog;
use App\Models\HealthScoreHistory;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\InvoicePayment;
use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Models\LandingPageVariant;
use App\Models\LandingPageVisit;
use App\Models\MeetingAnalyticsCache;
use App\Models\MeetingType;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\PortalActivityLog;
use App\Models\PortalInvitation;
use App\Models\PortalUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quota;
use App\Models\QuotaPeriod;
use App\Models\QuotaSnapshot;
use App\Models\Renewal;
use App\Models\RenewalActivity;
use App\Models\RenewalForecast;
use App\Models\RenewalReminder;
use App\Models\ScheduledMeeting;
use App\Models\SchedulingPage;
use App\Models\SignatureAuditLog;
use App\Models\SignatureField;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Models\SmsConnection;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\SupportTeam;
use App\Models\SupportTicket;
use App\Models\TicketActivity;
use App\Models\TicketCannedResponse;
use App\Models\TicketCategory;
use App\Models\TicketEscalation;
use App\Models\TicketReply;
use App\Models\User;
use App\Models\VideoMeeting;
use App\Models\VideoProvider;
use App\Models\WebForm;
use App\Models\WebFormField;
use App\Models\WebFormSubmission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Demo Data Seeding...');

        // First, seed permissions if needed
        $this->seedPermissions();

        // Wrap in transaction
        DB::beginTransaction();

        try {
            // Get or create users
            $users = $this->seedUsers();

            // Core CRM Records (Contacts, Organizations, Deals, Leads)
            $this->seedCoreRecords($users);

            // Products & Categories
            $this->seedProducts();

            // Support System
            $this->seedSupportSystem($users);

            // CMS & Web Forms
            $this->seedCmsWebForms($users);

            // Invoices & Contracts
            $this->seedInvoicesContracts($users);

            // Renewals
            $this->seedRenewals($users);

            // Quotas & Goals
            $this->seedQuotasGoals($users);

            // Documents & Signatures
            $this->seedDocumentsSignatures($users);

            // Communications (SMS, Chat)
            $this->seedCommunications($users);

            // Meetings & Calls
            $this->seedMeetingsCalls($users);

            // Analytics & Alerts
            $this->seedAnalyticsAlerts($users);

            // Customer Health
            $this->seedCustomerHealth();

            // Portal Users
            $this->seedPortalUsers();

            DB::commit();
            $this->command->info('Demo Data Seeding Complete!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Seed permissions and roles if not already present.
     */
    private function seedPermissions(): void
    {
        $permissionCount = \Spatie\Permission\Models\Permission::count();
        if ($permissionCount === 0) {
            $this->command->info('  Seeding Permissions...');
            $this->call(RolesAndPermissionsSeeder::class);
            $this->call(ModulePermissionsSeeder::class);
        }

        // Ensure all users have admin role
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );

        // Sync all permissions to admin role
        $permissions = \Spatie\Permission\Models\Permission::all();
        $adminRole->syncPermissions($permissions);

        // Assign admin role to all users without a role
        User::whereDoesntHave('roles')->each(function ($user) use ($adminRole) {
            $user->assignRole($adminRole);
        });
    }

    /**
     * Check if a table exists.
     */
    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * Get existing users or create demo users.
     */
    private function seedUsers(): array
    {
        if (!$this->tableExists('users')) {
            $this->command->warn('  Skipping users - table does not exist');
            return [];
        }

        $users = User::take(5)->get();

        if ($users->count() < 3) {
            $users = User::factory()->count(5)->create();
        }

        return $users->all();
    }

    /**
     * Seed products and categories.
     */
    private function seedProducts(): void
    {
        if (!$this->tableExists('products') || !$this->tableExists('product_categories')) {
            $this->command->warn('  Skipping Products - tables do not exist');
            return;
        }

        $this->command->info('  Seeding Products...');

        // Create categories
        $categories = [];
        $categoryNames = ['Software', 'Services', 'Support', 'Training', 'Add-ons'];

        foreach ($categoryNames as $index => $name) {
            $categories[] = ProductCategory::factory()->create([
                'name' => $name,
                'display_order' => $index + 1,
            ]);
        }

        // Create products in each category
        foreach ($categories as $category) {
            Product::factory()->count(3)->create([
                'category_id' => $category->id,
            ]);
        }
    }

    /**
     * Seed support system (teams, tickets, canned responses).
     */
    private function seedSupportSystem(array $users): void
    {
        if (!$this->tableExists('support_tickets')) {
            $this->command->warn('  Skipping Support System - tables do not exist');
            return;
        }

        $this->command->info('  Seeding Support System...');

        $userId = $users[0]->id ?? null;

        // Create support teams
        $teams = [];
        if ($this->tableExists('support_teams')) {
            $teams = [
                SupportTeam::factory()->technical()->create(['lead_id' => $userId]),
                SupportTeam::factory()->billing()->create(['lead_id' => $users[1]->id ?? $userId]),
            ];
        }

        // Create ticket categories
        $categories = [];
        if ($this->tableExists('ticket_categories')) {
            $categories = [
                TicketCategory::factory()->technical()->create(),
                TicketCategory::factory()->billing()->create(),
                TicketCategory::factory()->featureRequest()->create(),
            ];
        }

        // Create canned responses
        if ($this->tableExists('ticket_canned_responses')) {
            TicketCannedResponse::factory()->greeting()->create();
            TicketCannedResponse::factory()->closing()->create();
            TicketCannedResponse::factory()->count(5)->create();
        }

        // Create tickets with various statuses
        foreach (['open', 'pending', 'inProgress', 'resolved', 'closed'] as $status) {
            $ticketData = [];
            if (!empty($categories)) {
                $ticketData['category_id'] = $categories[array_rand($categories)]->id;
            }
            if (!empty($teams)) {
                $ticketData['team_id'] = $teams[array_rand($teams)]->id;
            }
            if (!empty($users)) {
                $ticketData['assigned_to'] = $users[array_rand($users)]->id;
            }

            $ticket = SupportTicket::factory()->{$status}()->create($ticketData);

            // Add replies
            if ($this->tableExists('ticket_replies')) {
                TicketReply::factory()->fromAgent()->count(2)->create(['ticket_id' => $ticket->id]);
                TicketReply::factory()->fromCustomer()->create(['ticket_id' => $ticket->id]);
            }

            // Add activities
            if ($this->tableExists('ticket_activities')) {
                TicketActivity::factory()->created()->create(['ticket_id' => $ticket->id]);
                TicketActivity::factory()->assigned()->create(['ticket_id' => $ticket->id]);
            }

            // Add escalation for some tickets
            if ($status === 'inProgress' && $this->tableExists('ticket_escalations')) {
                TicketEscalation::factory()->responseSla()->create(['ticket_id' => $ticket->id]);
            }
        }

        // Create some urgent tickets
        $urgentData = [];
        if (!empty($categories)) {
            $urgentData['category_id'] = $categories[0]->id;
        }
        if (!empty($teams)) {
            $urgentData['team_id'] = $teams[0]->id;
        }
        SupportTicket::factory()->urgent()->count(2)->create($urgentData);
    }

    /**
     * Seed CMS and web forms.
     */
    private function seedCmsWebForms(array $users): void
    {
        if (!$this->tableExists('web_forms')) {
            $this->command->warn('  Skipping CMS & Web Forms - tables do not exist');
            return;
        }

        $this->command->info('  Seeding CMS & Web Forms...');

        $userId = $users[0]->id ?? null;

        // Create web forms
        $forms = [
            WebForm::factory()->contactForm()->create(['created_by' => $userId]),
            WebForm::factory()->demoRequest()->create(['created_by' => $userId]),
            WebForm::factory()->newsletter()->create(['created_by' => $userId]),
        ];

        if ($this->tableExists('web_form_fields')) {
            foreach ($forms as $form) {
                // Add fields to each form
                WebFormField::factory()->email()->create(['web_form_id' => $form->id, 'display_order' => 1]);
                WebFormField::factory()->text('First Name')->required()->create(['web_form_id' => $form->id, 'display_order' => 2]);
                WebFormField::factory()->text('Last Name')->create(['web_form_id' => $form->id, 'display_order' => 3]);
                WebFormField::factory()->phone()->create(['web_form_id' => $form->id, 'display_order' => 4]);
                WebFormField::factory()->textarea('Message')->create(['web_form_id' => $form->id, 'display_order' => 5]);
            }
        }

        if ($this->tableExists('web_form_submissions')) {
            foreach ($forms as $form) {
                WebFormSubmission::factory()->processed()->count(10)->create(['web_form_id' => $form->id]);
                WebFormSubmission::factory()->spam()->count(2)->create(['web_form_id' => $form->id]);
            }
        }

        // Landing pages
        if (!$this->tableExists('landing_pages')) {
            return;
        }

        $templates = [];
        if ($this->tableExists('landing_page_templates')) {
            $templates = [
                LandingPageTemplate::factory()->leadCapture()->system()->create(),
                LandingPageTemplate::factory()->webinar()->system()->create(),
                LandingPageTemplate::factory()->event()->system()->create(),
            ];
        }

        // Create landing pages
        foreach ($templates as $template) {
            $page = LandingPage::factory()->published()->create([
                'template_id' => $template->id,
                'web_form_id' => $forms[0]->id,
                'created_by' => $userId,
            ]);

            // Add variants for A/B testing
            if ($this->tableExists('landing_page_variants')) {
                LandingPageVariant::factory()->variantA()->create(['page_id' => $page->id]);
                LandingPageVariant::factory()->variantB()->create(['page_id' => $page->id]);
            }

            // Add visits
            if ($this->tableExists('landing_page_visits')) {
                LandingPageVisit::factory()->desktop()->fromGoogle()->count(20)->create(['page_id' => $page->id]);
                LandingPageVisit::factory()->mobile()->fromSocial()->count(10)->create(['page_id' => $page->id]);
                LandingPageVisit::factory()->converted()->count(5)->create(['page_id' => $page->id]);
            }
        }

        // Create draft pages
        if (!empty($templates)) {
            LandingPage::factory()->draft()->count(2)->create([
                'template_id' => $templates[0]->id,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * Seed invoices and contracts.
     */
    private function seedInvoicesContracts(array $users): void
    {
        $userId = $users[0]->id ?? null;

        // Invoices
        if ($this->tableExists('invoices')) {
            $this->command->info('  Seeding Invoices...');

            $products = $this->tableExists('products') ? Product::take(5)->get() : collect();

            // Create invoices with various statuses
            foreach (['draft', 'sent', 'paid', 'partial', 'overdue'] as $status) {
                $invoice = Invoice::factory()->{$status}()->create([
                    'created_by' => !empty($users) ? $users[array_rand($users)]->id : $userId,
                ]);

                // Add line items
                if ($this->tableExists('invoice_line_items') && $products->isNotEmpty()) {
                    foreach ($products->random(min(3, $products->count())) as $index => $product) {
                        InvoiceLineItem::factory()->create([
                            'invoice_id' => $invoice->id,
                            'product_id' => $product->id,
                            'description' => $product->name,
                            'unit_price' => $product->unit_price,
                            'display_order' => $index + 1,
                        ]);
                    }
                }

                // Add payments for paid/partial invoices
                if (in_array($status, ['paid', 'partial']) && $this->tableExists('invoice_payments')) {
                    InvoicePayment::factory()->creditCard()->create(['invoice_id' => $invoice->id]);
                }
            }
        }

        // Contracts
        if ($this->tableExists('contracts')) {
            $this->command->info('  Seeding Contracts...');

            foreach (['draft', 'active', 'expiringSoon', 'expired'] as $status) {
                $contract = Contract::factory()->{$status}()->create([
                    'owner_id' => !empty($users) ? $users[array_rand($users)]->id : $userId,
                ]);

                // Add line items
                if ($this->tableExists('contract_line_items')) {
                    ContractLineItem::factory()->license()->create(['contract_id' => $contract->id]);
                    ContractLineItem::factory()->userSeats(25)->create(['contract_id' => $contract->id]);
                    ContractLineItem::factory()->support()->create(['contract_id' => $contract->id]);
                }

                // Add renewal reminders for active contracts
                if (($status === 'active' || $status === 'expiringSoon') && $this->tableExists('renewal_reminders')) {
                    RenewalReminder::factory()->thirtyDays()->create(['contract_id' => $contract->id]);
                    RenewalReminder::factory()->sevenDays()->create(['contract_id' => $contract->id]);
                }
            }
        }
    }

    /**
     * Seed renewals.
     */
    private function seedRenewals(array $users): void
    {
        if (!$this->tableExists('renewals')) {
            $this->command->warn('  Skipping Renewals - table does not exist');
            return;
        }

        $this->command->info('  Seeding Renewals...');

        $userId = $users[0]->id ?? null;
        $contracts = $this->tableExists('contracts') ? Contract::where('status', 'active')->take(5)->get() : collect();

        foreach ($contracts as $contract) {
            // Create renewal
            $renewal = Renewal::factory()->inProgress()->create([
                'contract_id' => $contract->id,
                'owner_id' => !empty($users) ? $users[array_rand($users)]->id : $userId,
                'original_value' => $contract->value,
            ]);

            // Add activities
            if ($this->tableExists('renewal_activities')) {
                RenewalActivity::factory()->call()->create(['renewal_id' => $renewal->id]);
                RenewalActivity::factory()->email()->create(['renewal_id' => $renewal->id]);
                RenewalActivity::factory()->statusChange('pending', 'in_progress')->create(['renewal_id' => $renewal->id]);
            }
        }

        // Create some won and lost renewals
        Renewal::factory()->won()->count(3)->create(['owner_id' => $userId]);
        Renewal::factory()->lost()->count(2)->create(['owner_id' => $userId]);

        // Create renewal forecasts
        if ($this->tableExists('renewal_forecasts') && RenewalForecast::count() === 0) {
            RenewalForecast::factory()->monthly()->highRetention()->create();
            RenewalForecast::factory()->monthly()->previous()->create();
            RenewalForecast::factory()->quarterly()->create();
        }
    }

    /**
     * Seed quotas and goals.
     */
    private function seedQuotasGoals(array $users): void
    {
        $userId = $users[0]->id ?? null;

        // Quotas
        if ($this->tableExists('quotas') && $this->tableExists('quota_periods')) {
            $this->command->info('  Seeding Quotas...');

            // Create quota periods
            $currentMonth = QuotaPeriod::factory()->monthly()->current()->create();
            $currentQuarter = QuotaPeriod::factory()->quarterly()->create();
            QuotaPeriod::factory()->yearly()->create();

            // Create quotas for each user
            foreach ($users as $user) {
                // Revenue quota
                $revenueQuota = Quota::factory()->revenue()->onTrack()->create([
                    'period_id' => $currentQuarter->id,
                    'user_id' => $user->id,
                    'created_by' => $userId,
                ]);

                // Add snapshots
                if ($this->tableExists('quota_snapshots')) {
                    for ($i = 30; $i >= 0; $i -= 7) {
                        QuotaSnapshot::factory()->create([
                            'quota_id' => $revenueQuota->id,
                            'snapshot_date' => now()->subDays($i),
                        ]);
                    }
                }

                // Deals quota
                Quota::factory()->deals()->create([
                    'period_id' => $currentMonth->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        // Goals
        if ($this->tableExists('goals')) {
            $this->command->info('  Seeding Goals...');

            foreach ($users as $user) {
                $goal = Goal::factory()->revenue()->quarterly()->inProgress()->create([
                    'user_id' => $user->id,
                    'created_by' => $userId,
                ]);

                // Add milestones
                if ($this->tableExists('goal_milestones')) {
                    $targetValue = $goal->target_value;
                    GoalMilestone::factory()->firstQuarter()->create([
                        'goal_id' => $goal->id,
                        'target_value' => $targetValue * 0.25,
                    ]);
                    GoalMilestone::factory()->halfway()->create([
                        'goal_id' => $goal->id,
                        'target_value' => $targetValue * 0.5,
                    ]);
                    GoalMilestone::factory()->thirdQuarter()->create([
                        'goal_id' => $goal->id,
                        'target_value' => $targetValue * 0.75,
                    ]);
                }

                // Add progress logs
                if ($this->tableExists('goal_progress_logs')) {
                    GoalProgressLog::factory()->fromDeal()->count(5)->create(['goal_id' => $goal->id]);
                }
            }

            // Create some achieved goals
            Goal::factory()->achieved()->count(3)->create();
        }
    }

    /**
     * Seed documents and signatures.
     */
    private function seedDocumentsSignatures(array $users): void
    {
        $userId = $users[0]->id ?? null;

        // Document templates
        if ($this->tableExists('document_templates')) {
            $this->command->info('  Seeding Documents...');

            $templates = [
                DocumentTemplate::factory()->contract()->create(['created_by' => $userId]),
                DocumentTemplate::factory()->proposal()->create(['created_by' => $userId]),
                DocumentTemplate::factory()->quote()->create(['created_by' => $userId]),
            ];

            // Create standard template variables (global, not per-template)
            if ($this->tableExists('document_template_variables') && DocumentTemplateVariable::count() === 0) {
                DocumentTemplateVariable::factory()->contact()->create(['api_name' => 'contact_name']);
                DocumentTemplateVariable::factory()->contact()->create(['name' => 'Contact Email', 'api_name' => 'contact_email', 'field_path' => 'contact.email']);
                DocumentTemplateVariable::factory()->company()->create(['api_name' => 'company_name']);
                DocumentTemplateVariable::factory()->deal()->create(['api_name' => 'deal_amount']);
                DocumentTemplateVariable::factory()->system()->create(['name' => 'Current Date', 'api_name' => 'current_date', 'field_path' => 'system.date']);
            }

            // Create generated documents
            if ($this->tableExists('generated_documents')) {
                foreach ($templates as $template) {
                    $doc = GeneratedDocument::factory()->generated()->create([
                        'template_id' => $template->id,
                        'created_by' => $userId,
                    ]);

                    // Add send logs
                    if ($this->tableExists('document_send_logs')) {
                        DocumentSendLog::factory()->delivered()->create(['document_id' => $doc->id]);
                    }
                }
            }
        }

        // Signatures
        if ($this->tableExists('signature_requests')) {
            $this->command->info('  Seeding Signatures...');

            $sigTemplates = [];
            if ($this->tableExists('signature_templates')) {
                $sigTemplates = [
                    SignatureTemplate::factory()->create(['created_by' => $userId]),
                    SignatureTemplate::factory()->multiSigner()->create(['created_by' => $userId]),
                ];
            }

            // Create signature requests with various statuses
            foreach (['draft', 'pending', 'completed', 'declined'] as $status) {
                $requestData = ['created_by' => $userId];

                $request = SignatureRequest::factory()->{$status}()->create($requestData);

                // Add signers
                if ($this->tableExists('signature_signers')) {
                    $signer = SignatureSigner::factory()->{$status === 'completed' ? 'signed' : 'pending'}()->create([
                        'request_id' => $request->id,
                    ]);

                    // Add fields
                    if ($this->tableExists('signature_fields')) {
                        SignatureField::factory()->signature()->create([
                            'request_id' => $request->id,
                            'signer_id' => $signer->id,
                        ]);
                        SignatureField::factory()->date()->create([
                            'request_id' => $request->id,
                            'signer_id' => $signer->id,
                        ]);
                    }
                }

                // Add audit logs
                if ($this->tableExists('signature_audit_logs')) {
                    SignatureAuditLog::factory()->created()->create(['request_id' => $request->id]);
                    if ($status !== 'draft') {
                        SignatureAuditLog::factory()->sent()->create(['request_id' => $request->id]);
                    }
                    if ($status === 'completed') {
                        SignatureAuditLog::factory()->signed()->create(['request_id' => $request->id]);
                        SignatureAuditLog::factory()->completed()->create(['request_id' => $request->id]);
                    }
                }
            }
        }
    }

    /**
     * Seed communications (SMS, Chat).
     */
    private function seedCommunications(array $users): void
    {
        $userId = $users[0]->id ?? null;

        // SMS
        if ($this->tableExists('sms_connections')) {
            $this->command->info('  Seeding SMS...');

            $smsConnection = SmsConnection::factory()->twilio()->active()->create();

            if ($this->tableExists('sms_templates')) {
                SmsTemplate::factory()->reminder()->count(3)->create();
                SmsTemplate::factory()->transactional()->count(2)->create();
            }

            if ($this->tableExists('sms_messages')) {
                SmsMessage::factory()->outbound()->sent()->count(20)->create([
                    'connection_id' => $smsConnection->id,
                ]);
                SmsMessage::factory()->inbound()->count(10)->create([
                    'connection_id' => $smsConnection->id,
                ]);
            }
        }

        // Chat
        if ($this->tableExists('chat_widgets')) {
            $this->command->info('  Seeding Chat...');

            $widget = ChatWidget::factory()->active()->create();

            if ($this->tableExists('chat_visitors') && $this->tableExists('chat_conversations')) {
                for ($i = 0; $i < 5; $i++) {
                    $visitor = ChatVisitor::factory()->identified()->create([
                        'widget_id' => $widget->id,
                    ]);

                    $conversation = ChatConversation::factory()->open()->create([
                        'widget_id' => $widget->id,
                        'visitor_id' => $visitor->id,
                        'assigned_to' => !empty($users) ? $users[array_rand($users)]->id : $userId,
                    ]);

                    // Add messages
                    if ($this->tableExists('chat_messages')) {
                        ChatMessage::factory()->fromVisitor()->count(3)->create([
                            'conversation_id' => $conversation->id,
                        ]);
                        ChatMessage::factory()->fromAgent()->count(2)->create([
                            'conversation_id' => $conversation->id,
                            'sender_id' => $userId,
                        ]);
                    }
                }

                // Create some closed conversations with ratings
                ChatConversation::factory()->closed()->rated()->count(5)->create([
                    'widget_id' => $widget->id,
                ]);
            }
        }
    }

    /**
     * Seed meetings and calls.
     */
    private function seedMeetingsCalls(array $users): void
    {
        $userId = $users[0]->id ?? null;

        // Calls
        if ($this->tableExists('calls') && $this->tableExists('call_providers')) {
            $this->command->info('  Seeding Calls...');

            $callProvider = CallProvider::factory()->twilio()->active()->create();

            Call::factory()->completed()->withRecording()->count(10)->create([
                'provider_id' => $callProvider->id,
                'user_id' => $userId,
            ]);
            Call::factory()->missed()->count(5)->create([
                'provider_id' => $callProvider->id,
                'user_id' => $userId,
            ]);
            Call::factory()->inbound()->count(8)->create([
                'provider_id' => $callProvider->id,
                'user_id' => $userId,
            ]);
        }

        // Scheduling pages (seed first since meeting types depend on them)
        if ($this->tableExists('scheduling_pages')) {
            $this->command->info('  Seeding Scheduling Pages...');
            foreach ($users as $user) {
                SchedulingPage::factory()->active()->create(['user_id' => $user->id]);
            }
        }

        // Video providers and meetings
        if ($this->tableExists('video_providers')) {
            $this->command->info('  Seeding Video Meetings...');

            $videoProvider = VideoProvider::factory()->zoom()->active()->create();

            if ($this->tableExists('meeting_types') && $this->tableExists('scheduling_pages')) {
                $schedulingPage = SchedulingPage::first();
                if ($schedulingPage) {
                    MeetingType::factory()->short()->zoom()->create(['scheduling_page_id' => $schedulingPage->id]);
                    MeetingType::factory()->standard()->zoom()->create(['scheduling_page_id' => $schedulingPage->id]);
                    MeetingType::factory()->long()->create(['scheduling_page_id' => $schedulingPage->id]);
                }
            }

            if ($this->tableExists('video_meetings')) {
                VideoMeeting::factory()->scheduled()->upcoming()->count(5)->create([
                    'provider_id' => $videoProvider->id,
                    'host_id' => $userId,
                ]);
                VideoMeeting::factory()->ended()->withRecording()->count(10)->create([
                    'provider_id' => $videoProvider->id,
                    'host_id' => $userId,
                ]);
            }
        }

        // Scheduled meetings
        if ($this->tableExists('scheduled_meetings')) {
            ScheduledMeeting::factory()->scheduled()->count(10)->create();
            ScheduledMeeting::factory()->completed()->count(15)->create();
            ScheduledMeeting::factory()->cancelled()->count(3)->create();
            ScheduledMeeting::factory()->noShow()->count(2)->create();
        }

        // Meeting analytics cache
        if ($this->tableExists('meeting_analytics_caches')) {
            MeetingAnalyticsCache::factory()->weekly()->count(4)->create();
            MeetingAnalyticsCache::factory()->monthly()->count(3)->create();
        }
    }

    /**
     * Seed analytics and alerts.
     */
    private function seedAnalyticsAlerts(array $users): void
    {
        $userId = $users[0]->id ?? null;

        if (!$this->tableExists('analytics_alerts')) {
            $this->command->warn('  Skipping Analytics & Alerts - table does not exist');
            return;
        }

        $this->command->info('  Seeding Analytics & Alerts...');

        $alerts = [
            AnalyticsAlert::factory()->threshold()->daily()->create(['user_id' => $userId]),
            AnalyticsAlert::factory()->anomaly()->hourly()->create(['user_id' => $userId]),
            AnalyticsAlert::factory()->trend()->create(['user_id' => $userId]),
        ];

        foreach ($alerts as $alert) {
            // Add subscriptions
            if ($this->tableExists('analytics_alert_subscriptions')) {
                foreach ($users as $user) {
                    AnalyticsAlertSubscription::factory()->create([
                        'alert_id' => $alert->id,
                        'user_id' => $user->id,
                    ]);
                }
            }

            // Add history
            if ($this->tableExists('analytics_alert_histories')) {
                AnalyticsAlertHistory::factory()->triggered()->count(3)->create(['alert_id' => $alert->id]);
                AnalyticsAlertHistory::factory()->resolved()->count(2)->create(['alert_id' => $alert->id]);
            }
        }

        // Create forecast data
        if ($this->tableExists('forecast_snapshots')) {
            ForecastSnapshot::factory()->monthly()->count(3)->create();
            ForecastSnapshot::factory()->quarterly()->create();
        }

        if ($this->tableExists('forecast_scenarios')) {
            ForecastScenario::factory()->current()->create();
            ForecastScenario::factory()->bestCase()->create();
            ForecastScenario::factory()->worstCase()->create();
        }

        if ($this->tableExists('forecast_adjustments')) {
            ForecastAdjustment::factory()->count(10)->create();
        }
    }

    /**
     * Seed customer health scores.
     */
    private function seedCustomerHealth(): void
    {
        if (!$this->tableExists('customer_health_scores')) {
            $this->command->warn('  Skipping Customer Health - table does not exist');
            return;
        }

        $this->command->info('  Seeding Customer Health...');

        $healthyScores = CustomerHealthScore::factory()->healthy()->count(10)->create();
        $atRiskScores = CustomerHealthScore::factory()->atRisk()->count(5)->create();
        $criticalScores = CustomerHealthScore::factory()->critical()->count(3)->create();

        // Add history for each
        if ($this->tableExists('health_score_histories')) {
            foreach (array_merge($healthyScores->all(), $atRiskScores->all(), $criticalScores->all()) as $score) {
                // Add historical snapshots
                for ($i = 90; $i >= 0; $i -= 7) {
                    HealthScoreHistory::factory()->create([
                        'customer_health_score_id' => $score->id,
                        'recorded_at' => now()->subDays($i),
                        'overall_score' => max(0, min(100, $score->overall_score + rand(-10, 10))),
                    ]);
                }
            }
        }
    }

    /**
     * Seed portal users.
     */
    private function seedPortalUsers(): void
    {
        if (!$this->tableExists('portal_users')) {
            $this->command->warn('  Skipping Portal Users - table does not exist');
            return;
        }

        $this->command->info('  Seeding Portal Users...');

        $portalUsers = [
            ...PortalUser::factory()->active()->count(10)->create()->all(),
            ...PortalUser::factory()->pending()->count(3)->create()->all(),
            ...PortalUser::factory()->suspended()->count(2)->create()->all(),
        ];

        // Create invitations
        if ($this->tableExists('portal_invitations')) {
            PortalInvitation::factory()->pending()->count(5)->create();
            PortalInvitation::factory()->accepted()->count(10)->create();
            PortalInvitation::factory()->expired()->count(3)->create();
        }

        // Add activity logs
        if ($this->tableExists('portal_activity_logs')) {
            foreach (array_slice($portalUsers, 0, 10) as $user) {
                PortalActivityLog::factory()->login()->create(['portal_user_id' => $user->id]);
                PortalActivityLog::factory()->viewDeal()->count(3)->create(['portal_user_id' => $user->id]);
                PortalActivityLog::factory()->viewInvoice()->count(2)->create(['portal_user_id' => $user->id]);
                PortalActivityLog::factory()->downloadDocument()->create(['portal_user_id' => $user->id]);
            }
        }
    }

    /**
     * Seed core CRM records (Contacts, Organizations, Deals, Leads).
     */
    private function seedCoreRecords(array $users): void
    {
        if (!$this->tableExists('module_records')) {
            $this->command->warn('  Skipping Core Records - table does not exist');
            return;
        }

        $this->command->info('  Seeding Core CRM Records...');

        $userId = $users[0]->id ?? 1;

        // Get modules
        $contactsModule = Module::where('api_name', 'contacts')->first();
        $orgsModule = Module::where('api_name', 'organizations')->first();
        $dealsModule = Module::where('api_name', 'deals')->first();
        $leadsModule = Module::where('api_name', 'leads')->first();

        // Seed Organizations
        if ($orgsModule) {
            $companies = [
                ['name' => 'Acme Corporation', 'industry' => 'Technology', 'website' => 'https://acme.example.com', 'phone' => '+1-555-0100', 'employees' => 500],
                ['name' => 'TechStart Inc', 'industry' => 'Software', 'website' => 'https://techstart.example.com', 'phone' => '+1-555-0101', 'employees' => 50],
                ['name' => 'Global Industries', 'industry' => 'Manufacturing', 'website' => 'https://global-ind.example.com', 'phone' => '+1-555-0102', 'employees' => 2500],
                ['name' => 'CloudSync Solutions', 'industry' => 'Cloud Services', 'website' => 'https://cloudsync.example.com', 'phone' => '+1-555-0103', 'employees' => 120],
                ['name' => 'DataFlow Analytics', 'industry' => 'Data Analytics', 'website' => 'https://dataflow.example.com', 'phone' => '+1-555-0104', 'employees' => 75],
                ['name' => 'SecureNet Systems', 'industry' => 'Cybersecurity', 'website' => 'https://securenet.example.com', 'phone' => '+1-555-0105', 'employees' => 200],
                ['name' => 'GreenTech Innovations', 'industry' => 'Clean Energy', 'website' => 'https://greentech.example.com', 'phone' => '+1-555-0106', 'employees' => 85],
                ['name' => 'MediCore Health', 'industry' => 'Healthcare', 'website' => 'https://medicore.example.com', 'phone' => '+1-555-0107', 'employees' => 350],
                ['name' => 'FinanceHub Ltd', 'industry' => 'Financial Services', 'website' => 'https://financehub.example.com', 'phone' => '+1-555-0108', 'employees' => 180],
                ['name' => 'RetailMax', 'industry' => 'Retail', 'website' => 'https://retailmax.example.com', 'phone' => '+1-555-0109', 'employees' => 1200],
            ];

            foreach ($companies as $company) {
                ModuleRecord::create([
                    'module_id' => $orgsModule->id,
                    'data' => $company,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        }

        // Seed Contacts
        if ($contactsModule) {
            $contacts = [
                ['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@acme.example.com', 'phone' => '+1-555-1001', 'title' => 'CEO', 'company' => 'Acme Corporation'],
                ['first_name' => 'Sarah', 'last_name' => 'Johnson', 'email' => 'sarah.j@techstart.example.com', 'phone' => '+1-555-1002', 'title' => 'CTO', 'company' => 'TechStart Inc'],
                ['first_name' => 'Michael', 'last_name' => 'Williams', 'email' => 'm.williams@global-ind.example.com', 'phone' => '+1-555-1003', 'title' => 'VP of Sales', 'company' => 'Global Industries'],
                ['first_name' => 'Emily', 'last_name' => 'Brown', 'email' => 'emily.b@cloudsync.example.com', 'phone' => '+1-555-1004', 'title' => 'Product Manager', 'company' => 'CloudSync Solutions'],
                ['first_name' => 'David', 'last_name' => 'Davis', 'email' => 'david.d@dataflow.example.com', 'phone' => '+1-555-1005', 'title' => 'Head of Engineering', 'company' => 'DataFlow Analytics'],
                ['first_name' => 'Jennifer', 'last_name' => 'Miller', 'email' => 'jen.miller@securenet.example.com', 'phone' => '+1-555-1006', 'title' => 'Security Director', 'company' => 'SecureNet Systems'],
                ['first_name' => 'Robert', 'last_name' => 'Wilson', 'email' => 'r.wilson@greentech.example.com', 'phone' => '+1-555-1007', 'title' => 'Operations Manager', 'company' => 'GreenTech Innovations'],
                ['first_name' => 'Lisa', 'last_name' => 'Anderson', 'email' => 'lisa.a@medicore.example.com', 'phone' => '+1-555-1008', 'title' => 'CFO', 'company' => 'MediCore Health'],
                ['first_name' => 'James', 'last_name' => 'Taylor', 'email' => 'james.t@financehub.example.com', 'phone' => '+1-555-1009', 'title' => 'Account Manager', 'company' => 'FinanceHub Ltd'],
                ['first_name' => 'Amanda', 'last_name' => 'Thomas', 'email' => 'a.thomas@retailmax.example.com', 'phone' => '+1-555-1010', 'title' => 'Procurement Lead', 'company' => 'RetailMax'],
                ['first_name' => 'Chris', 'last_name' => 'Martinez', 'email' => 'chris.m@acme.example.com', 'phone' => '+1-555-1011', 'title' => 'Sales Director', 'company' => 'Acme Corporation'],
                ['first_name' => 'Jessica', 'last_name' => 'Garcia', 'email' => 'j.garcia@techstart.example.com', 'phone' => '+1-555-1012', 'title' => 'Marketing Manager', 'company' => 'TechStart Inc'],
                ['first_name' => 'Daniel', 'last_name' => 'Rodriguez', 'email' => 'd.rodriguez@global-ind.example.com', 'phone' => '+1-555-1013', 'title' => 'IT Director', 'company' => 'Global Industries'],
                ['first_name' => 'Michelle', 'last_name' => 'Lee', 'email' => 'm.lee@cloudsync.example.com', 'phone' => '+1-555-1014', 'title' => 'Customer Success', 'company' => 'CloudSync Solutions'],
                ['first_name' => 'Kevin', 'last_name' => 'White', 'email' => 'k.white@dataflow.example.com', 'phone' => '+1-555-1015', 'title' => 'Data Scientist', 'company' => 'DataFlow Analytics'],
            ];

            foreach ($contacts as $contact) {
                ModuleRecord::create([
                    'module_id' => $contactsModule->id,
                    'data' => $contact,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        }

        // Seed Deals
        if ($dealsModule) {
            $stages = ['Qualification', 'Needs Analysis', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'];
            $dealNames = [
                'Enterprise License Agreement', 'Cloud Migration Project', 'Security Audit Package',
                'Analytics Platform Subscription', 'Support Contract Renewal', 'Custom Development',
                'Training Program', 'Consulting Engagement', 'Hardware Upgrade', 'SaaS Implementation',
            ];

            foreach ($dealNames as $index => $dealName) {
                $stage = $stages[array_rand($stages)];
                $amount = rand(10000, 500000);
                ModuleRecord::create([
                    'module_id' => $dealsModule->id,
                    'data' => [
                        'name' => $dealName,
                        'amount' => $amount,
                        'stage' => $stage,
                        'probability' => $stage === 'Closed Won' ? 100 : ($stage === 'Closed Lost' ? 0 : rand(20, 80)),
                        'expected_close_date' => now()->addDays(rand(7, 90))->format('Y-m-d'),
                        'company' => ['Acme Corporation', 'TechStart Inc', 'Global Industries', 'CloudSync Solutions'][rand(0, 3)],
                    ],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        }

        // Seed Leads
        if ($leadsModule) {
            $leadSources = ['Website', 'Referral', 'Trade Show', 'Cold Call', 'Social Media', 'Advertisement'];
            $leadStatuses = ['New', 'Contacted', 'Qualified', 'Unqualified'];

            $leadData = [
                ['first_name' => 'Tom', 'last_name' => 'Baker', 'email' => 'tom.baker@prospect1.com', 'company' => 'Prospect One LLC'],
                ['first_name' => 'Nancy', 'last_name' => 'Drew', 'email' => 'nancy.d@newclient.com', 'company' => 'New Client Corp'],
                ['first_name' => 'Frank', 'last_name' => 'Castle', 'email' => 'f.castle@startup.io', 'company' => 'Hot Startup'],
                ['first_name' => 'Diana', 'last_name' => 'Prince', 'email' => 'd.prince@enterprise.com', 'company' => 'Big Enterprise'],
                ['first_name' => 'Bruce', 'last_name' => 'Wayne', 'email' => 'b.wayne@wealthy.com', 'company' => 'Wayne Industries'],
                ['first_name' => 'Clark', 'last_name' => 'Kent', 'email' => 'c.kent@media.com', 'company' => 'Daily Media'],
                ['first_name' => 'Peter', 'last_name' => 'Parker', 'email' => 'p.parker@photo.com', 'company' => 'Photo Pros'],
                ['first_name' => 'Tony', 'last_name' => 'Stark', 'email' => 't.stark@innovation.com', 'company' => 'Stark Innovations'],
            ];

            foreach ($leadData as $lead) {
                ModuleRecord::create([
                    'module_id' => $leadsModule->id,
                    'data' => array_merge($lead, [
                        'phone' => '+1-555-' . rand(2000, 2999),
                        'source' => $leadSources[array_rand($leadSources)],
                        'status' => $leadStatuses[array_rand($leadStatuses)],
                        'rating' => ['Hot', 'Warm', 'Cold'][rand(0, 2)],
                    ]),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        }
    }
}
