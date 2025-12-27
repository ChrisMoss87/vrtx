<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_subscriptions')) {
            Schema::create('tenant_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('plan')->default('free');
                $table->string('status')->default('active');
                $table->string('billing_cycle')->default('monthly');
                $table->integer('user_count')->default(1);
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_end')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plugin_licenses')) {
            Schema::create('plugin_licenses', function (Blueprint $table) {
                $table->id();
                $table->string('plugin_slug');
                $table->string('status')->default('active');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plugin_usages')) {
            Schema::create('plugin_usages', function (Blueprint $table) {
                $table->id();
                $table->string('plugin_slug');
                $table->string('metric');
                $table->integer('quantity')->default(0);
                $table->integer('limit')->nullable();
                $table->timestamp('period_start');
                $table->timestamp('period_end');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plugins')) {
            Schema::create('plugins', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plugin_bundles')) {
            Schema::create('plugin_bundles', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->json('plugins')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_bundles');
        Schema::dropIfExists('plugins');
        Schema::dropIfExists('plugin_usages');
        Schema::dropIfExists('plugin_licenses');
        Schema::dropIfExists('tenant_subscriptions');
    }
};
