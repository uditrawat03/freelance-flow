<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_a_lively_demo_workspace(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'demo@freelanceflow.test')->first();
        $workspace = Workspace::where('slug', 'demo-agency')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($workspace);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($workspace->users()->whereKey($user)->exists());

        $this->assertGreaterThanOrEqual(50, Client::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count());
        $this->assertGreaterThanOrEqual(80, Project::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count());
        $this->assertGreaterThanOrEqual(100, Invoice::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(15, Tag::count());
        $this->assertGreaterThan(0, Attachment::count());
    }
}
