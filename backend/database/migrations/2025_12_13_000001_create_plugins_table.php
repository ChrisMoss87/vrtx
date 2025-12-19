<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plugin catalog - central database (not per-tenant)
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique(); // 'forecasting-pro', 'quotes-invoices'
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('category', 50); // sales, marketing, communication, analytics, ai, documents, service
            $table->string('tier', 20); // core, professional, advanced, enterprise
            $table->string('pricing_model', 20); // per_user, flat, usage, included
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable(); // Annual price (discounted)
            $table->jsonb('features')->default('[]'); // Feature list for display
            $table->jsonb('requirements')->nullable(); // Required plugins/plans
            $table->jsonb('limits')->nullable(); // Usage limits if applicable
            $table->string('icon', 50)->nullable(); // Lucide icon name
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('tier');
            $table->index('is_active');
        });

        // Plugin bundles - central database
        Schema::create('plugin_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->jsonb('plugins')->default('[]'); // Array of plugin slugs
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->integer('discount_percent')->default(0); // e.g., 25 for 25% off
            $table->string('icon', 50)->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_bundles');
        Schema::dropIfExists('plugins');
    }
};
