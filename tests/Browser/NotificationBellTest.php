<?php

namespace Tests\Browser;

use App\Models\Client;
use App\Models\Project;
use App\Notifications\ProjectStatusChanged;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class NotificationBellTest extends DuskTestCase
{
    use DatabaseTruncation;
    use DuskWithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_notification_bell_shows_unread_count(): void
    {
        $this->notifyUserAboutProjectStatus();

        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/dashboard')
                ->waitFor('@notification-bell')
                ->assertSeeIn('@notification-bell', '1');
        });
    }

    public function test_clicking_bell_opens_notification_panel(): void
    {
        $this->notifyUserAboutProjectStatus();

        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/dashboard')
                ->waitFor('@notification-bell')
                ->click('@notification-bell')
                ->waitForText('Notifications')
                ->assertSee('Dusk Project')
                ->assertSee('Completed');
        });
    }

    public function test_marking_notifications_as_read_clears_badge(): void
    {
        $this->notifyUserAboutProjectStatus();

        $this->browse(function (Browser $browser) {
            $this->loginWith($browser);

            $browser->visit('/dashboard')
                ->waitFor('@notification-bell')
                ->assertSeeIn('@notification-bell', '1')
                ->click('@notification-bell')
                ->waitUntilMissing('@notification-count')
                ->assertMissing('@notification-count');
        });
    }

    private function notifyUserAboutProjectStatus(): void
    {
        $client = Client::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
        $project = Project::factory()->create([
            'name' => 'Dusk Project',
            'status' => 'completed',
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        $this->user->notify(new ProjectStatusChanged($project, 'active'));
    }
}
