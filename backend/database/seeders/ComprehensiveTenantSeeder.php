<?php

declare(strict_types=1);

namespace Database\Seeders;

use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Comprehensive tenant seeder that seeds ALL modules with demo data.
 * Uses raw DB inserts for compatibility with DDD architecture.
 *
 * Run with: php artisan tenants:run "db:seed --class=ComprehensiveTenantSeeder" --tenants=techco
 */
class ComprehensiveTenantSeeder extends Seeder
{
    private array $userIds = [];
    private int $userId = 1;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Comprehensive Tenant Seeding...');
        $this->command->info('This seeds ALL modules including Cadences, Playbooks, Deal Rooms, etc.');
        $this->command->newLine();

        // Get existing user IDs
        $this->userIds = DB::table('users')->take(5)->pluck('id')->toArray();
        $this->userId = $this->userIds[0] ?? 1;

        if (empty($this->userIds)) {
            $this->command->warn('No users found! Creating a default user.');
            $this->userId = DB::table('users')->insertGetId([
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->userIds = [$this->userId];
        }

        DB::beginTransaction();

        try {
            // Seed all missing modules
            $this->seedCadenceSystem();
            $this->seedPlaybookSystem();
            $this->seedDealRoomSystem();
            $this->seedCompetitorIntelligence();
            $this->seedProposalSystem();
            $this->seedQuoteSystem();
            $this->seedWorkflowSystem();
            $this->seedBlueprintSystem();
            $this->seedEmailSystem();
            $this->seedApprovalSystem();
            $this->seedApiAndWebhooks();
            $this->seedDuplicateManagement();
            $this->seedDashboardsAndReports();
            $this->seedSavedSearches();
            $this->seedAuditLogs();

            DB::commit();
            $this->command->newLine();
            $this->command->info('Comprehensive Tenant Seeding Complete!');
        } catch (Exception $e) {
            DB::rollBack();
            $this->command->error('Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a table exists.
     */
    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * Get random user ID.
     */
    private function randomUserId(): int
    {
        return $this->userIds[array_rand($this->userIds)] ?? $this->userId;
    }

    /**
     * Seed the Cadence System.
     */
    private function seedCadenceSystem(): void
    {
        if (!$this->tableExists('cadences')) {
            $this->command->warn('  Skipping Cadences - table does not exist');
            return;
        }

        $this->command->info('  Seeding Cadence System...');

        // Get module for cadences
        $leadsModule = DB::table('modules')->where('api_name', 'leads')->first();
        $moduleId = $leadsModule?->id ?? 1;

        // Create cadences
        $cadenceData = [
            ['name' => 'New Lead Welcome Sequence', 'status' => 'active', 'auto_enroll' => true],
            ['name' => 'Post-Demo Follow-up', 'status' => 'active', 'auto_enroll' => false],
            ['name' => 'Re-engagement Campaign', 'status' => 'paused', 'auto_enroll' => false],
            ['name' => 'Enterprise Outreach (Draft)', 'status' => 'draft', 'auto_enroll' => false],
        ];

        $cadenceIds = [];
        foreach ($cadenceData as $index => $data) {
            $cadenceIds[] = DB::table('cadences')->insertGetId([
                'name' => $data['name'],
                'description' => 'Automated outreach sequence for ' . strtolower($data['name']),
                'module_id' => $moduleId,
                'status' => $data['status'],
                'entry_criteria' => json_encode([]),
                'exit_criteria' => json_encode([]),
                'settings' => json_encode(['timezone' => 'UTC']),
                'auto_enroll' => $data['auto_enroll'],
                'allow_re_enrollment' => false,
                're_enrollment_days' => null,
                'max_enrollments_per_day' => 50,
                'created_by' => $this->userId,
                'owner_id' => $this->userIds[$index % count($this->userIds)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('    - Created ' . count($cadenceIds) . ' cadences');

        // Create cadence steps
        if ($this->tableExists('cadence_steps')) {
            $stepTypes = ['email', 'call', 'task', 'wait', 'linkedin'];
            foreach ($cadenceIds as $cadenceId) {
                foreach ($stepTypes as $order => $type) {
                    DB::table('cadence_steps')->insert([
                        'cadence_id' => $cadenceId,
                        'step_order' => $order + 1,
                        'name' => "Step " . ($order + 1) . " - " . ucfirst($type),
                        'channel' => $type,
                        'delay_type' => 'days',
                        'delay_value' => $order * 2,
                        'subject' => "Follow-up step " . ($order + 1),
                        'content' => "Content for step " . ($order + 1),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Created cadence steps');
        }

        // Create enrollments
        if ($this->tableExists('cadence_enrollments')) {
            $statuses = ['active', 'completed', 'paused', 'replied'];
            $contactRecords = DB::table('module_records')
                ->join('modules', 'module_records.module_id', '=', 'modules.id')
                ->whereIn('modules.api_name', ['contacts', 'leads'])
                ->select('module_records.id')
                ->take(40)
                ->pluck('id')
                ->toArray();

            $enrollmentCount = 0;
            foreach ($cadenceIds as $cadenceId) {
                foreach (array_slice($contactRecords, 0, 10) as $recordId) {
                    DB::table('cadence_enrollments')->insert([
                        'cadence_id' => $cadenceId,
                        'record_id' => $recordId,
                        'current_step_id' => null,
                        'status' => $statuses[array_rand($statuses)],
                        'enrolled_at' => now()->subDays(rand(1, 30)),
                        'next_step_at' => now()->addDays(rand(1, 7)),
                        'enrolled_by' => $this->randomUserId(),
                        'metadata' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $enrollmentCount++;
                }
            }
            $this->command->info("    - Created {$enrollmentCount} cadence enrollments");
        }
    }

    /**
     * Seed the Playbook System.
     */
    private function seedPlaybookSystem(): void
    {
        if (!$this->tableExists('playbooks')) {
            $this->command->warn('  Skipping Playbooks - table does not exist');
            return;
        }

        $this->command->info('  Seeding Playbook System...');

        // Create playbooks
        $playbookData = [
            ['name' => 'Enterprise Deal Playbook', 'slug' => 'enterprise-deal-playbook', 'module' => 'deals', 'active' => true],
            ['name' => 'Lead Qualification Playbook', 'slug' => 'lead-qualification-playbook', 'module' => 'leads', 'active' => true],
            ['name' => 'Customer Onboarding', 'slug' => 'customer-onboarding', 'module' => 'contacts', 'active' => true],
            ['name' => 'Renewal Process (Inactive)', 'slug' => 'renewal-process', 'module' => 'deals', 'active' => false],
        ];

        $playbookIds = [];
        foreach ($playbookData as $data) {
            $playbookIds[] = DB::table('playbooks')->insertGetId([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => 'Comprehensive playbook for ' . $data['module'],
                'trigger_module' => $data['module'],
                'trigger_condition' => 'created',
                'trigger_config' => json_encode([]),
                'estimated_days' => rand(30, 90),
                'is_active' => $data['active'],
                'auto_assign' => false,
                'created_by' => $this->userId,
                'default_owner_id' => $this->randomUserId(),
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('    - Created ' . count($playbookIds) . ' playbooks');

        // Create phases
        if ($this->tableExists('playbook_phases')) {
            $phaseNames = ['Discovery', 'Qualification', 'Proposal', 'Negotiation', 'Closing'];
            foreach ($playbookIds as $playbookId) {
                foreach ($phaseNames as $order => $phaseName) {
                    $phaseId = DB::table('playbook_phases')->insertGetId([
                        'playbook_id' => $playbookId,
                        'name' => $phaseName,
                        'description' => "Phase {$order}: {$phaseName}",
                        'target_days' => ($order + 1) * 7,
                        'display_order' => $order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Create tasks for each phase
                    if ($this->tableExists('playbook_tasks')) {
                        $taskTitles = [
                            'Complete discovery call',
                            'Send follow-up email',
                            'Schedule demo',
                            'Review requirements',
                            'Update CRM',
                        ];
                        foreach (array_slice($taskTitles, 0, 3) as $taskOrder => $taskTitle) {
                            DB::table('playbook_tasks')->insert([
                                'playbook_id' => $playbookId,
                                'phase_id' => $phaseId,
                                'title' => $taskTitle . ' - ' . $phaseName,
                                'description' => 'Task for ' . $phaseName . ' phase',
                                'task_type' => 'manual',
                                'is_required' => $taskOrder === 0,
                                'is_milestone' => $taskOrder === 2,
                                'due_days' => ($order + 1) * 7 + $taskOrder,
                                'duration_estimate' => rand(15, 120),
                                'display_order' => $taskOrder,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
            $this->command->info('    - Created playbook phases and tasks');
        }

        // Create playbook instances
        if ($this->tableExists('playbook_instances')) {
            $dealRecords = DB::table('module_records')
                ->join('modules', 'module_records.module_id', '=', 'modules.id')
                ->where('modules.api_name', 'deals')
                ->select('module_records.id')
                ->take(20)
                ->pluck('id')
                ->toArray();

            foreach ($playbookIds as $playbookId) {
                foreach (array_slice($dealRecords, 0, 5) as $recordId) {
                    $status = ['active', 'completed', 'paused'][array_rand(['active', 'completed', 'paused'])];
                    DB::table('playbook_instances')->insert([
                        'playbook_id' => $playbookId,
                        'related_module' => 'deals',
                        'related_id' => $recordId,
                        'status' => $status,
                        'started_at' => now()->subDays(rand(1, 60)),
                        'target_completion_at' => now()->addDays(rand(30, 90)),
                        'completed_at' => $status === 'completed' ? now()->subDays(rand(1, 10)) : null,
                        'owner_id' => $this->randomUserId(),
                        'progress_percent' => $status === 'completed' ? 100 : rand(0, 80),
                        'metadata' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Created playbook instances');
        }
    }

    /**
     * Seed the Deal Room System.
     */
    private function seedDealRoomSystem(): void
    {
        if (!$this->tableExists('deal_rooms')) {
            $this->command->warn('  Skipping Deal Rooms - table does not exist');
            return;
        }

        $this->command->info('  Seeding Deal Room System...');

        // Get deal records
        $dealRecords = DB::table('module_records')
            ->join('modules', 'module_records.module_id', '=', 'modules.id')
            ->where('modules.api_name', 'deals')
            ->select('module_records.id')
            ->take(10)
            ->pluck('id')
            ->toArray();

        if (empty($dealRecords)) {
            $this->command->warn('    - No deal records found, skipping Deal Rooms');
            return;
        }

        // Create deal rooms - only as many as we have deal records
        $roomNames = [
            'Acme Corp Enterprise Agreement',
            'TechStart Partnership',
            'Global Industries Q4 Contract',
            'CloudSync Expansion Deal',
            'DataFlow Analytics Implementation',
            'SecureNet Systems (Won)',
            'FinanceHub Ltd (Lost)',
        ];

        $roomIds = [];
        foreach ($roomNames as $index => $name) {
            // Skip if we don't have enough deal records
            if (!isset($dealRecords[$index])) {
                break;
            }

            $status = 'active';
            if (str_contains($name, 'Won')) $status = 'won';
            if (str_contains($name, 'Lost')) $status = 'lost';

            $roomIds[] = DB::table('deal_rooms')->insertGetId([
                'name' => $name,
                'slug' => Str::slug($name) . '-' . ($index + 1),
                'deal_record_id' => $dealRecords[$index],
                'status' => $status,
                'description' => 'Collaborative deal room for ' . $name,
                'settings' => json_encode(['notifications' => true]),
                'created_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('    - Created ' . count($roomIds) . ' deal rooms');

        // Add members
        if ($this->tableExists('deal_room_members')) {
            foreach ($roomIds as $roomId) {
                // Add internal members
                foreach ($this->userIds as $userId) {
                    DB::table('deal_room_members')->insert([
                        'room_id' => $roomId,
                        'user_id' => $userId,
                        'external_email' => null,
                        'external_name' => null,
                        'role' => ['owner', 'team', 'stakeholder', 'viewer'][array_rand(['owner', 'team', 'stakeholder', 'viewer'])],
                        'access_token' => null,
                        'token_expires_at' => null,
                        'last_accessed_at' => now()->subDays(rand(0, 7)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                // Add external members
                for ($i = 0; $i < 2; $i++) {
                    DB::table('deal_room_members')->insert([
                        'room_id' => $roomId,
                        'user_id' => null,
                        'external_email' => "external{$i}_{$roomId}@client.com",
                        'external_name' => "External Contact {$i}",
                        'role' => 'stakeholder',
                        'access_token' => Str::random(64),
                        'token_expires_at' => now()->addDays(30),
                        'last_accessed_at' => now()->subDays(rand(0, 5)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added members to deal rooms');
        }

        // Add documents
        if ($this->tableExists('deal_room_documents')) {
            $docTypes = ['proposal', 'contract', 'pricing', 'presentation', 'nda'];
            foreach ($roomIds as $roomId) {
                foreach ($docTypes as $type) {
                    DB::table('deal_room_documents')->insert([
                        'room_id' => $roomId,
                        'name' => ucfirst($type) . ' Document.pdf',
                        'file_path' => "deal_rooms/{$roomId}/{$type}.pdf",
                        'mime_type' => 'application/pdf',
                        'file_size' => rand(10000, 5000000),
                        'version' => 1,
                        'is_visible_to_external' => true,
                        'uploaded_by' => $this->randomUserId(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added documents to deal rooms');
        }

        // Add messages (requires members first)
        if ($this->tableExists('deal_room_messages')) {
            // Get first member for each room
            $roomMembers = [];
            foreach ($roomIds as $roomId) {
                $member = DB::table('deal_room_members')->where('room_id', $roomId)->first();
                if ($member) {
                    $roomMembers[$roomId] = $member->id;
                }
            }

            $messages = [
                'Looking forward to our partnership!',
                'Please review the attached proposal.',
                'Can we schedule a call to discuss next steps?',
                'Thanks for the update on pricing.',
                'The team is reviewing the contract.',
            ];
            foreach ($roomIds as $roomId) {
                if (!isset($roomMembers[$roomId])) continue;
                foreach ($messages as $message) {
                    DB::table('deal_room_messages')->insert([
                        'room_id' => $roomId,
                        'member_id' => $roomMembers[$roomId],
                        'message' => $message,
                        'is_internal' => rand(0, 1),
                        'created_at' => now()->subDays(rand(0, 14)),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added messages to deal rooms');
        }

        // Add action items
        if ($this->tableExists('deal_room_action_items')) {
            $actionItems = [
                'Review proposal document',
                'Schedule demo with technical team',
                'Provide pricing breakdown',
                'Sign NDA',
                'Complete security questionnaire',
            ];
            foreach ($roomIds as $roomId) {
                // Get a member for this room
                $member = DB::table('deal_room_members')->where('room_id', $roomId)->first();
                $memberId = $member?->id;

                foreach ($actionItems as $order => $title) {
                    DB::table('deal_room_action_items')->insert([
                        'room_id' => $roomId,
                        'title' => $title,
                        'description' => 'Action item: ' . $title,
                        'assigned_to' => $memberId,
                        'assigned_party' => ['seller', 'buyer', 'both'][array_rand(['seller', 'buyer', 'both'])],
                        'due_date' => now()->addDays(rand(1, 30)),
                        'status' => ['pending', 'in_progress', 'completed'][array_rand(['pending', 'in_progress', 'completed'])],
                        'display_order' => $order,
                        'completed_at' => null,
                        'completed_by' => null,
                        'created_by' => $this->userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added action items');
        }
    }

    /**
     * Seed the Competitor Intelligence System.
     */
    private function seedCompetitorIntelligence(): void
    {
        if (!$this->tableExists('competitors')) {
            $this->command->warn('  Skipping Competitors - table does not exist');
            return;
        }

        $this->command->info('  Seeding Competitor Intelligence...');

        // Create competitors
        $competitorData = [
            ['name' => 'Salesforce', 'position' => 'Market Leader', 'website' => 'https://salesforce.com'],
            ['name' => 'HubSpot', 'position' => 'Challenger', 'website' => 'https://hubspot.com'],
            ['name' => 'Pipedrive', 'position' => 'SMB Focus', 'website' => 'https://pipedrive.com'],
            ['name' => 'Zoho CRM', 'position' => 'Value Player', 'website' => 'https://zoho.com/crm'],
            ['name' => 'Microsoft Dynamics', 'position' => 'Enterprise', 'website' => 'https://dynamics.microsoft.com'],
            ['name' => 'Freshsales', 'position' => 'Emerging', 'website' => 'https://freshsales.io'],
        ];

        $competitorIds = [];
        foreach ($competitorData as $data) {
            $competitorIds[] = DB::table('competitors')->insertGetId([
                'name' => $data['name'],
                'website' => $data['website'],
                'market_position' => $data['position'],
                'description' => $data['name'] . ' is a ' . strtolower($data['position']) . ' in the CRM market.',
                'is_active' => true,
                'last_updated_at' => now(),
                'last_updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('    - Created ' . count($competitorIds) . ' competitors');

        // Add battlecard sections
        if ($this->tableExists('battlecard_sections')) {
            $sections = [
                'strengths' => "- Strong brand recognition\n- Large partner ecosystem\n- Comprehensive feature set",
                'weaknesses' => "- Complex pricing\n- Steep learning curve\n- Slow support response",
                'counters' => "- 50% faster implementation\n- Better mobile app\n- Simpler pricing",
                'pricing' => "Professional: $75/user/month\nEnterprise: $150/user/month",
                'resources' => "- Enterprise sales teams\n- Mid-market companies\n- B2B organizations",
            ];
            foreach ($competitorIds as $competitorId) {
                $order = 0;
                foreach ($sections as $type => $content) {
                    DB::table('battlecard_sections')->insert([
                        'competitor_id' => $competitorId,
                        'section_type' => $type,
                        'content' => $content,
                        'display_order' => $order++,
                        'created_by' => $this->userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added battlecard sections');
        }

        // Add objections
        if ($this->tableExists('competitor_objections')) {
            $objections = [
                ['objection' => 'They have more features', 'counter_script' => 'We focus on the features that matter most for your use case.'],
                ['objection' => 'They are cheaper', 'counter_script' => 'Our total cost of ownership is lower due to faster implementation.'],
                ['objection' => 'They have better brand recognition', 'counter_script' => 'We have proven results with similar companies in your industry.'],
            ];
            foreach ($competitorIds as $competitorId) {
                foreach ($objections as $obj) {
                    DB::table('competitor_objections')->insert([
                        'competitor_id' => $competitorId,
                        'objection' => $obj['objection'],
                        'counter_script' => $obj['counter_script'],
                        'effectiveness_score' => rand(30, 50) / 10,
                        'use_count' => rand(0, 50),
                        'success_count' => rand(0, 30),
                        'created_by' => $this->randomUserId(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added competitor objections');
        }

        // Add notes
        if ($this->tableExists('competitor_notes')) {
            $sources = ['Sales Team', 'Customer Feedback', 'Industry Report', 'Press Release', 'LinkedIn'];
            foreach ($competitorIds as $competitorId) {
                for ($i = 0; $i < 3; $i++) {
                    DB::table('competitor_notes')->insert([
                        'competitor_id' => $competitorId,
                        'content' => 'Competitive intel note #' . ($i + 1) . ' - Recent market activity observed.',
                        'source' => $sources[array_rand($sources)],
                        'is_verified' => rand(0, 1),
                        'verified_by' => rand(0, 1) ? $this->randomUserId() : null,
                        'created_by' => $this->randomUserId(),
                        'created_at' => now()->subDays(rand(1, 30)),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added competitor notes');
        }
    }

    /**
     * Seed the Proposal System.
     */
    private function seedProposalSystem(): void
    {
        if (!$this->tableExists('proposals')) {
            $this->command->warn('  Skipping Proposals - table does not exist');
            return;
        }

        $this->command->info('  Seeding Proposal System...');

        // Create proposal templates
        $templateIds = [];
        if ($this->tableExists('proposal_templates')) {
            $templates = [
                ['name' => 'Standard Business Proposal', 'category' => 'sales'],
                ['name' => 'Enterprise Solution Proposal', 'category' => 'services'],
                ['name' => 'Partnership Proposal', 'category' => 'partnership'],
            ];
            foreach ($templates as $template) {
                $templateIds[] = DB::table('proposal_templates')->insertGetId([
                    'name' => $template['name'],
                    'description' => 'Template for ' . strtolower($template['name']),
                    'category' => $template['category'],
                    'default_sections' => json_encode(['executive_summary', 'scope', 'timeline', 'pricing']),
                    'styling' => json_encode(['primary_color' => '#3498db']),
                    'is_active' => true,
                    'created_by' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('    - Created ' . count($templateIds) . ' proposal templates');
        }

        // Create proposals
        $proposalData = [
            ['name' => 'Acme Corp - Digital Transformation', 'status' => 'draft'],
            ['name' => 'TechStart - Enterprise Solution', 'status' => 'sent'],
            ['name' => 'Global Industries - Partnership', 'status' => 'viewed'],
            ['name' => 'CloudSync - Annual Contract', 'status' => 'accepted'],
            ['name' => 'DataFlow - Integration Project', 'status' => 'accepted'],
            ['name' => 'SecureNet - Security Upgrade', 'status' => 'rejected'],
        ];

        $proposalIds = [];
        foreach ($proposalData as $index => $data) {
            $proposalIds[] = DB::table('proposals')->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'name' => $data['name'],
                'proposal_number' => 'PROP-' . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT),
                'status' => $data['status'],
                'template_id' => $templateIds[$index % count($templateIds)] ?? null,
                'total_value' => rand(10000, 500000),
                'currency' => 'USD',
                'valid_until' => now()->addDays(30),
                'version' => 1,
                'created_by' => $this->userId,
                'assigned_to' => $this->randomUserId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('    - Created ' . count($proposalIds) . ' proposals');

        // Add sections
        if ($this->tableExists('proposal_sections')) {
            $sections = [
                ['type' => 'executive_summary', 'title' => 'Executive Summary'],
                ['type' => 'scope', 'title' => 'Scope of Work'],
                ['type' => 'timeline', 'title' => 'Project Timeline'],
                ['type' => 'pricing', 'title' => 'Pricing'],
            ];
            foreach ($proposalIds as $proposalId) {
                foreach ($sections as $order => $section) {
                    DB::table('proposal_sections')->insert([
                        'proposal_id' => $proposalId,
                        'section_type' => $section['type'],
                        'title' => $section['title'],
                        'content' => '<p>Content for ' . $section['title'] . '</p>',
                        'settings' => json_encode([]),
                        'display_order' => $order,
                        'is_visible' => true,
                        'is_locked' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added proposal sections');
        }

        // Add pricing items
        if ($this->tableExists('proposal_pricing_items')) {
            $items = [
                ['name' => 'Implementation', 'price' => 25000, 'unit' => 'one_time'],
                ['name' => 'Annual License', 'price' => 50000, 'unit' => 'yearly'],
                ['name' => 'Training', 'price' => 10000, 'unit' => 'hours'],
                ['name' => 'Support', 'price' => 15000, 'unit' => 'monthly'],
            ];
            foreach ($proposalIds as $proposalId) {
                foreach ($items as $order => $item) {
                    DB::table('proposal_pricing_items')->insert([
                        'proposal_id' => $proposalId,
                        'name' => $item['name'],
                        'description' => $item['name'] . ' services',
                        'quantity' => 1,
                        'unit' => $item['unit'],
                        'unit_price' => $item['price'],
                        'discount_percent' => 0,
                        'line_total' => $item['price'],
                        'is_optional' => false,
                        'is_selected' => true,
                        'pricing_type' => 'fixed',
                        'display_order' => $order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added pricing items');
        }
    }

    /**
     * Seed the Quote System.
     */
    private function seedQuoteSystem(): void
    {
        if (!$this->tableExists('quotes')) {
            $this->command->warn('  Skipping Quotes - table does not exist');
            return;
        }

        $this->command->info('  Seeding Quote System...');

        // Create quote templates
        $templateIds = [];
        if ($this->tableExists('quote_templates')) {
            $templates = ['Standard Quote Template', 'Enterprise Quote Template'];
            foreach ($templates as $index => $name) {
                $templateIds[] = DB::table('quote_templates')->insertGetId([
                    'name' => $name,
                    'is_default' => $index === 0,
                    'header_html' => '<h1>Quote</h1>',
                    'footer_html' => '<p>Thank you for your business</p>',
                    'styling' => json_encode(['primary_color' => '#3498db']),
                    'company_info' => json_encode(['name' => 'Demo Company']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('    - Created quote templates');
        }

        // Create quotes
        $statuses = ['draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired'];
        $quoteIds = [];
        for ($i = 0; $i < 20; $i++) {
            $subtotal = rand(5000, 100000);
            $taxAmount = $subtotal * 0.1;
            $discountAmount = rand(0, 1000);

            $quoteIds[] = DB::table('quotes')->insertGetId([
                'quote_number' => 'Q-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                'title' => 'Quote #' . ($i + 1),
                'status' => $statuses[array_rand($statuses)],
                'template_id' => !empty($templateIds) ? $templateIds[array_rand($templateIds)] : null,
                'subtotal' => $subtotal,
                'discount_type' => 'fixed',
                'discount_amount' => $discountAmount,
                'discount_percent' => 0,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount - $discountAmount,
                'currency' => 'USD',
                'valid_until' => now()->addDays(30),
                'version' => 1,
                'view_token' => Str::random(32),
                'created_by' => $this->userId,
                'assigned_to' => $this->randomUserId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('    - Created ' . count($quoteIds) . ' quotes');

        // Add line items
        if ($this->tableExists('quote_line_items')) {
            $products = ['Software License', 'Implementation', 'Training', 'Support', 'Consulting'];
            foreach ($quoteIds as $quoteId) {
                $numItems = rand(2, 5);
                for ($i = 0; $i < $numItems; $i++) {
                    $quantity = rand(1, 10);
                    $unitPrice = rand(1000, 20000);
                    DB::table('quote_line_items')->insert([
                        'quote_id' => $quoteId,
                        'product_id' => null,
                        'description' => $products[array_rand($products)] . ' - Line item description',
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_percent' => rand(0, 15),
                        'tax_rate' => 10,
                        'line_total' => $quantity * $unitPrice,
                        'display_order' => $i,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added quote line items');
        }
    }

    /**
     * Seed the Workflow System.
     */
    private function seedWorkflowSystem(): void
    {
        if (!$this->tableExists('workflows')) {
            $this->command->warn('  Skipping Workflows - table does not exist');
            return;
        }

        $this->command->info('  Seeding Workflow System...');

        // Get module
        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $moduleId = $dealsModule?->id ?? 1;

        // Create workflow email templates
        if ($this->tableExists('workflow_email_templates')) {
            $emailTemplates = [
                'Welcome Email',
                'Follow-up Reminder',
                'Deal Won Notification',
                'Deal Lost Notification',
                'Task Assignment',
            ];
            foreach ($emailTemplates as $name) {
                DB::table('workflow_email_templates')->insert([
                    'name' => $name,
                    'subject' => $name . ' - {{record.name}}',
                    'body_html' => '<p>Hello,</p><p>This is a ' . strtolower($name) . '.</p>',
                    'body_text' => 'Hello, This is a ' . strtolower($name) . '.',
                    'created_by' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('    - Created email templates');
        }

        // Create workflows
        $workflowData = [
            ['name' => 'Welcome Email for New Leads', 'trigger' => 'record_created', 'active' => true],
            ['name' => 'Stage Change Notification', 'trigger' => 'field_changed', 'active' => true],
            ['name' => 'Deal Amount Alert', 'trigger' => 'record_updated', 'active' => true],
            ['name' => 'Send Proposal to Customer', 'trigger' => 'manual', 'active' => true],
            ['name' => 'Old Campaign Workflow (Inactive)', 'trigger' => 'record_created', 'active' => false],
        ];

        $workflowIds = [];
        foreach ($workflowData as $data) {
            $workflowIds[] = DB::table('workflows')->insertGetId([
                'name' => $data['name'],
                'description' => 'Workflow: ' . $data['name'],
                'module_id' => $moduleId,
                'trigger_type' => $data['trigger'],
                'trigger_config' => json_encode([]),
                'conditions' => json_encode([]),
                'is_active' => $data['active'],
                'created_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('    - Created ' . count($workflowIds) . ' workflows');

        // Add steps
        if ($this->tableExists('workflow_steps')) {
            $actionTypes = ['send_email', 'create_record', 'update_record', 'webhook'];
            foreach ($workflowIds as $workflowId) {
                foreach (array_slice($actionTypes, 0, 3) as $order => $type) {
                    DB::table('workflow_steps')->insert([
                        'workflow_id' => $workflowId,
                        'order' => $order,
                        'name' => ucwords(str_replace('_', ' ', $type)) . ' Step',
                        'action_type' => $type,
                        'action_config' => json_encode(['type' => $type]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Added workflow steps');
        }

        // Create executions
        if ($this->tableExists('workflow_executions')) {
            foreach ($workflowIds as $workflowId) {
                for ($i = 0; $i < 10; $i++) {
                    $status = ['pending', 'running', 'completed', 'failed'][array_rand(['pending', 'running', 'completed', 'failed'])];
                    DB::table('workflow_executions')->insert([
                        'workflow_id' => $workflowId,
                        'trigger_record_id' => rand(1, 100),
                        'trigger_record_type' => 'module_records',
                        'trigger_type' => 'record_created',
                        'status' => $status,
                        'context_data' => json_encode([]),
                        'started_at' => now()->subHours(rand(1, 48)),
                        'completed_at' => $status === 'completed' ? now() : null,
                        'error_message' => $status === 'failed' ? 'Execution failed' : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Created workflow executions');
        }
    }

    /**
     * Seed the Blueprint System.
     */
    private function seedBlueprintSystem(): void
    {
        if (!$this->tableExists('blueprints')) {
            $this->command->warn('  Skipping Blueprints - table does not exist');
            return;
        }

        $this->command->info('  Seeding Blueprint System...');

        // Get module and field for the blueprint
        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $moduleId = $dealsModule?->id ?? 1;

        // Get a stage/status field from this module
        $stageField = DB::table('fields')
            ->where('module_id', $moduleId)
            ->where('api_name', 'stage')
            ->first();

        if (!$stageField) {
            // Try to find any picklist field
            $stageField = DB::table('fields')
                ->where('module_id', $moduleId)
                ->where('field_type', 'picklist')
                ->first();
        }

        if (!$stageField) {
            $this->command->warn('    - No suitable field found for blueprints');
            return;
        }

        // Create blueprints
        $blueprintIds = [];
        $blueprintIds[] = DB::table('blueprints')->insertGetId([
            'name' => 'Deal Stage Blueprint',
            'module_id' => $moduleId,
            'field_id' => $stageField->id,
            'description' => 'Controls deal stage transitions',
            'is_active' => true,
            'layout_data' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->command->info('    - Created ' . count($blueprintIds) . ' blueprints');

        // Add states
        if ($this->tableExists('blueprint_states')) {
            $states = ['New', 'In Progress', 'Review', 'Approved', 'Closed'];
            foreach ($blueprintIds as $blueprintId) {
                $stateIds = [];
                foreach ($states as $order => $stateName) {
                    $stateIds[] = DB::table('blueprint_states')->insertGetId([
                        'blueprint_id' => $blueprintId,
                        'name' => $stateName,
                        'field_option_value' => strtolower(str_replace(' ', '_', $stateName)),
                        'color' => ['#3498db', '#f1c40f', '#e67e22', '#27ae60', '#95a5a6'][$order],
                        'is_initial' => $order === 0,
                        'is_terminal' => $order === count($states) - 1,
                        'position_x' => $order * 200,
                        'position_y' => 100,
                        'metadata' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Add transitions
                if ($this->tableExists('blueprint_transitions')) {
                    for ($i = 0; $i < count($stateIds) - 1; $i++) {
                        DB::table('blueprint_transitions')->insert([
                            'blueprint_id' => $blueprintId,
                            'from_state_id' => $stateIds[$i],
                            'to_state_id' => $stateIds[$i + 1],
                            'name' => $states[$i] . ' to ' . $states[$i + 1],
                            'description' => 'Transition from ' . $states[$i] . ' to ' . $states[$i + 1],
                            'display_order' => $i,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
            $this->command->info('    - Added states and transitions');
        }
    }

    /**
     * Seed the Email System.
     */
    private function seedEmailSystem(): void
    {
        if (!$this->tableExists('email_accounts')) {
            $this->command->warn('  Skipping Email System - table does not exist');
            return;
        }

        $this->command->info('  Seeding Email System...');

        // Create email accounts
        $accountIds = [];
        foreach ($this->userIds as $index => $userId) {
            $accountIds[] = DB::table('email_accounts')->insertGetId([
                'user_id' => $userId,
                'name' => 'Work Email ' . ($index + 1),
                'email_address' => "user{$userId}@company.com",
                'provider' => 'imap',
                'imap_host' => 'imap.company.com',
                'smtp_host' => 'smtp.company.com',
                'is_active' => true,
                'is_default' => $index === 0,
                'sync_enabled' => true,
                'last_sync_at' => now(),
                'sync_folders' => json_encode(['INBOX']),
                'settings' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('    - Created email accounts');

        // Create email templates
        if ($this->tableExists('email_templates')) {
            $templates = [
                'Introduction Email',
                'Follow-up Email',
                'Thank You Email',
                'Proposal Email',
                'Meeting Request',
            ];
            foreach ($templates as $name) {
                DB::table('email_templates')->insert([
                    'name' => $name,
                    'subject' => $name . ' - {{contact.name}}',
                    'body_html' => '<p>Hello {{contact.first_name}},</p><p>Content for ' . strtolower($name) . '.</p>',
                    'body_text' => 'Hello {{contact.first_name}}, Content for ' . strtolower($name) . '.',
                    'is_active' => true,
                    'created_by' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('    - Created email templates');
        }

        // Create email messages
        if ($this->tableExists('email_messages')) {
            foreach ($accountIds as $accountId) {
                for ($i = 0; $i < 20; $i++) {
                    $direction = ['inbound', 'outbound'][array_rand(['inbound', 'outbound'])];
                    DB::table('email_messages')->insert([
                        'account_id' => $accountId,
                        'message_id' => Str::uuid()->toString(),
                        'thread_id' => Str::uuid()->toString(),
                        'direction' => $direction,
                        'status' => $direction === 'inbound' ? 'received' : ['sent', 'draft'][array_rand(['sent', 'draft'])],
                        'from_email' => $direction === 'inbound' ? 'sender@example.com' : 'user@company.com',
                        'from_name' => $direction === 'inbound' ? 'External Sender' : 'Company User',
                        'to_emails' => json_encode(['recipient@example.com']),
                        'cc_emails' => json_encode([]),
                        'bcc_emails' => json_encode([]),
                        'subject' => 'Email Subject ' . ($i + 1),
                        'body_text' => 'Email body content ' . ($i + 1),
                        'body_html' => '<p>Email body content ' . ($i + 1) . '</p>',
                        'folder' => 'INBOX',
                        'is_read' => rand(0, 1),
                        'has_attachments' => false,
                        'attachments' => json_encode([]),
                        'received_at' => $direction === 'inbound' ? now()->subDays(rand(0, 30)) : null,
                        'sent_at' => $direction === 'outbound' ? now()->subDays(rand(0, 30)) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $this->command->info('    - Created email messages');
        }
    }

    /**
     * Seed the Approval System.
     */
    private function seedApprovalSystem(): void
    {
        if (!$this->tableExists('approval_rules')) {
            $this->command->warn('  Skipping Approval System - table does not exist');
            return;
        }

        $this->command->info('  Seeding Approval System...');

        // Get module
        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $moduleId = $dealsModule?->id;

        // Create approval rules
        $ruleIds = [];
        $ruleIds[] = DB::table('approval_rules')->insertGetId([
            'name' => 'High Value Deal Approval',
            'description' => 'Requires approval for deals over $50,000',
            'entity_type' => 'quote',
            'module_id' => $moduleId,
            'conditions' => json_encode([['field' => 'amount', 'operator' => '>', 'value' => 50000]]),
            'approver_chain' => json_encode([['user_id' => $this->userId]]),
            'approval_type' => 'sequential',
            'is_active' => true,
            'priority' => 1,
            'created_by' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ruleIds[] = DB::table('approval_rules')->insertGetId([
            'name' => 'Discount Approval',
            'description' => 'Requires approval for discounts over 20%',
            'entity_type' => 'discount',
            'module_id' => $moduleId,
            'conditions' => json_encode([['field' => 'discount_percent', 'operator' => '>', 'value' => 20]]),
            'approver_chain' => json_encode([['user_id' => $this->userId]]),
            'approval_type' => 'sequential',
            'is_active' => true,
            'priority' => 2,
            'created_by' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('    - Created approval rules');

        // Create approval requests
        if ($this->tableExists('approval_requests')) {
            $requestIds = [];
            foreach ($ruleIds as $index => $ruleId) {
                $requestIds[] = DB::table('approval_requests')->insertGetId([
                    'uuid' => Str::uuid()->toString(),
                    'rule_id' => $ruleId,
                    'entity_type' => $index === 0 ? 'quote' : 'discount',
                    'entity_id' => 1,
                    'title' => $index === 0 ? 'Quote Approval Request' : 'Discount Approval Request',
                    'description' => 'Sample approval request',
                    'status' => ['pending', 'approved', 'rejected'][array_rand(['pending', 'approved', 'rejected'])],
                    'value' => $index === 0 ? 75000 : 25,
                    'currency' => 'USD',
                    'submitted_at' => now(),
                    'requested_by' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('    - Created approval requests');

            // Create approval steps for requests
            if ($this->tableExists('approval_steps')) {
                foreach ($requestIds as $requestId) {
                    DB::table('approval_steps')->insert([
                        'request_id' => $requestId,
                        'approver_id' => $this->randomUserId(),
                        'approver_type' => 'user',
                        'step_order' => 1,
                        'status' => 'pending',
                        'is_current' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $this->command->info('    - Created approval steps');
            }
        }

        $this->command->info('    - Approval system seeding completed');
    }

    /**
     * Seed API Keys and Webhooks.
     */
    private function seedApiAndWebhooks(): void
    {
        // API Keys
        if ($this->tableExists('api_keys')) {
            $this->command->info('  Seeding API Keys...');
            DB::table('api_keys')->insert([
                [
                    'name' => 'Integration API Key',
                    'key' => hash('sha256', 'integration-api-key-' . uniqid()),
                    'prefix' => 'vrtx_int',
                    'user_id' => $this->userId,
                    'description' => 'Full read/write access for integrations',
                    'scopes' => json_encode(['records:read', 'records:write']),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Read-Only API Key',
                    'key' => hash('sha256', 'readonly-api-key-' . uniqid()),
                    'prefix' => 'vrtx_ro_',
                    'user_id' => $this->userId,
                    'description' => 'Read-only access for reporting',
                    'scopes' => json_encode(['records:read']),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            $this->command->info('    - Created 2 API keys');
        }

        // Webhooks
        if ($this->tableExists('webhooks')) {
            $this->command->info('  Seeding Webhooks...');
            DB::table('webhooks')->insert([
                [
                    'name' => 'Deal Created Webhook',
                    'description' => 'Notifies external system when deals are created',
                    'url' => 'https://webhook.example.com/deals/created',
                    'events' => json_encode(['deal.created']),
                    'is_active' => true,
                    'secret' => substr(hash('sha256', 'webhook-secret-' . uniqid()), 0, 64),
                    'user_id' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Contact Updated Webhook',
                    'description' => 'Syncs contact updates to external CRM',
                    'url' => 'https://webhook.example.com/contacts/updated',
                    'events' => json_encode(['contact.updated', 'contact.created']),
                    'is_active' => true,
                    'secret' => substr(hash('sha256', 'webhook-secret-' . uniqid()), 0, 64),
                    'user_id' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            $this->command->info('    - Created 2 webhooks');
        }

        // Incoming Webhooks
        if ($this->tableExists('incoming_webhooks')) {
            $moduleId = DB::table('modules')->where('api_name', 'deals')->first()?->id;
            if ($moduleId) {
                DB::table('incoming_webhooks')->insert([
                    'name' => 'Stripe Payment Webhook',
                    'description' => 'Receives payment notifications from Stripe',
                    'token' => substr(hash('sha256', 'incoming-webhook-' . uniqid()), 0, 64),
                    'module_id' => $moduleId,
                    'field_mapping' => json_encode([]),
                    'is_active' => true,
                    'action' => 'update',
                    'user_id' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('    - Created 1 incoming webhook');
        }
    }

    /**
     * Seed Duplicate Management.
     */
    private function seedDuplicateManagement(): void
    {
        if (!$this->tableExists('duplicate_rules')) {
            $this->command->warn('  Skipping Duplicate Management - table does not exist');
            return;
        }

        $this->command->info('  Seeding Duplicate Management...');

        $contactModule = DB::table('modules')->where('api_name', 'contacts')->first();
        $orgModule = DB::table('modules')->where('api_name', 'organizations')->first();

        if ($contactModule) {
            DB::table('duplicate_rules')->insert([
                'name' => 'Contact Email Duplicate Check',
                'description' => 'Warns when contact email matches existing record',
                'module_id' => $contactModule->id,
                'conditions' => json_encode([
                    'logic' => 'or',
                    'rules' => [
                        ['field' => 'email', 'operator' => 'equals', 'type' => 'exact'],
                    ],
                ]),
                'action' => 'warn',
                'is_active' => true,
                'priority' => 1,
                'created_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($orgModule) {
            DB::table('duplicate_rules')->insert([
                'name' => 'Organization Name Fuzzy Match',
                'description' => 'Blocks when organization name is similar to existing record',
                'module_id' => $orgModule->id,
                'conditions' => json_encode([
                    'logic' => 'or',
                    'rules' => [
                        ['field' => 'name', 'operator' => 'fuzzy', 'threshold' => 0.8],
                    ],
                ]),
                'action' => 'block',
                'is_active' => true,
                'priority' => 1,
                'created_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('    - Created duplicate rules');
    }

    /**
     * Seed Dashboards and Reports.
     */
    private function seedDashboardsAndReports(): void
    {
        // Seed Dashboards
        if ($this->tableExists('dashboards')) {
            $this->command->info('  Seeding Dashboards...');

            $dashboardData = [
                ['name' => 'Sales Overview', 'is_default' => true, 'is_public' => false],
                ['name' => 'Team Performance', 'is_default' => false, 'is_public' => true],
                ['name' => 'Real-time Pipeline', 'is_default' => false, 'is_public' => false],
                ['name' => 'Marketing Metrics', 'is_default' => false, 'is_public' => false],
            ];

            $dashboardIds = [];
            foreach ($dashboardData as $index => $data) {
                $dashboardIds[] = DB::table('dashboards')->insertGetId([
                    'name' => $data['name'],
                    'description' => 'Dashboard for ' . strtolower($data['name']),
                    'user_id' => $this->userIds[$index % count($this->userIds)],
                    'is_default' => $data['is_default'],
                    'is_public' => $data['is_public'],
                    'layout' => json_encode([]),
                    'settings' => json_encode([]),
                    'filters' => json_encode([]),
                    'refresh_interval' => 300,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('    - Created ' . count($dashboardIds) . ' dashboards');

            // Add widgets
            if ($this->tableExists('dashboard_widgets')) {
                $widgetTypes = [
                    ['type' => 'kpi', 'title' => 'Total Deals', 'w' => 4, 'h' => 2],
                    ['type' => 'kpi', 'title' => 'Revenue', 'w' => 4, 'h' => 2],
                    ['type' => 'chart', 'title' => 'Deals by Stage', 'w' => 6, 'h' => 4],
                    ['type' => 'chart', 'title' => 'Revenue Trend', 'w' => 6, 'h' => 4],
                    ['type' => 'activity', 'title' => 'Recent Activity', 'w' => 6, 'h' => 4],
                    ['type' => 'tasks', 'title' => 'My Tasks', 'w' => 6, 'h' => 4],
                ];
                foreach ($dashboardIds as $dashboardId) {
                    foreach ($widgetTypes as $order => $widget) {
                        // Calculate grid position (3 widgets per row in a 12-column grid)
                        $x = ($order % 2) * 6;
                        $y = floor($order / 2) * 4;
                        DB::table('dashboard_widgets')->insert([
                            'dashboard_id' => $dashboardId,
                            'type' => $widget['type'],
                            'title' => $widget['title'],
                            'config' => json_encode(['chart_type' => 'bar']),
                            'grid_position' => json_encode([
                                'x' => $x,
                                'y' => $y,
                                'w' => $widget['w'],
                                'h' => $widget['h'],
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                $this->command->info('    - Added widgets to dashboards');
            }
        }

        // Seed Reports
        if ($this->tableExists('reports')) {
            $this->command->info('  Seeding Reports...');

            $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
            $moduleId = $dealsModule?->id;

            $reportData = [
                ['name' => 'All Deals Report', 'type' => 'table', 'chart_type' => null, 'is_public' => false],
                ['name' => 'Open Opportunities', 'type' => 'table', 'chart_type' => null, 'is_public' => true],
                ['name' => 'Deals by Stage', 'type' => 'chart', 'chart_type' => 'bar', 'is_public' => false],
                ['name' => 'Monthly Revenue Trend', 'type' => 'chart', 'chart_type' => 'line', 'is_public' => false],
                ['name' => 'Lead Source Distribution', 'type' => 'chart', 'chart_type' => 'pie', 'is_public' => false],
                ['name' => 'Sales Summary', 'type' => 'summary', 'chart_type' => null, 'is_public' => false],
                ['name' => 'Revenue by Rep by Quarter', 'type' => 'pivot', 'chart_type' => null, 'is_public' => false],
                ['name' => 'Contacts by Company', 'type' => 'table', 'chart_type' => null, 'is_public' => false],
            ];

            foreach ($reportData as $data) {
                DB::table('reports')->insert([
                    'name' => $data['name'],
                    'description' => 'Report: ' . $data['name'],
                    'module_id' => $moduleId,
                    'type' => $data['type'],
                    'chart_type' => $data['chart_type'],
                    'config' => json_encode(['columns' => ['name', 'amount', 'stage', 'created_at']]),
                    'filters' => json_encode([]),
                    'grouping' => json_encode([]),
                    'aggregations' => json_encode([]),
                    'sorting' => json_encode([]),
                    'date_range' => json_encode([]),
                    'is_public' => $data['is_public'],
                    'is_favorite' => rand(0, 1),
                    'user_id' => $this->randomUserId(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('    - Created ' . count($reportData) . ' reports');
        }
    }

    /**
     * Seed Saved Searches.
     */
    private function seedSavedSearches(): void
    {
        if (!$this->tableExists('saved_searches')) {
            $this->command->warn('  Skipping Saved Searches - table does not exist');
            return;
        }

        $this->command->info('  Seeding Saved Searches...');

        $searches = [
            ['name' => 'Hot Leads', 'query' => 'hot leads', 'module' => null, 'pinned' => true],
            ['name' => 'Enterprise Deals', 'query' => 'enterprise', 'module' => null, 'pinned' => false],
            ['name' => 'Active Contacts', 'query' => 'active', 'module' => 'contacts', 'pinned' => false],
            ['name' => 'Deals in Negotiation', 'query' => 'negotiation', 'module' => 'deals', 'pinned' => true],
            ['name' => 'High Value Deals', 'query' => 'high value', 'module' => 'deals', 'pinned' => false],
            ['name' => 'Unqualified Leads', 'query' => 'unqualified', 'module' => 'leads', 'pinned' => false],
        ];

        foreach ($searches as $search) {
            DB::table('saved_searches')->insert([
                'name' => $search['name'],
                'query' => $search['query'],
                'type' => $search['module'] ? 'module' : 'global',
                'module_api_name' => $search['module'],
                'filters' => json_encode([]),
                'is_pinned' => $search['pinned'],
                'use_count' => rand(0, 50),
                'user_id' => $this->randomUserId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('    - Created ' . count($searches) . ' saved searches');
    }

    /**
     * Seed Audit Logs.
     */
    private function seedAuditLogs(): void
    {
        if (!$this->tableExists('audit_logs')) {
            $this->command->warn('  Skipping Audit Logs - table does not exist');
            return;
        }

        $this->command->info('  Seeding Audit Logs...');

        $events = ['created', 'updated', 'deleted', 'restored'];
        $recordTypes = ['module_records', 'users', 'workflows', 'reports'];

        foreach ($this->userIds as $userId) {
            for ($i = 0; $i < 15; $i++) {
                DB::table('audit_logs')->insert([
                    'user_id' => $userId,
                    'event' => $events[array_rand($events)],
                    'auditable_type' => $recordTypes[array_rand($recordTypes)],
                    'auditable_id' => rand(1, 100),
                    'old_values' => json_encode(['field' => 'old_value']),
                    'new_values' => json_encode(['field' => 'new_value']),
                    'ip_address' => '192.168.1.' . rand(1, 255),
                    'user_agent' => 'Mozilla/5.0 (compatible; Demo)',
                    'url' => '/api/v1/records/' . rand(1, 100),
                    'created_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }

        $this->command->info('    - Created audit log entries');
    }
}
