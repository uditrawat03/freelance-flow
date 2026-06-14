<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        $user = User::updateOrCreate([
            'email' => 'demo@freelanceflow.test',
        ], [
            'name' => 'Demo User',
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('admin');

        $workspace = Workspace::updateOrCreate([
            'slug' => 'demo-agency',
        ], [
            'name' => 'Demo Agency',
            'owner_id' => $user->id,
            'plan' => 'pro',
        ]);

        $workspace->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner'],
        ]);

        session(['current_workspace_id' => $workspace->id]);

        $this->resetDemoWorkspaceData($workspace);

        $this->call([
            ClientSeeder::class,
            TagSeeder::class,
            InvoiceSeeder::class,
            AttachmentSeeder::class,
        ]);
    }

    private function resetDemoWorkspaceData(Workspace $workspace): void
    {
        $projectIds = Project::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->pluck('id');

        DB::table('attachments')->whereIn('project_id', $projectIds)->delete();
        DB::table('project_tag')->whereIn('project_id', $projectIds)->delete();
        DB::table('invoices')->where('workspace_id', $workspace->id)->delete();
        DB::table('projects')->where('workspace_id', $workspace->id)->delete();
        DB::table('clients')->where('workspace_id', $workspace->id)->delete();
    }
}
