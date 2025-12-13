<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Support ticket categories
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color')->default('#6B7280');
            $table->unsignedBigInteger('default_assignee_id')->nullable();
            $table->integer('default_priority')->default(2); // 1=low, 2=medium, 3=high, 4=urgent
            $table->integer('sla_response_hours')->nullable();
            $table->integer('sla_resolution_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // Support tickets
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open'); // open, pending, in_progress, resolved, closed
            $table->integer('priority')->default(2); // 1=low, 2=medium, 3=high, 4=urgent
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('submitter_id')->nullable(); // Internal user who submitted
            $table->unsignedBigInteger('portal_user_id')->nullable(); // Customer who submitted
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('channel')->default('portal'); // portal, email, phone, chat
            $table->json('tags')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sla_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();
            $table->boolean('sla_response_breached')->default(false);
            $table->boolean('sla_resolution_breached')->default(false);
            $table->integer('satisfaction_rating')->nullable();
            $table->text('satisfaction_feedback')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('ticket_categories')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });

        // Ticket replies/comments
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->text('content');
            $table->unsignedBigInteger('user_id')->nullable(); // Internal user
            $table->unsignedBigInteger('portal_user_id')->nullable(); // Customer
            $table->boolean('is_internal')->default(false); // Internal notes not visible to customer
            $table->boolean('is_system')->default(false); // System-generated messages
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Ticket activity log
        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('action'); // created, assigned, status_changed, priority_changed, replied, etc.
            $table->json('changes')->nullable(); // Old and new values
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('portal_user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Canned responses for quick replies
        Schema::create('ticket_canned_responses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('shortcut')->nullable();
            $table->text('content');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->boolean('is_shared')->default(true); // Available to all agents
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('ticket_categories')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

        // Support teams for ticket routing
        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('users')->nullOnDelete();
        });

        // Team members
        Schema::create('support_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member'); // member, supervisor
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
            $table->foreign('team_id')->references('id')->on('support_teams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // SLA policies
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions')->nullable(); // When to apply this SLA
            $table->json('targets'); // Response and resolution targets by priority
            $table->json('escalation_rules')->nullable();
            $table->integer('priority')->default(0); // Higher priority SLAs take precedence
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Ticket escalations
        Schema::create('ticket_escalations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('type'); // response_sla, resolution_sla, manual
            $table->string('level'); // first, second, third
            $table->unsignedBigInteger('escalated_to')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('escalated_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->foreign('escalated_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('escalated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Knowledge base articles
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('status')->default('draft'); // draft, published, archived
            $table->unsignedBigInteger('author_id');
            $table->json('tags')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->boolean('is_public')->default(true); // Visible to customers
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Knowledge base categories
        Schema::create('kb_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('kb_categories')->nullOnDelete();
        });

        // Add kb_category relationship to articles
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('kb_categories')->nullOnDelete();
        });

        // Article feedback
        Schema::create('kb_article_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('portal_user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('kb_articles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_feedback');
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
        Schema::dropIfExists('kb_categories');
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('ticket_escalations');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('support_team_members');
        Schema::dropIfExists('support_teams');
        Schema::dropIfExists('ticket_canned_responses');
        Schema::dropIfExists('ticket_activities');
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('ticket_categories');
    }
};
