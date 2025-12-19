<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('plugin_slug', 50);
            $table->string('bundle_slug', 50)->nullable(); // If purchased via bundle
            $table->string('status', 20)->default('active'); // active, expired, cancelled
            $table->string('pricing_model', 20)->default('per_user'); // per_user, flat, usage, included
            $table->integer('quantity')->default(1); // For per-user: number of seats
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->string('external_subscription_item_id', 100)->nullable(); // Stripe subscription item ID
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('plugin_slug');
            $table->index('status');
            $table->index('bundle_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_licenses');
    }
};
