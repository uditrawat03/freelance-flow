<?php

namespace Tests\Browser;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FileUploadTest extends DuskTestCase
{
    use DatabaseTruncation;
    use DuskWithWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkspace();
    }

    public function test_user_can_upload_a_file_to_a_project(): void
    {
        $project = $this->createProject();
        $tempFile = tempnam(sys_get_temp_dir(), 'dusk_test_') . '.pdf';
        file_put_contents($tempFile, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $this->browse(function (Browser $browser) use ($project, $tempFile) {
            $this->loginWith($browser);

            $browser->visit("/projects/{$project->id}/edit")
                ->waitFor('@project-file')
                ->attach('@project-file', $tempFile)
                ->waitForText('Ready to upload')
                ->assertSee(basename($tempFile))
                ->click('@upload-project-file')
                ->waitForText('Download')
                ->assertSee('Download');
        });

        @unlink($tempFile);
    }

    public function test_upload_button_appears_only_after_file_selection(): void
    {
        $project = $this->createProject();
        $tempFile = tempnam(sys_get_temp_dir(), 'dusk_test_') . '.pdf';
        file_put_contents($tempFile, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $this->browse(function (Browser $browser) use ($project, $tempFile) {
            $this->loginWith($browser);

            $browser->visit("/projects/{$project->id}/edit")
                ->waitFor('@project-file')
                ->assertMissing('@upload-project-file')
                ->attach('@project-file', $tempFile)
                ->waitFor('@upload-project-file')
                ->assertVisible('@upload-project-file');
        });

        @unlink($tempFile);
    }

    private function createProject(): Project
    {
        $client = Client::factory()->active()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);

        return Project::factory()->create([
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
    }
}
