<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::where('slug', 'demo-agency')->firstOrFail();

        $tags = collect([
            ['name' => 'Web Design', 'colour' => '#005cff'],
            ['name' => 'Mobile App', 'colour' => '#16a34a'],
            ['name' => 'Branding', 'colour' => '#ec4899'],
            ['name' => 'SEO', 'colour' => '#d97706'],
            ['name' => 'Copywriting', 'colour' => '#7c3aed'],
            ['name' => 'Photography', 'colour' => '#0891b2'],
            ['name' => 'Video Production', 'colour' => '#dc2626'],
            ['name' => 'UI/UX Design', 'colour' => '#2563eb'],
            ['name' => 'Backend Development', 'colour' => '#475569'],
            ['name' => 'Frontend Development', 'colour' => '#0f766e'],
            ['name' => 'E-commerce', 'colour' => '#9333ea'],
            ['name' => 'Social Media', 'colour' => '#ea580c'],
            ['name' => 'Content Strategy', 'colour' => '#4f46e5'],
            ['name' => 'Data Analysis', 'colour' => '#0ea5e9'],
            ['name' => 'DevOps', 'colour' => '#64748b'],
        ])->map(fn (array $tag) => Tag::updateOrCreate([
            'slug' => str($tag['name'])->slug()->toString(),
        ], $tag));

        Project::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->get()
            ->each(function (Project $project) use ($tags): void {
                $randomTags = $tags->random(fake()->numberBetween(1, 3));

                $project->tags()->sync($randomTags->pluck('id')->toArray());
            });

        $this->command->info('Seeded tags and attached them to projects.');
    }
}
