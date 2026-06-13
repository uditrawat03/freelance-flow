<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Workspace;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait DuskWithWorkspace
{
    protected User $user;

    protected Workspace $workspace;

    protected function setUpWorkspace(string $role = 'admin'): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view clients',
            'create clients',
            'edit clients',
            'delete clients',
            'view projects',
            'create projects',
            'edit projects',
            'delete projects',
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'send invoices',
            'view reports',
            'manage settings',
            'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        foreach (['manager', 'freelancer'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);
        $this->user->assignRole($role);

        $this->workspace = Workspace::factory()->create([
            'owner_id' => $this->user->id,
        ]);

        $this->workspace->users()->attach($this->user->id, ['role' => 'owner']);
    }

    protected function loginWith(Browser $browser): void
    {
        $browser->driver->manage()->deleteAllCookies();

        $browser->loginAs($this->user)
            ->visit("/testing/set-workspace/{$this->workspace->id}")
            ->waitForText('OK');
    }
}
