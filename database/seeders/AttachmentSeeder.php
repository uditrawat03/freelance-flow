<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AttachmentSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::where('slug', 'demo-agency')->firstOrFail();

        $files = [
            ['Project Brief.pdf', 'application/pdf', 382000],
            ['Brand Assets.zip', 'application/zip', 4850000],
            ['Wireframes.png', 'image/png', 920000],
            ['Content Plan.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 186000],
            ['Analytics Export.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 244000],
        ];

        Project::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['active', 'completed', 'on_hold'])
            ->inRandomOrder()
            ->limit(28)
            ->get()
            ->each(function (Project $project) use ($files): void {
                collect($files)
                    ->random(fake()->numberBetween(1, 2))
                    ->each(function (array $file) use ($project): void {
                        Attachment::create([
                            'project_id' => $project->id,
                            'original_name' => $file[0],
                            'stored_name' => 'attachments/demo/'.fake()->uuid().'-'.str($file[0])->slug('.'),
                            'mime_type' => $file[1],
                            'size' => $file[2],
                            'disk' => 'local',
                        ]);
                    });
            });

        $this->command->info('Seeded sample project attachment metadata.');
    }
}
