<?php

namespace Database\Seeders;

use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create users
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->info('No users found. Please seed users first.');
            return;
        }

        // Initialize billing settings
        $settings = BillingSetting::first();
        if (!$settings) {
            $settings = BillingSetting::create([
                'company_name' => 'VRTX Corporation',
                'company_address' => "123 Innovation Drive\nSuite 500\nSan Francisco, CA 94102",
                'company_phone' => '+1 (415) 555-0100',
                'company_email' => 'billing@vrtx.io',
                'company_website' => 'https://vrtx.io',
                'tax_id' => 'US12-3456789',
                'default_currency' => 'USD',
                'default_payment_terms' => 30,
                'quote_validity_days' => 30,
                'next_quote_number' => 1001,
                'next_invoice_number' => 5001,
                'quote_prefix' => 'QT-',
                'invoice_prefix' => 'INV-',
                'footer_text' => 'Thank you for your business! Payment is due within the specified terms.',
            ]);
        }

        // Create product categories
        $categories = $this->createCategories();

        // Create products
        $products = $this->createProducts($categories);

        // Create quotes with various statuses
        $this->createQuotes($users, $products, $settings);

        // Create invoices with various statuses
        $this->createInvoices($users, $products, $settings);

        $this->command->info('Billing data seeded successfully!');
    }

    private function createCategories(): array
    {
        $categoriesData = [
            ['name' => 'Software Licenses', 'description' => 'Software licensing and subscriptions'],
            ['name' => 'Professional Services', 'description' => 'Consulting, implementation, and training'],
            ['name' => 'Support & Maintenance', 'description' => 'Ongoing support and maintenance plans'],
            ['name' => 'Hardware', 'description' => 'Physical equipment and devices'],
            ['name' => 'Add-ons & Integrations', 'description' => 'Additional modules and third-party integrations'],
        ];

        $categories = [];
        foreach ($categoriesData as $data) {
            $categories[$data['name']] = ProductCategory::firstOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description'], 'is_active' => true]
            );
        }

        return $categories;
    }

    private function createProducts(array $categories): array
    {
        $productsData = [
            // Software Licenses
            [
                'category' => 'Software Licenses',
                'name' => 'VRTX CRM - Professional',
                'sku' => 'CRM-PRO-001',
                'description' => 'Full-featured CRM with automation, reporting, and integrations. Per user per month.',
                'unit_price' => 49.00,
                'unit' => 'user/month',
                'is_recurring' => true,
            ],
            [
                'category' => 'Software Licenses',
                'name' => 'VRTX CRM - Enterprise',
                'sku' => 'CRM-ENT-001',
                'description' => 'Enterprise CRM with advanced analytics, custom modules, and dedicated support. Per user per month.',
                'unit_price' => 99.00,
                'unit' => 'user/month',
                'is_recurring' => true,
            ],
            [
                'category' => 'Software Licenses',
                'name' => 'VRTX CRM - Starter',
                'sku' => 'CRM-STR-001',
                'description' => 'Basic CRM features for small teams. Up to 5 users.',
                'unit_price' => 149.00,
                'unit' => 'month',
                'is_recurring' => true,
            ],
            // Professional Services
            [
                'category' => 'Professional Services',
                'name' => 'Implementation Package - Basic',
                'sku' => 'SVC-IMP-BAS',
                'description' => 'Basic setup and configuration, data migration (up to 10,000 records), 2 hours training.',
                'unit_price' => 1500.00,
                'unit' => 'package',
                'is_recurring' => false,
            ],
            [
                'category' => 'Professional Services',
                'name' => 'Implementation Package - Standard',
                'sku' => 'SVC-IMP-STD',
                'description' => 'Full setup, data migration (up to 100,000 records), workflow configuration, 8 hours training.',
                'unit_price' => 4500.00,
                'unit' => 'package',
                'is_recurring' => false,
            ],
            [
                'category' => 'Professional Services',
                'name' => 'Implementation Package - Enterprise',
                'sku' => 'SVC-IMP-ENT',
                'description' => 'Complete implementation with custom modules, unlimited data migration, dedicated PM, 24 hours training.',
                'unit_price' => 12000.00,
                'unit' => 'package',
                'is_recurring' => false,
            ],
            [
                'category' => 'Professional Services',
                'name' => 'Consulting Hours',
                'sku' => 'SVC-CON-HR',
                'description' => 'Expert consulting for workflow optimization, best practices, and custom solutions.',
                'unit_price' => 175.00,
                'unit' => 'hour',
                'is_recurring' => false,
            ],
            [
                'category' => 'Professional Services',
                'name' => 'Custom Development',
                'sku' => 'SVC-DEV-HR',
                'description' => 'Custom feature development and integrations.',
                'unit_price' => 200.00,
                'unit' => 'hour',
                'is_recurring' => false,
            ],
            // Support & Maintenance
            [
                'category' => 'Support & Maintenance',
                'name' => 'Premium Support - Annual',
                'sku' => 'SUP-PRM-YR',
                'description' => '24/7 priority support, 1-hour response time SLA, dedicated account manager.',
                'unit_price' => 2400.00,
                'unit' => 'year',
                'is_recurring' => true,
            ],
            [
                'category' => 'Support & Maintenance',
                'name' => 'Standard Support - Annual',
                'sku' => 'SUP-STD-YR',
                'description' => 'Business hours support, 4-hour response time SLA, email and chat.',
                'unit_price' => 1200.00,
                'unit' => 'year',
                'is_recurring' => true,
            ],
            // Add-ons
            [
                'category' => 'Add-ons & Integrations',
                'name' => 'Email Integration - Gmail/Outlook',
                'sku' => 'ADD-EMAIL-001',
                'description' => 'Two-way email sync with Gmail or Outlook.',
                'unit_price' => 15.00,
                'unit' => 'user/month',
                'is_recurring' => true,
            ],
            [
                'category' => 'Add-ons & Integrations',
                'name' => 'Marketing Automation',
                'sku' => 'ADD-MKTG-001',
                'description' => 'Email campaigns, landing pages, lead scoring.',
                'unit_price' => 299.00,
                'unit' => 'month',
                'is_recurring' => true,
            ],
            [
                'category' => 'Add-ons & Integrations',
                'name' => 'Advanced Analytics',
                'sku' => 'ADD-ANLY-001',
                'description' => 'Custom dashboards, predictive analytics, AI insights.',
                'unit_price' => 199.00,
                'unit' => 'month',
                'is_recurring' => true,
            ],
            [
                'category' => 'Add-ons & Integrations',
                'name' => 'API Access - Standard',
                'sku' => 'ADD-API-STD',
                'description' => '10,000 API calls per month.',
                'unit_price' => 99.00,
                'unit' => 'month',
                'is_recurring' => true,
            ],
        ];

        $products = [];
        foreach ($productsData as $data) {
            $category = $categories[$data['category']] ?? null;
            $product = Product::firstOrCreate(
                ['sku' => $data['sku']],
                [
                    'category_id' => $category?->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'unit_price' => $data['unit_price'],
                    'unit' => $data['unit'],
                    'is_recurring' => $data['is_recurring'],
                    'is_active' => true,
                ]
            );
            $products[] = $product;
        }

        return $products;
    }

    private function createQuotes(mixed $users, array $products, BillingSetting $settings): void
    {
        $quotesData = [
            // Draft quotes
            [
                'title' => 'Enterprise CRM Implementation - TechCorp',
                'status' => 'draft',
                'days_ago' => 2,
                'valid_days' => 30,
                'items' => [
                    ['sku' => 'CRM-ENT-001', 'qty' => 50, 'desc' => '50 Enterprise user licenses (annual)'],
                    ['sku' => 'SVC-IMP-ENT', 'qty' => 1],
                    ['sku' => 'SUP-PRM-YR', 'qty' => 1],
                ],
            ],
            [
                'title' => 'Startup Growth Package',
                'status' => 'draft',
                'days_ago' => 1,
                'valid_days' => 30,
                'items' => [
                    ['sku' => 'CRM-PRO-001', 'qty' => 10, 'desc' => '10 Professional user licenses (annual)'],
                    ['sku' => 'SVC-IMP-BAS', 'qty' => 1],
                    ['sku' => 'ADD-EMAIL-001', 'qty' => 10],
                ],
            ],
            // Sent quotes (awaiting response)
            [
                'title' => 'Mid-Market Solution - Acme Industries',
                'status' => 'sent',
                'days_ago' => 5,
                'valid_days' => 25,
                'items' => [
                    ['sku' => 'CRM-PRO-001', 'qty' => 25, 'desc' => '25 Professional user licenses (annual)'],
                    ['sku' => 'SVC-IMP-STD', 'qty' => 1],
                    ['sku' => 'SUP-STD-YR', 'qty' => 1],
                    ['sku' => 'ADD-ANLY-001', 'qty' => 1, 'desc' => 'Advanced Analytics add-on (annual)'],
                ],
            ],
            [
                'title' => 'Custom Development Project',
                'status' => 'sent',
                'days_ago' => 3,
                'valid_days' => 14,
                'items' => [
                    ['sku' => 'SVC-DEV-HR', 'qty' => 80, 'desc' => 'Custom integration development (estimate)'],
                    ['sku' => 'SVC-CON-HR', 'qty' => 20, 'desc' => 'Solution architecture consulting'],
                ],
            ],
            // Viewed quotes
            [
                'title' => 'Professional Services Expansion',
                'status' => 'viewed',
                'days_ago' => 7,
                'valid_days' => 20,
                'items' => [
                    ['sku' => 'CRM-ENT-001', 'qty' => 15, 'desc' => 'Additional Enterprise licenses'],
                    ['sku' => 'SVC-CON-HR', 'qty' => 40, 'desc' => 'Workflow optimization project'],
                    ['sku' => 'ADD-MKTG-001', 'qty' => 1, 'desc' => 'Marketing Automation (annual)'],
                ],
            ],
            // Accepted quotes
            [
                'title' => 'Small Business Package - LocalShop Inc',
                'status' => 'accepted',
                'days_ago' => 14,
                'valid_days' => 30,
                'accepted_ago' => 10,
                'items' => [
                    ['sku' => 'CRM-STR-001', 'qty' => 12, 'desc' => 'Starter CRM (annual)'],
                    ['sku' => 'SVC-IMP-BAS', 'qty' => 1],
                ],
            ],
            [
                'title' => 'Enterprise Renewal - GlobalTech',
                'status' => 'accepted',
                'days_ago' => 21,
                'valid_days' => 30,
                'accepted_ago' => 18,
                'items' => [
                    ['sku' => 'CRM-ENT-001', 'qty' => 100, 'desc' => '100 Enterprise licenses (annual renewal)'],
                    ['sku' => 'SUP-PRM-YR', 'qty' => 1],
                    ['sku' => 'ADD-API-STD', 'qty' => 1, 'desc' => 'API Access (annual)'],
                ],
            ],
            // Rejected quotes
            [
                'title' => 'Full Platform Migration',
                'status' => 'rejected',
                'days_ago' => 30,
                'valid_days' => 30,
                'items' => [
                    ['sku' => 'CRM-ENT-001', 'qty' => 200],
                    ['sku' => 'SVC-IMP-ENT', 'qty' => 1],
                    ['sku' => 'SVC-DEV-HR', 'qty' => 200, 'desc' => 'Legacy system integration'],
                ],
            ],
            // Expired quotes
            [
                'title' => 'Q3 Pilot Program',
                'status' => 'expired',
                'days_ago' => 45,
                'valid_days' => 14,
                'items' => [
                    ['sku' => 'CRM-PRO-001', 'qty' => 5, 'desc' => 'Pilot user licenses (3 months)'],
                    ['sku' => 'SVC-CON-HR', 'qty' => 8],
                ],
            ],
        ];

        $productsBySku = collect($products)->keyBy('sku');

        foreach ($quotesData as $data) {
            $assignedUser = $users->random();
            $createdAt = Carbon::now()->subDays($data['days_ago']);
            $validUntil = $createdAt->copy()->addDays($data['valid_days']);

            $quoteNumber = $settings->quote_prefix . str_pad($settings->next_quote_number, 4, '0', STR_PAD_LEFT);
            $settings->next_quote_number++;

            $quote = Quote::create([
                'quote_number' => $quoteNumber,
                'title' => $data['title'],
                'status' => $data['status'],
                'currency' => 'USD',
                'valid_until' => $validUntil,
                'assigned_to' => $assignedUser->id,
                'notes' => 'Generated sample quote for demonstration purposes.',
                'terms' => "Payment due within 30 days of invoice date.\nPrices valid for the duration of the quote validity period.\nSubject to VRTX standard terms and conditions.",
                'view_token' => Str::random(32),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($data['status'] === 'sent' || $data['status'] === 'viewed') {
                $quote->sent_at = $createdAt->copy()->addHours(rand(1, 24));
            }
            if ($data['status'] === 'viewed') {
                $quote->viewed_at = $createdAt->copy()->addDays(rand(1, 3));
            }
            if ($data['status'] === 'accepted') {
                $quote->sent_at = $createdAt->copy()->addHours(rand(1, 24));
                $quote->viewed_at = $createdAt->copy()->addDays(1);
                $quote->accepted_at = Carbon::now()->subDays($data['accepted_ago']);
            }
            if ($data['status'] === 'rejected') {
                $quote->sent_at = $createdAt->copy()->addHours(rand(1, 24));
                $quote->viewed_at = $createdAt->copy()->addDays(2);
                $quote->rejected_at = $createdAt->copy()->addDays(5);
                $quote->rejection_reason = 'Budget constraints for this fiscal year.';
            }

            $subtotal = 0;
            $position = 1;
            foreach ($data['items'] as $item) {
                $product = $productsBySku[$item['sku']] ?? null;
                if (!$product) continue;

                $quantity = $item['qty'];
                $unitPrice = $product->unit_price;
                $total = $quantity * $unitPrice;
                $subtotal += $total;

                QuoteLineItem::create([
                    'quote_id' => $quote->id,
                    'product_id' => $product->id,
                    'description' => $item['desc'] ?? $product->description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'tax_rate' => 0,
                    'total' => $total,
                    'position' => $position++,
                ]);
            }

            $quote->subtotal = $subtotal;
            $quote->discount = 0;
            $quote->tax = 0;
            $quote->total = $subtotal;
            $quote->save();
        }

        $settings->save();
    }

    private function createInvoices(mixed $users, array $products, BillingSetting $settings): void
    {
        $invoicesData = [
            // Draft invoices
            [
                'title' => 'January 2025 - Enterprise Licenses',
                'status' => 'draft',
                'days_ago' => 1,
                'due_days' => 30,
                'items' => [
                    ['sku' => 'CRM-ENT-001', 'qty' => 25, 'desc' => 'Enterprise licenses - January 2025'],
                    ['sku' => 'ADD-EMAIL-001', 'qty' => 25],
                ],
            ],
            // Sent invoices
            [
                'title' => 'Professional Services - Phase 1',
                'status' => 'sent',
                'days_ago' => 5,
                'due_days' => 30,
                'items' => [
                    ['sku' => 'SVC-IMP-STD', 'qty' => 1],
                    ['sku' => 'SVC-CON-HR', 'qty' => 12, 'desc' => 'Requirements gathering and planning'],
                ],
            ],
            [
                'title' => 'Q4 License Renewal - TechStartup',
                'status' => 'sent',
                'days_ago' => 10,
                'due_days' => 30,
                'items' => [
                    ['sku' => 'CRM-PRO-001', 'qty' => 15, 'desc' => 'Professional licenses (Q4 2024)'],
                    ['sku' => 'SUP-STD-YR', 'qty' => 1],
                ],
            ],
            // Viewed invoices
            [
                'title' => 'Custom Development - Milestone 1',
                'status' => 'viewed',
                'days_ago' => 7,
                'due_days' => 15,
                'items' => [
                    ['sku' => 'SVC-DEV-HR', 'qty' => 40, 'desc' => 'API integration development'],
                    ['sku' => 'SVC-CON-HR', 'qty' => 8, 'desc' => 'Technical architecture review'],
                ],
            ],
            // Paid invoices
            [
                'title' => 'December 2024 - Monthly Services',
                'status' => 'paid',
                'days_ago' => 35,
                'due_days' => 30,
                'paid_ago' => 20,
                'items' => [
                    ['sku' => 'CRM-ENT-001', 'qty' => 50, 'desc' => 'Enterprise licenses - December'],
                    ['sku' => 'ADD-MKTG-001', 'qty' => 1],
                    ['sku' => 'ADD-ANLY-001', 'qty' => 1],
                ],
            ],
            [
                'title' => 'Implementation Package - Complete',
                'status' => 'paid',
                'days_ago' => 60,
                'due_days' => 30,
                'paid_ago' => 45,
                'items' => [
                    ['sku' => 'SVC-IMP-ENT', 'qty' => 1],
                    ['sku' => 'SVC-CON-HR', 'qty' => 24, 'desc' => 'Additional consulting hours'],
                ],
            ],
            [
                'title' => 'Annual Support Renewal',
                'status' => 'paid',
                'days_ago' => 45,
                'due_days' => 30,
                'paid_ago' => 30,
                'items' => [
                    ['sku' => 'SUP-PRM-YR', 'qty' => 1],
                    ['sku' => 'ADD-API-STD', 'qty' => 12, 'desc' => 'API Access (annual)'],
                ],
            ],
            // Partial payment
            [
                'title' => 'Enterprise Expansion Project',
                'status' => 'partial',
                'days_ago' => 20,
                'due_days' => 30,
                'partial_percent' => 50,
                'items' => [
                    ['sku' => 'CRM-ENT-001', 'qty' => 30, 'desc' => 'New department licenses'],
                    ['sku' => 'SVC-IMP-STD', 'qty' => 1],
                    ['sku' => 'SVC-CON-HR', 'qty' => 16],
                ],
            ],
            // Overdue invoices
            [
                'title' => 'November Services - Past Due',
                'status' => 'overdue',
                'days_ago' => 45,
                'due_days' => 30,
                'items' => [
                    ['sku' => 'CRM-PRO-001', 'qty' => 20],
                    ['sku' => 'ADD-EMAIL-001', 'qty' => 20],
                ],
            ],
            [
                'title' => 'Consulting Services - Overdue',
                'status' => 'overdue',
                'days_ago' => 50,
                'due_days' => 15,
                'items' => [
                    ['sku' => 'SVC-CON-HR', 'qty' => 32, 'desc' => 'Process optimization consulting'],
                ],
            ],
            // Cancelled invoice
            [
                'title' => 'Cancelled Order - Duplicate',
                'status' => 'cancelled',
                'days_ago' => 30,
                'due_days' => 30,
                'items' => [
                    ['sku' => 'CRM-STR-001', 'qty' => 12],
                ],
            ],
        ];

        $productsBySku = collect($products)->keyBy('sku');

        foreach ($invoicesData as $data) {
            $assignedUser = $users->random();
            $createdAt = Carbon::now()->subDays($data['days_ago']);
            $issueDate = $createdAt;
            $dueDate = $createdAt->copy()->addDays($data['due_days']);

            $invoiceNumber = $settings->invoice_prefix . str_pad($settings->next_invoice_number, 4, '0', STR_PAD_LEFT);
            $settings->next_invoice_number++;

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'title' => $data['title'],
                'status' => $data['status'],
                'currency' => 'USD',
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'assigned_to' => $assignedUser->id,
                'notes' => 'Generated sample invoice for demonstration purposes.',
                'payment_terms' => 'Net ' . $data['due_days'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if (in_array($data['status'], ['sent', 'viewed', 'paid', 'partial', 'overdue'])) {
                $invoice->sent_at = $createdAt->copy()->addHours(rand(1, 8));
            }
            if (in_array($data['status'], ['viewed', 'paid', 'partial', 'overdue'])) {
                $invoice->viewed_at = $createdAt->copy()->addDays(rand(1, 3));
            }

            $subtotal = 0;
            $position = 1;
            foreach ($data['items'] as $item) {
                $product = $productsBySku[$item['sku']] ?? null;
                if (!$product) continue;

                $quantity = $item['qty'];
                $unitPrice = $product->unit_price;
                $total = $quantity * $unitPrice;
                $subtotal += $total;

                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'description' => $item['desc'] ?? $product->description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'tax_rate' => 0,
                    'total' => $total,
                    'position' => $position++,
                ]);
            }

            $invoice->subtotal = $subtotal;
            $invoice->discount = 0;
            $invoice->tax = 0;
            $invoice->total = $subtotal;
            $invoice->balance_due = $subtotal;

            // Handle payments
            if ($data['status'] === 'paid') {
                $paidAt = Carbon::now()->subDays($data['paid_ago']);
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $subtotal,
                    'payment_method' => collect(['credit_card', 'bank_transfer', 'check'])->random(),
                    'payment_date' => $paidAt,
                    'reference' => 'PAY-' . strtoupper(Str::random(8)),
                    'notes' => 'Payment received',
                ]);
                $invoice->balance_due = 0;
                $invoice->paid_at = $paidAt;
            }

            if ($data['status'] === 'partial') {
                $partialAmount = $subtotal * ($data['partial_percent'] / 100);
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $partialAmount,
                    'payment_method' => 'bank_transfer',
                    'payment_date' => Carbon::now()->subDays(10),
                    'reference' => 'PAY-' . strtoupper(Str::random(8)),
                    'notes' => 'Partial payment - 50% deposit',
                ]);
                $invoice->balance_due = $subtotal - $partialAmount;
            }

            if ($data['status'] === 'cancelled') {
                $invoice->cancelled_at = $createdAt->copy()->addDays(2);
            }

            $invoice->save();
        }

        $settings->save();
    }
}
