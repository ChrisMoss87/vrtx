<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrates data from Spatie permission tables to custom RBAC structure.
 * - Copies user-role assignments from model_has_roles to user_roles
 * - Marks system roles (admin, super-admin)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migrate user-role assignments from Spatie's polymorphic table
        if (Schema::hasTable('model_has_roles') && Schema::hasTable('user_roles')) {
            // Get all user role assignments (model_type = App\Models\User)
            $assignments = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->orWhere('model_type', 'App\\Infrastructure\\Persistence\\Eloquent\\Models\\User')
                ->get();

            foreach ($assignments as $assignment) {
                // Check if assignment already exists
                $exists = DB::table('user_roles')
                    ->where('user_id', $assignment->model_id)
                    ->where('role_id', $assignment->role_id)
                    ->exists();

                if (!$exists) {
                    DB::table('user_roles')->insert([
                        'user_id' => $assignment->model_id,
                        'role_id' => $assignment->role_id,
                        'assigned_at' => now(),
                        'assigned_by' => null,
                    ]);
                }
            }
        }

        // Mark system roles
        DB::table('roles')
            ->whereIn('name', ['admin', 'super-admin', 'administrator', 'super_admin'])
            ->update(['is_system' => true]);

        // Set display names for roles that don't have them
        $roles = DB::table('roles')->whereNull('display_name')->get();
        foreach ($roles as $role) {
            $displayName = ucwords(str_replace(['-', '_'], ' ', $role->name));
            DB::table('roles')
                ->where('id', $role->id)
                ->update(['display_name' => $displayName]);
        }
    }

    public function down(): void
    {
        // Clear user_roles table (data was copied, original still exists)
        DB::table('user_roles')->truncate();

        // Reset system role flags
        DB::table('roles')->update(['is_system' => false]);

        // Clear display names
        DB::table('roles')->update(['display_name' => null]);
    }
};
