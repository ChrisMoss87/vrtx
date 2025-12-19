<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // Products catalog
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('unit')->default('unit'); // unit, hour, month, etc.
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        // Quote templates
        Schema::create('quote_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->text('header_html')->nullable();
            $table->text('footer_html')->nullable();
            $table->json('styling')->nullable();
            $table->json('company_info')->nullable(); // Logo, address, etc.
            $table->timestamps();
        });

        // Quotes
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number')->unique();
            $table->foreignId('deal_id')->nullable(); // Link to deals module record
            $table->foreignId('contact_id')->nullable(); // Link to contacts module record
            $table->foreignId('company_id')->nullable(); // Link to accounts module record
            $table->string('status')->default('draft'); // draft, sent, viewed, accepted, rejected, expired
            $table->string('title')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('discount_type')->default('fixed'); // fixed or percent
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('valid_until')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('quote_templates')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->string('view_token')->unique(); // For public viewing
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_by')->nullable(); // Name of person who accepted
            $table->text('accepted_signature')->nullable(); // Base64 signature image
            $table->string('accepted_ip')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('view_token');
            $table->index('deal_id');
        });

        // Quote line items
        Schema::create('quote_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index('quote_id');
        });

        // Quote history/versions
        Schema::create('quote_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->integer('version');
            $table->json('snapshot'); // Full quote data at this version
            $table->text('change_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['quote_id', 'version']);
        });

        // Invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_id')->nullable();
            $table->foreignId('contact_id')->nullable();
            $table->foreignId('company_id')->nullable();
            $table->string('status')->default('draft'); // draft, sent, viewed, paid, partial, overdue, cancelled
            $table->string('title')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('payment_terms')->nullable(); // Net 30, Due on Receipt, etc.
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('quote_templates')->nullOnDelete();
            $table->string('view_token')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index('view_token');
        });

        // Invoice line items
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index('invoice_id');
        });

        // Invoice payments
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method')->nullable(); // credit_card, bank_transfer, check, cash, other
            $table->string('reference')->nullable(); // Transaction ID, check number, etc.
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('invoice_id');
        });

        // Quote/Invoice settings (per-tenant configuration)
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('quote_prefix')->default('Q');
            $table->string('invoice_prefix')->default('INV');
            $table->integer('quote_next_number')->default(1);
            $table->integer('invoice_next_number')->default(1);
            $table->integer('quote_validity_days')->default(30);
            $table->string('default_payment_terms')->default('Net 30');
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('company_info')->nullable(); // Company name, address, logo, etc.
            $table->text('default_terms')->nullable();
            $table->text('default_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('quote_versions');
        Schema::dropIfExists('quote_line_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('quote_templates');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
