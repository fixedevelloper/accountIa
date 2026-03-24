<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ================================
        // 1️⃣ Roles
        // ================================
        $roles = [
            ['name' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'user', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('roles')->insert($roles);

        // ================================
        // 2️⃣ Permissions
        // ================================
        $permissions = [
            ['name' => 'create_company'],
            ['name' => 'manage_users'],
            ['name' => 'view_reports'],
            ['name' => 'manage_accounting'],
        ];

        DB::table('permissions')->insert($permissions);

        // ================================
        // 3️⃣ Role-Permissions (pivot)
        // ================================
        // Exemple simple : owner a tout, admin un peu, user très limité
        $rolePermissions = [
            // owner
            ['role_id' => 1, 'permission_id' => 1],
            ['role_id' => 1, 'permission_id' => 2],
            ['role_id' => 1, 'permission_id' => 3],
            ['role_id' => 1, 'permission_id' => 4],

            // admin
            ['role_id' => 2, 'permission_id' => 2],
            ['role_id' => 2, 'permission_id' => 3],
            ['role_id' => 2, 'permission_id' => 4],

            // user
            ['role_id' => 3, 'permission_id' => 3],
        ];

        DB::table('role_permissions')->insert($rolePermissions);

        $this->command->info('Roles et permissions seedées avec succès ✅');
    }
}
