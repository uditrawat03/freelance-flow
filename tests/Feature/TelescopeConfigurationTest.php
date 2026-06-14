<?php

namespace Tests\Feature;

use App\Jobs\RefreshDashboardCache;
use App\Jobs\SendProjectNotification;
use App\Models\Client;
use App\Models\Project;
use App\Models\Workspace;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Telescope\Watchers;
use Tests\TestCase;

class TelescopeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_telescope_application_provider_is_registered_conditionally(): void
    {
        $providers = require base_path('bootstrap/providers.php');
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertNotContains(TelescopeServiceProvider::class, $providers);
        $this->assertArrayHasKey('laravel/telescope', $composer['require-dev']);
        $this->assertFalse((bool) config('telescope.enabled'));
    }

    public function test_telescope_watchers_are_tuned_for_scalable_local_observability(): void
    {
        $this->assertSame('low', config('telescope.queue.queue'));
        $this->assertSame(100, config('telescope.watchers.'.Watchers\QueryWatcher::class.'.slow'));
        $this->assertTrue(config('telescope.watchers.'.Watchers\QueryWatcher::class.'.ignore_packages'));
        $this->assertFalse(config('telescope.watchers.'.Watchers\ModelWatcher::class.'.hydrations'));
        $this->assertSame(['eloquent.created*', 'eloquent.updated*', 'eloquent.deleted*'], config('telescope.watchers.'.Watchers\ModelWatcher::class.'.events'));
        $this->assertFalse(config('telescope.watchers.'.Watchers\RedisWatcher::class));
        $this->assertFalse(config('telescope.watchers.'.Watchers\ViewWatcher::class));
    }

    public function test_telescope_ignores_noisy_paths_and_commands(): void
    {
        $this->assertContains('livewire*', config('telescope.ignore_paths'));
        $this->assertContains('telescope*', config('telescope.ignore_paths'));
        $this->assertContains('horizon*', config('telescope.ignore_paths'));
        $this->assertContains('health', config('telescope.ignore_paths'));
        $this->assertContains('queue:*', config('telescope.ignore_commands'));
        $this->assertContains('schedule:run', config('telescope.ignore_commands'));
    }

    public function test_jobs_expose_telescope_tags_for_filtering(): void
    {
        $workspace = Workspace::factory()->create();
        $client = Client::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create([
            'client_id' => $client->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->assertSame([
            'project:'.$project->id,
            'client:'.$project->client_id,
            'workspace:'.$project->workspace_id,
            'queue:emails',
            'type:project-notification',
        ], (new SendProjectNotification($project))->tags());

        $this->assertSame([
            'workspace:'.$project->workspace_id,
            'queue:low',
            'type:dashboard-cache-refresh',
        ], (new RefreshDashboardCache((int) $project->workspace_id))->tags());
    }
}
