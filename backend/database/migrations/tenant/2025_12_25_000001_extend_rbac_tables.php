<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends existing RBAC tables for custom DDD implementation.
 * - Adds additional columns to roles table
 * - Creates simplified user_roles pivot table (replaces polymorphic model_has_roles)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Extend roles table with additional metadata
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('description');
            }
        });

        // Create simplified user_roles pivot table
        // This replaces the polymorphic model_has_roles for better performance
        if (!Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

                // Unique constraint - user can only have each role once
                $table->unique(['user_id', 'role_id']);

                // Index for efficient lookups
                $table->index('role_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'is_system']);
        });
    }
};
