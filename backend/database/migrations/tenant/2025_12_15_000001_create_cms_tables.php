<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CMS Media Folders - Organize media files (created first as media references it)
        Schema::create('cms_media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('parent_id')->nullable()->constrained('cms_media_folders')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['slug', 'parent_id']);
        });

        // CMS Media - Images, documents, videos
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('filename');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->comment('File size in bytes');
            $table->enum('type', ['image', 'document', 'video', 'audio', 'other'])->default('other');

            // Image dimensions
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Metadata
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable()->comment('EXIF data, duration, etc');

            // Organization
            $table->foreignId('folder_id')->nullable()->constrained('cms_media_folders')->nullOnDelete();
            $table->json('tags')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'mime_type']);
            $table->index('folder_id');
        });

        // CMS Templates - Reusable page/email templates
        Schema::create('cms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['page', 'email', 'form', 'landing', 'blog', 'partial'])->default('page');
            $table->json('content')->nullable()->comment('Block-based content structure');
            $table->json('settings')->nullable()->comment('Template settings (colors, fonts, etc)');
            $table->string('thumbnail')->nullable();
            $table->boolean('is_system')->default(false)->comment('System templates cannot be deleted');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
        });

        // CMS Pages - Website pages, landing pages, blog posts
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->json('content')->nullable()->comment('Block-based content structure');
            $table->enum('type', ['page', 'landing', 'blog', 'article'])->default('page');
            $table->enum('status', ['draft', 'pending_review', 'scheduled', 'published', 'archived'])->default('draft');
            $table->foreignId('template_id')->nullable()->constrained('cms_templates')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_pages')->nullOnDelete();

            // SEO fields
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image')->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('nofollow')->default(false);

            // Featured image
            $table->foreignId('featured_image_id')->nullable()->constrained('cms_media')->nullOnDelete();

            // Publishing
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Settings
            $table->json('settings')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'type']);
            $table->index(['type', 'status']);
            $table->index(['status', 'published_at']);
            $table->index('author_id');
        });

        // CMS Forms - Lead capture and contact forms
        Schema::create('cms_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('fields')->comment('Form field configuration');
            $table->json('settings')->nullable()->comment('Form settings (redirect, notifications, etc)');

            // Submission handling
            $table->enum('submit_action', ['create_lead', 'create_contact', 'update_contact', 'webhook', 'email', 'custom'])->default('create_lead');
            $table->foreignId('target_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->json('field_mapping')->nullable()->comment('Map form fields to CRM fields');

            // Display settings
            $table->string('submit_button_text')->default('Submit');
            $table->text('success_message')->nullable();
            $table->string('redirect_url')->nullable();

            // Notifications
            $table->json('notification_emails')->nullable();
            $table->foreignId('notification_template_id')->nullable()->constrained('cms_templates')->nullOnDelete();

            // Analytics
            $table->unsignedInteger('submission_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        // CMS Form Submissions - Store form submissions
        Schema::create('cms_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('cms_forms')->cascadeOnDelete();
            $table->json('data')->comment('Submitted form data');
            $table->json('metadata')->nullable()->comment('IP, user agent, referrer, etc');

            // Link to CRM record if created
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();

            $table->string('source_url')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'created_at']);
        });

        // CMS Menus - Navigation menus
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location')->nullable()->comment('header, footer, sidebar, etc');
            $table->json('items')->comment('Nested menu structure');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('location');
        });

        // CMS Categories - For organizing blog posts and pages
        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('cms_categories')->cascadeOnDelete();
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // CMS Page Categories - Many-to-many relationship
        Schema::create('cms_page_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('cms_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['page_id', 'category_id']);
        });

        // CMS Tags - For tagging content
        Schema::create('cms_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // CMS Page Tags - Many-to-many relationship
        Schema::create('cms_page_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('cms_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['page_id', 'tag_id']);
        });

        // CMS Page Versions - Version history
        Schema::create('cms_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('title');
            $table->json('content');
            $table->json('seo_data')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_id', 'version_number']);
            $table->index(['page_id', 'created_at']);
        });

        // CMS Comments - For blog comments
        Schema::create('cms_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Guest comments
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->string('author_url')->nullable();

            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'spam', 'trash'])->default('pending');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['page_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_comments');
        Schema::dropIfExists('cms_page_versions');
        Schema::dropIfExists('cms_page_tag');
        Schema::dropIfExists('cms_tags');
        Schema::dropIfExists('cms_page_category');
        Schema::dropIfExists('cms_categories');
        Schema::dropIfExists('cms_menus');
        Schema::dropIfExists('cms_form_submissions');
        Schema::dropIfExists('cms_forms');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('cms_templates');
        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_media_folders');
    }
};
