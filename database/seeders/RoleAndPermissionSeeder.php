<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $permissions = [
            // Clients
            'view clients',
            'create clients',
            'edit clients',
            'delete clients',
            // Projects
            'view projects',
            'create projects',
            'edit projects',
            'delete projects',
            // Invoices
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'send invoices',
            // Reports
            'view reports',
            // Settings
            'manage settings',
            'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Admin — can do everything
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // Manager — can do most things except manage users and settings
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'view clients',
            'create clients',
            'edit clients',
            'view projects',
            'create projects',
            'edit projects',
            'view invoices',
            'create invoices',
            'edit invoices',
            'send invoices',
            'view reports',
        ]);

        // Freelancer — view and create only, no delete or send
        $freelancer = Role::firstOrCreate(['name' => 'freelancer']);
        $freelancer->syncPermissions([
            'view clients',
            'create clients',
            'view projects',
            'create projects',
            'view invoices',
            'create invoices',
        ]);

        $this->command->info('✓ Roles and permissions seeded');
    }
}