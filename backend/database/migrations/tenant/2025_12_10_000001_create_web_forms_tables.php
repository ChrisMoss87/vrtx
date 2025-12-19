<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main web forms table
        Schema::create('web_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->default('{}');
            $table->jsonb('styling')->default('{}');
            $table->jsonb('thank_you_config')->default('{}');
            $table->jsonb('spam_protection')->default('{}');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assign_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('is_active');
        });

        // Form fields table
        Schema::create('web_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_id')->constrained('web_forms')->cascadeOnDelete();
            $table->string('field_type', 50);
            $table->string('label');
            $table->string('name')->nullable(); // field name/key
            $table->string('placeholder')->nullable();
            $table->boolean('is_required')->default(false);
            $table->foreignId('module_field_id')->nullable()->constrained('fields')->nullOnDelete();
            $table->jsonb('options')->nullable(); // for select/radio/checkbox
            $table->jsonb('validation_rules')->nullable();
            $table->integer('display_order')->default(0);
            $table->jsonb('settings')->default('{}');
            $table->timestamps();

            $table->index(['web_form_id', 'display_order']);
        });

        // Form submissions table
        Schema::create('web_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_id')->constrained('web_forms')->cascadeOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('module_records')->nullOnDelete();
            $table->jsonb('submission_data');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referrer')->nullable();
            $table->jsonb('utm_params')->nullable();
            $table->string('status')->default('processed'); // processed, failed, spam
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index('web_form_id');
            $table->index('submitted_at');
            $table->index('status');
        });

        // Form analytics table (daily aggregates)
        Schema::create('web_form_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_form_id')->constrained('web_forms')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('submissions')->default(0);
            $table->unsignedInteger('successful_submissions')->default(0);
            $table->unsignedInteger('spam_blocked')->default(0);
            $table->timestamps();

            $table->unique(['web_form_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_form_analytics');
        Schema::dropIfExists('web_form_submissions');
        Schema::dropIfExists('web_form_fields');
        Schema::dropIfExists('web_forms');
    }
};