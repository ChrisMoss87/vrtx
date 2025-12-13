<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('plan', 50)->default('free'); // free, starter, professional, business, enterprise
            $table->string('status', 20)->default('active'); // active, past_due, cancelled, trialing
            $table->string('billing_cycle', 20)->default('monthly'); // monthly, yearly
            $table->integer('user_count')->default(1);
            $table->decimal('price_per_user', 10, 2)->nullable();
            $table->string('external_subscription_id', 100)->nullable(); // Stripe subscription ID
            $table->string('external_customer_id', 100)->nullable(); // Stripe customer ID
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('plan');
            $table->index('status');
            $table->index('external_subscription_id');
            $table->index('external_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};