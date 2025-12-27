<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

/**
 * Seeds sample/demo data for a new tenant.
 * This creates realistic records for testing and demonstration.
 */
class SampleDataSeeder extends Seeder
{
    private $faker;

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    public function run(): void
    {
        $this->command->info('Seeding sample data...');

        // Seed in order of dependencies
        $orgIds = $this->seedOrganizations(50);
        $contactIds = $this->seedContacts(100, $orgIds);
        $productIds = $this->seedProducts(40);
        $dealIds = $this->seedDeals(30, $orgIds, $contactIds);
        $this->seedTasks(60, $contactIds, $dealIds);
        $this->seedActivities(80, $contactIds, $dealIds);
        $this->seedCases(35, $orgIds, $contactIds);
        $this->seedInvoices(40, $orgIds, $dealIds);
        $this->seedQuotes(20, $orgIds, $dealIds);
        $this->seedEvents(30);
        $this->seedNotes(25, $contactIds, $dealIds);

        $this->command->info('Sample data seeded successfully!');
    }

    private function seedOrganizations(int $count): array
    {
        $module = DB::table('modules')->where('api_name', 'organizations')->first();
        if (!$module) return [];

        $ids = [];
        $industries = ['technology', 'healthcare', 'finance', 'retail', 'manufacturing', 'education', 'real_estate', 'consulting', 'media', 'other'];
        $sizes = ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'];
        $types = ['customer', 'prospect', 'partner', 'vendor', 'competitor'];

        for ($i = 0; $i < $count; $i++) {
            $id = DB::table('module_records')->insertGetId([
                'module_id' => $module->id,
                'data' => json_encode([
                    'name' => $this->faker->company,
                    'website' => $this->faker->url,
                    'industry' => $this->faker->randomElement($industries),
                    'employee_count' => $this->faker->randomElement($sizes),
                    'phone' => $this->faker->phoneNumber,
                    'email' => $this->faker->companyEmail,
                    'street' => $this->faker->streetAddress,
                    'city' => $this->faker->city,
                    'state' => $this->faker->state,
                    'postal_code' => $this->faker->postcode,
                    'country' => 'united_states',
                    'type' => $this->faker->randomElement($types),
                    'annual_revenue' => $this->faker->numberBetween(100000, 50000000),
                    'description' => $this->faker->paragraph,
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[] = $id;
        }

        $this->command->info("  - Created {$count} organizations");
        return $ids;
    }

    private function seedContacts(int $count, array $orgIds): array
    {
        $module = DB::table('modules')->where('api_name', 'contacts')->first();
        if (!$module) return [];

        $ids = [];
        $statuses = ['lead', 'prospect', 'customer', 'partner', 'inactive'];
        $sources = ['website', 'referral', 'social_media', 'email_campaign', 'cold_call', 'trade_show', 'partner', 'other'];
        $titles = ['CEO', 'CTO', 'CFO', 'VP of Sales', 'VP of Marketing', 'Director', 'Manager', 'Senior Engineer', 'Analyst', 'Consultant'];
        $departments = ['Sales', 'Marketing', 'Engineering', 'Finance', 'HR', 'Operations', 'Executive', 'IT', 'Support'];

        for ($i = 0; $i < $count; $i++) {
            $id = DB::table('module_records')->insertGetId([
                'module_id' => $module->id,
                'data' => json_encode([
                    'first_name' => $this->faker->firstName,
                    'last_name' => $this->faker->lastName,
                    'email' => $this->faker->unique()->safeEmail,
                    'phone' => $this->faker->phoneNumber,
                    'mobile' => $this->faker->phoneNumber,
                    'organization_id' => !empty($orgIds) ? $this->faker->randomElement($orgIds) : null,
                    'job_title' => $this->faker->randomElement($titles),
                    'department' => $this->faker->randomElement($departments),
                    'linkedin_url' => 'https://linkedin.com/in/' . $this->faker->slug,
                    'city' => $this->faker->city,
                    'state' => $this->faker->state,
                    'country' => 'united_states',
                    'status' => $this->faker->randomElement($statuses),
                    'lead_source' => $this->faker->randomElement($sources),
                    'do_not_contact' => $this->faker->boolean(10),
                    'notes' => $this->faker->optional(0.3)->paragraph,
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[] = $id;
        }

        $this->command->info("  - Created {$count} contacts");
        return $ids;
    }

    private function seedProducts(int $count): array
    {
        $module = DB::table('modules')->where('api_name', 'products')->first();
        if (!$module) return [];

        $ids = [];
        $categories = ['software', 'hardware', 'services', 'consulting', 'training', 'support', 'subscription', 'add-on'];
        $productNames = [
            'Professional License', 'Enterprise License', 'Basic Plan', 'Premium Plan',
            'API Access', 'Custom Integration', 'Training Package', 'Support Bundle',
            'Data Migration', 'Implementation Service', 'Consulting Hours', 'Premium Support',
            'Analytics Add-on', 'Security Module', 'Backup Service', 'White Label Option'
        ];

        for ($i = 0; $i < $count; $i++) {
            $unitPrice = $this->faker->numberBetween(100, 10000);
            $cost = round($unitPrice * $this->faker->randomFloat(2, 0.3, 0.6), 2);

            $id = DB::table('module_records')->insertGetId([
                'module_id' => $module->id,
                'data' => json_encode([
                    'name' => $this->faker->randomElement($productNames) . ' ' . $this->faker->word,
                    'sku' => strtoupper($this->faker->bothify('???-####')),
                    'description' => $this->faker->paragraph,
                    'category' => $this->faker->randomElement($categories),
                    'unit_price' => $unitPrice,
                    'cost' => $cost,
                    'margin' => round((($unitPrice - $cost) / $unitPrice) * 100, 2),
                    'tax_rate' => $this->faker->randomElement([0, 5, 10, 15, 20]),
                    'quantity_in_stock' => $this->faker->numberBetween(0, 500),
                    'reorder_level' => $this->faker->numberBetween(10, 50),
                    'is_active' => $this->faker->boolean(90),
                    'vendor' => $this->faker->company,
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[] = $id;
        }

        $this->command->info("  - Created {$count} products");
        return $ids;
    }

    private function seedDeals(int $count, array $orgIds, array $contactIds): array
    {
        $module = DB::table('modules')->where('api_name', 'deals')->first();
        if (!$module) return [];

        $pipeline = DB::table('pipelines')->where('module_id', $module->id)->first();
        $stages = $pipeline
            ? DB::table('pipeline_stages')
                ->where('pipeline_id', $pipeline->id)
                ->orderBy('display_order')
                ->get()
            : collect();

        $ids = [];
        $sources = ['website', 'referral', 'partner', 'outbound', 'inbound', 'event', 'other'];

        for ($i = 0; $i < $count; $i++) {
            $stage = $stages->isNotEmpty() ? $stages->random() : null;
            $amount = $this->faker->numberBetween(5000, 500000);
            $probability = $stage ? ($stage->probability ?? 50) : 50;

            $expectedClose = $this->faker->dateTimeBetween('-1 month', '+3 months');

            $id = DB::table('module_records')->insertGetId([
                'module_id' => $module->id,
                'data' => json_encode([
                    'name' => $this->faker->company . ' - ' . $this->faker->words(3, true),
                    'amount' => $amount,
                    'probability' => $probability,
                    'expected_revenue' => $amount * ($probability / 100),
                    'organization_id' => !empty($orgIds) ? $this->faker->randomElement($orgIds) : null,
                    'contact_id' => !empty($contactIds) ? $this->faker->randomElement($contactIds) : null,
                    'stage' => $stage ? (string) $stage->id : 'prospecting',
                    'close_date' => $expectedClose->format('Y-m-d'),
                    'source' => $this->faker->randomElement($sources),
                    'description' => $this->faker->paragraph,
                    'next_step' => $this->faker->optional()->sentence,
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[] = $id;
        }

        $this->command->info("  - Created {$count} deals");
        return $ids;
    }

    private function seedTasks(int $count, array $contactIds, array $dealIds): void
    {
        $module = DB::table('modules')->where('api_name', 'tasks')->first();
        if (!$module) return;

        $statuses = ['not_started', 'in_progress', 'completed', 'waiting', 'deferred'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $subjects = [
            'Follow up call', 'Send proposal', 'Schedule meeting', 'Review contract',
            'Prepare presentation', 'Send quote', 'Complete demo', 'Send introduction email',
            'Review requirements', 'Update CRM records', 'Research competitor', 'Prepare report'
        ];

        for ($i = 0; $i < $count; $i++) {
            $status = $this->faker->randomElement($statuses);
            $dueDate = $this->faker->dateTimeBetween('-1 week', '+2 weeks');

            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode([
                    'subject' => $this->faker->randomElement($subjects),
                    'description' => $this->faker->optional()->paragraph,
                    'priority' => $this->faker->randomElement($priorities),
                    'status' => $status,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'related_to_type' => $this->faker->randomElement(['contact', 'deal']),
                    'related_to_id' => $this->faker->boolean() && !empty($contactIds)
                        ? $this->faker->randomElement($contactIds)
                        : (!empty($dealIds) ? $this->faker->randomElement($dealIds) : null),
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("  - Created {$count} tasks");
    }

    private function seedActivities(int $count, array $contactIds, array $dealIds): void
    {
        $module = DB::table('modules')->where('api_name', 'activities')->first();
        if (!$module) return;

        $types = ['call', 'meeting', 'email', 'note', 'demo', 'lunch', 'other'];
        $outcomes = ['completed', 'no_answer', 'left_message', 'rescheduled', 'cancelled'];
        $subjects = [
            'Discovery call', 'Product demo', 'Quarterly review', 'Contract negotiation',
            'Initial outreach', 'Technical discussion', 'Pricing discussion', 'Requirements gathering'
        ];

        for ($i = 0; $i < $count; $i++) {
            $type = $this->faker->randomElement($types);
            $startTime = $this->faker->dateTimeBetween('-1 month', '+1 week');
            $duration = $this->faker->randomElement([15, 30, 45, 60, 90, 120]);
            $endTime = (clone $startTime)->modify("+{$duration} minutes");

            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode([
                    'subject' => $this->faker->randomElement($subjects),
                    'type' => $type,
                    'description' => $this->faker->optional()->paragraph,
                    'start_datetime' => $startTime->format('Y-m-d H:i:s'),
                    'end_datetime' => $endTime->format('Y-m-d H:i:s'),
                    'duration_minutes' => $duration,
                    'contact_id' => !empty($contactIds) ? $this->faker->randomElement($contactIds) : null,
                    'deal_id' => !empty($dealIds) ? $this->faker->randomElement($dealIds) : null,
                    'outcome' => $startTime < now() ? $this->faker->randomElement($outcomes) : null,
                    'next_action' => $this->faker->optional()->sentence,
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("  - Created {$count} activities");
    }

    private function seedCases(int $count, array $orgIds, array $contactIds): void
    {
        $module = DB::table('modules')->where('api_name', 'cases')->first();
        if (!$module) return;

        $pipeline = DB::table('pipelines')->where('module_id', $module->id)->first();
        $stages = $pipeline
            ? DB::table('pipeline_stages')
                ->where('pipeline_id', $pipeline->id)
                ->orderBy('display_order')
                ->get()
            : collect();

        $priorities = ['low', 'medium', 'high', 'critical'];
        $severities = ['minor', 'major', 'critical', 'blocker'];
        $types = ['question', 'problem', 'feature_request', 'bug'];
        $subjects = [
            'Cannot login to account', 'Feature request: Export to PDF', 'Integration not working',
            'Slow performance issue', 'Billing question', 'How to configure settings',
            'Data import error', 'API authentication failing', 'Report not generating'
        ];

        for ($i = 0; $i < $count; $i++) {
            $stage = $stages->isNotEmpty() ? $stages->random() : null;
            $openedDate = $this->faker->dateTimeBetween('-3 months', 'now');

            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode([
                    'case_number' => 'CS-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                    'subject' => $this->faker->randomElement($subjects),
                    'description' => $this->faker->paragraph(3),
                    'type' => $this->faker->randomElement($types),
                    'status' => $stage ? (string) $stage->id : 'new',
                    'priority' => $this->faker->randomElement($priorities),
                    'severity' => $this->faker->randomElement($severities),
                    'contact_id' => !empty($contactIds) ? $this->faker->randomElement($contactIds) : null,
                    'organization_id' => !empty($orgIds) ? $this->faker->randomElement($orgIds) : null,
                    'sla_due_date' => (clone $openedDate)->modify('+24 hours')->format('Y-m-d H:i:s'),
                    'escalated' => $this->faker->boolean(20),
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("  - Created {$count} cases");
    }

    private function seedInvoices(int $count, array $orgIds, array $dealIds): void
    {
        $module = DB::table('modules')->where('api_name', 'invoices')->first();
        if (!$module) return;

        $statuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
        $paymentTerms = ['due_on_receipt', 'net_15', 'net_30', 'net_45', 'net_60'];
        $paymentMethods = ['bank_transfer', 'credit_card', 'check', 'cash', 'paypal'];

        for ($i = 0; $i < $count; $i++) {
            $status = $this->faker->randomElement($statuses);
            $subtotal = $this->faker->numberBetween(1000, 50000);
            $taxAmount = round($subtotal * 0.1, 2);
            $discountAmount = $this->faker->boolean(30) ? $this->faker->numberBetween(100, 1000) : 0;
            $total = $subtotal + $taxAmount - $discountAmount;
            $amountPaid = $status === 'paid' ? $total : ($status === 'sent' || $status === 'overdue' ? 0 : $this->faker->numberBetween(0, (int) $total));

            $invoiceDate = $this->faker->dateTimeBetween('-3 months', 'now');
            $dueDate = (clone $invoiceDate)->modify('+30 days');

            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode([
                    'invoice_number' => 'INV-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'amount_paid' => $amountPaid,
                    'balance_due' => $total - $amountPaid,
                    'organization_id' => !empty($orgIds) ? $this->faker->randomElement($orgIds) : null,
                    'deal_id' => !empty($dealIds) ? $this->faker->randomElement($dealIds) : null,
                    'invoice_date' => $invoiceDate->format('Y-m-d'),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'payment_date' => $status === 'paid' ? $this->faker->dateTimeBetween($invoiceDate, 'now')->format('Y-m-d') : null,
                    'payment_terms' => $this->faker->randomElement($paymentTerms),
                    'payment_method' => $status === 'paid' ? $this->faker->randomElement($paymentMethods) : null,
                    'notes' => $this->faker->optional()->paragraph,
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("  - Created {$count} invoices");
    }

    private function seedQuotes(int $count, array $orgIds, array $dealIds): void
    {
        $module = DB::table('modules')->where('api_name', 'quotes')->first();
        if (!$module) return;

        $statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

        for ($i = 0; $i < $count; $i++) {
            $status = $this->faker->randomElement($statuses);
            $subtotal = $this->faker->numberBetween(5000, 100000);
            $discountPercent = $this->faker->boolean(40) ? $this->faker->randomElement([5, 10, 15, 20]) : 0;
            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            $taxAmount = round(($subtotal - $discountAmount) * 0.1, 2);
            $total = $subtotal - $discountAmount + $taxAmount;

            $quoteDate = $this->faker->dateTimeBetween('-2 months', 'now');
            $validUntil = (clone $quoteDate)->modify('+30 days');

            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode([
                    'quote_number' => 'QT-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                    'subject' => $this->faker->company . ' - ' . $this->faker->words(2, true),
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'organization_id' => !empty($orgIds) ? $this->faker->randomElement($orgIds) : null,
                    'deal_id' => !empty($dealIds) ? $this->faker->randomElement($dealIds) : null,
                    'quote_date' => $quoteDate->format('Y-m-d'),
                    'valid_until' => $validUntil->format('Y-m-d'),
                    'accepted_date' => $status === 'accepted' ? $this->faker->dateTimeBetween($quoteDate, 'now')->format('Y-m-d') : null,
                    'terms' => 'Quote valid for 30 days. Prices subject to change.',
                    'notes' => $this->faker->optional()->paragraph,
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("  - Created {$count} quotes");
    }

    private function seedEvents(int $count): void
    {
        $module = DB::table('modules')->where('api_name', 'events')->first();
        if (!$module) return;

        $types = ['meeting', 'call', 'webinar', 'conference', 'personal', 'other'];
        $titles = [
            'Team Standup', 'Client Meeting', 'Product Demo', 'Sales Call',
            'Training Session', 'Strategy Review', 'Quarterly Planning', 'One-on-One'
        ];

        for ($i = 0; $i < $count; $i++) {
            $startTime = $this->faker->dateTimeBetween('-1 week', '+2 weeks');
            $duration = $this->faker->randomElement([30, 60, 90, 120]);
            $endTime = (clone $startTime)->modify("+{$duration} minutes");
            $allDay = $this->faker->boolean(10);

            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode([
                    'title' => $this->faker->randomElement($titles),
                    'description' => $this->faker->optional()->paragraph,
                    'location' => $this->faker->randomElement(['Conference Room A', 'Conference Room B', 'Zoom', 'Google Meet', 'Teams', 'On-site']),
                    'event_type' => $this->faker->randomElement($types),
                    'start_datetime' => $allDay ? $startTime->format('Y-m-d') . ' 00:00:00' : $startTime->format('Y-m-d H:i:s'),
                    'end_datetime' => $allDay ? $startTime->format('Y-m-d') . ' 23:59:59' : $endTime->format('Y-m-d H:i:s'),
                    'all_day' => $allDay,
                    'is_recurring' => $this->faker->boolean(20),
                    'reminder_minutes' => $this->faker->randomElement(['none', '5_minutes', '15_minutes', '30_minutes', '1_hour']),
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("  - Created {$count} events");
    }

    private function seedNotes(int $count, array $contactIds, array $dealIds): void
    {
        $module = DB::table('modules')->where('api_name', 'notes')->first();
        if (!$module) return;

        $visibilities = ['everyone', 'team_only', 'private'];
        $titles = [
            'Meeting Notes', 'Call Summary', 'Important Update', 'Requirements',
            'Technical Details', 'Follow-up Action', 'Customer Feedback', 'Strategy Notes'
        ];

        for ($i = 0; $i < $count; $i++) {
            $relatedType = $this->faker->randomElement(['contact', 'organization', 'deal']);

            DB::table('module_records')->insert([
                'module_id' => $module->id,
                'data' => json_encode([
                    'title' => $this->faker->randomElement($titles) . ' - ' . $this->faker->date(),
                    'content' => $this->faker->paragraphs(3, true),
                    'related_to_type' => $relatedType,
                    'related_to_id' => $relatedType === 'contact' && !empty($contactIds)
                        ? $this->faker->randomElement($contactIds)
                        : ($relatedType === 'deal' && !empty($dealIds) ? $this->faker->randomElement($dealIds) : null),
                    'is_pinned' => $this->faker->boolean(15),
                    'visibility' => $this->faker->randomElement($visibilities),
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("  - Created {$count} notes");
    }
}
