<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contracts - Track customer contracts/subscriptions
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contract_number')->unique();
            $table->string('related_module'); // e.g., 'accounts', 'contacts'
            $table->unsignedBigInteger('related_id');
            $table->string('type')->default('subscription'); // subscription, license, support, etc.
            $table->string('status')->default('active'); // draft, pending, active, expired, cancelled
            $table->decimal('value', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_frequency')->nullable(); // monthly, quarterly, annual
            $table->date('start_date');
            $table->date('end_date');
            $table->date('renewal_date')->nullable(); // When renewal process should start
            $table->integer('renewal_notice_days')->default(30);
            $table->boolean('auto_renew')->default(false);
            $table->string('renewal_status')->nullable(); // pending, in_progress, renewed, lost
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['related_module', 'related_id']);
            $table->index('status');
            $table->index('end_date');
            $table->index('renewal_date');
        });

        // Contract Line Items
        Schema::create('contract_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // Renewals - Track renewal opportunities
        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, in_progress, won, lost
            $table->decimal('original_value', 15, 2)->default(0);
            $table->decimal('renewal_value', 15, 2)->nullable();
            $table->decimal('upsell_value', 15, 2)->default(0);
            $table->string('renewal_type')->nullable(); // renewal, expansion, contraction, churn
            $table->date('due_date');
            $table->date('closed_date')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('new_contract_id')->nullable(); // Link to the new contract
            $table->string('loss_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
        });

        // Renewal Activities
        Schema::create('renewal_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // email, call, meeting, note, status_change
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('type');
        });

        // Renewal Reminders
        Schema::create('renewal_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->integer('days_before'); // Days before expiry
            $table->string('reminder_type'); // email, task, notification
            $table->json('recipients')->nullable(); // User IDs or email addresses
            $table->string('template')->nullable(); // Email template name
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'is_sent']);
        });

        // Renewal Settings
        Schema::create('renewal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json
            $table->timestamps();
        });

        // Renewal Forecasts - Monthly/Quarterly renewal forecasts
        Schema::create('renewal_forecasts', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_type'); // month, quarter, year
            $table->decimal('expected_renewals', 15, 2)->default(0);
            $table->decimal('at_risk_value', 15, 2)->default(0);
            $table->decimal('churned_value', 15, 2)->default(0);
            $table->decimal('renewed_value', 15, 2)->default(0);
            $table->decimal('expansion_value', 15, 2)->default(0);
            $table->integer('total_contracts')->default(0);
            $table->integer('at_risk_count')->default(0);
            $table->integer('renewed_count')->default(0);
            $table->integer('churned_count')->default(0);
            $table->decimal('retention_rate', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_type']);
        });

        // Health Scores - Track customer health for renewal prediction
        Schema::create('customer_health_scores', function (Blueprint $table) {
            $table->id();
            $table->string('related_module'); // accounts
            $table->unsignedBigInteger('related_id');
            $table->integer('overall_score')->default(0); // 0-100
            $table->integer('engagement_score')->default(0);
            $table->integer('support_score')->default(0);
            $table->integer('product_usage_score')->default(0);
            $table->integer('payment_score')->default(0);
            $table->integer('relationship_score')->default(0);
            $table->string('health_status')->default('healthy'); // healthy, at_risk, critical
            $table->json('score_breakdown')->nullable();
            $table->json('risk_factors')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['related_module', 'related_id']);
            $table->index('health_status');
            $table->index('overall_score');
        });

        // Health Score History
        Schema::create('health_score_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_health_score_id')->constrained()->cascadeOnDelete();
            $table->integer('overall_score');
            $table->json('scores_snapshot')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['customer_health_score_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_score_history');
        Schema::dropIfExists('customer_health_scores');
        Schema::dropIfExists('renewal_forecasts');
        Schema::dropIfExists('renewal_settings');
        Schema::dropIfExists('renewal_reminders');
        Schema::dropIfExists('renewal_activities');
        Schema::dropIfExists('renewals');
        Schema::dropIfExists('contract_line_items');
        Schema::dropIfExists('contracts');
    }
};
