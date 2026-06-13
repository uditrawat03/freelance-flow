<?php

namespace Tests\Traits;

use App\Models\User;
use App\Models\Workspace;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

trait WithWorkspace
{
    protected User $user;

    protected Workspace $workspace;

    protected function setUpWorkspace(string $role = 'admin'): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'manager', 'freelancer'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->workspace = Workspace::factory()->create([
            'owner_id' => $this->user->id,
        ]);

        $this->workspace->users()->attach($this->user->id, ['role' => 'owner']);

        $this->actingAs($this->user);
        session(['current_workspace_id' => $this->workspace->id]);
    }

    protected function createWorkspaceMember(string $role = 'member'): User
    {
        $member = User::factory()->create();

        $this->workspace->users()->attach($member->id, ['role' => $role]);

        return $member;
    }

    protected function createOtherWorkspace(): array
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('admin');

        $otherWorkspace = Workspace::factory()->create([
            'owner_id' => $otherUser->id,
        ]);

        $otherWorkspace->users()->attach($otherUser->id, ['role' => 'owner']);

        return [$otherUser, $otherWorkspace];
    }
}
