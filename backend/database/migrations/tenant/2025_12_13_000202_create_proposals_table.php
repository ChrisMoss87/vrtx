<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proposal Templates
        Schema::create('proposal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // sales, services, partnership, etc.
            $table->json('default_sections')->nullable(); // Default section structure
            $table->json('styling')->nullable(); // Colors, fonts, branding
            $table->string('cover_image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        // Proposals
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique(); // Public identifier
            $table->string('name');
            $table->string('proposal_number')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('proposal_templates')->nullOnDelete();
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('status')->default('draft'); // draft, sent, viewed, accepted, rejected, expired
            $table->json('cover_page')->nullable(); // Cover page content
            $table->json('styling')->nullable(); // Custom styling overrides
            $table->decimal('total_value', 15, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('total_time_spent')->default(0); // Seconds
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_by')->nullable();
            $table->text('accepted_signature')->nullable();
            $table->string('accepted_ip')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('deal_id');
            $table->index('contact_id');
            $table->index('status');
            $table->index('valid_until');
        });

        // Proposal Sections
        Schema::create('proposal_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('section_type'); // cover, executive_summary, scope, pricing, timeline, terms, team, case_study, custom
            $table->string('title');
            $table->longText('content')->nullable(); // Rich text content
            $table->json('settings')->nullable(); // Section-specific settings
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_locked')->default(false); // Prevent client editing
            $table->timestamps();

            $table->index(['proposal_id', 'display_order']);
        });

        // Proposal Pricing Tables
        Schema::create('proposal_pricing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('proposal_sections')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit')->nullable(); // hours, units, monthly, etc.
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_selected')->default(true); // Client can toggle optional items
            $table->string('pricing_type')->default('fixed'); // fixed, recurring, usage
            $table->string('billing_frequency')->nullable(); // monthly, annually, one_time
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('product_id')->nullable();
            $table->timestamps();

            $table->index(['proposal_id', 'display_order']);
        });

        // Proposal View Analytics
        Schema::create('proposal_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('viewer_email')->nullable();
            $table->string('viewer_name')->nullable();
            $table->string('session_id');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('time_spent')->default(0); // Seconds
            $table->json('sections_viewed')->nullable(); // Section IDs and time spent
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('referrer')->nullable();
            $table->timestamps();

            $table->index(['proposal_id', 'started_at']);
            $table->index('viewer_email');
        });

        // Proposal Comments (client questions/feedback)
        Schema::create('proposal_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('proposal_sections')->nullOnDelete();
            $table->text('comment');
            $table->string('author_email');
            $table->string('author_name')->nullable();
            $table->string('author_type')->default('client'); // client, internal
            $table->foreignId('reply_to_id')->nullable()->constrained('proposal_comments')->nullOnDelete();
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('proposal_id');
            $table->index(['proposal_id', 'section_id']);
        });

        // Proposal Content Blocks (reusable content)
        Schema::create('proposal_content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable(); // about_us, case_studies, team_bios, terms
            $table->string('block_type'); // text, image, pricing, team, testimonial
            $table->longText('content');
            $table->json('settings')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_content_blocks');
        Schema::dropIfExists('proposal_comments');
        Schema::dropIfExists('proposal_views');
        Schema::dropIfExists('proposal_pricing_items');
        Schema::dropIfExists('proposal_sections');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('proposal_templates');
    }
};
